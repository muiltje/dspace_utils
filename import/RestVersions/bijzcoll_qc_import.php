<?php
require_once 'import_init.php';

require_once CLASSES . 'DigitizedExport.php';
require_once CLASSES . 'ItemMetadata.php';

$sBijzCollSubmitter = 'c.vanderstappen@uu.nl';
$sBijzCollImporter = 'c.vanderstappen@uu.nl';

$oItemMetadata = new ItemMetadata();


$sSourceDir = SIPBASE . 'bijzcoll/qc/';
$sImportDirectory = IMPORTBASE . 'bijzcoll_qc/';

$bDoImport = 0;
$bVerbose = 0;
$bDevelop = 0;

$aOptions = getopt("iDhv");


if (isset($aOptions['i'])) {
    $bDoImport = 1;
}
if (isset($aOptions['v'])) {
    $bVerbose = 1;
}
if (isset($aOptions['D'])) {
    $bDevelop = 1;
}
if (isset($aOptions['h'])) {
    showUsage();
}
if(empty ($aOptions)) {
    showUsage();
}

$oFandD = new FileAndDisk();



$logstart = "================ BIJZCOLL QC START ================\n";
wlog($logstart, '');


/**
 * Get collection-ids for the various Digitized Objects collections
 */
$sCommunityName='Digitized Objects';
//$nCollectionId = 778; //TEST!!!
$nCollectionId = 867; //PROD

$aNewBooks = array();
$aDoneBooks = array();

//what do we have in SIP
$dh = opendir($sSourceDir);
while (($infile = readdir($dh)) !== false) {
    if ($infile != '.' && $infile != '..') {
        if (opendir($sSourceDir . $infile)) {
            //directories with _ in their name have already been done
            if (strpos($infile, '_') === FALSE) {
                $aNewBooks[] = $infile;
            }
            else {
                $aDoneBooks[] = $infile;
            }
         }
    }
}

if ($bVerbose == 1) {
    $line = count($aNewBooks) . ' new items found' . "\n";
    wlog($line, 'INF');
}

$sTodaysImportDir = $sImportDirectory . date('Ymd') . '/';

//qc items are not added regularly, so first check if there are any
if (count($aNewBooks) > 0) {
	if (!@opendir($sTodaysImportDir)) {
		mkdir($sTodaysImportDir);
	}

	$sVendor = 'Digitalisering UBU';
	$sVendorTable = 'ubuaux_vendor_recs';

	$nSizeImported = 0;

	foreach ($aNewBooks as $sIdentifier) {
		$sBookDirectory = $sSourceDir . $sIdentifier . '/';
		//echo 'doing ' . $sBookDirectory . "\n";
    
		//is it in DSpace
		$aTitleData = $oItemMetadata->findItemByMetadata(143, $sIdentifier);
	
		if (isset($aTitleData['error'])) {
			wlog($aTitleData['error'], 'INF');
			$aErrorsToMail[] = $aTitleData['error'];
		}
		else {
			if (isset($aTitleData['itemids'])) {
				if ($bVerbose == 1) {
					$line = $sIdentifier . ' has already been archived ';
					wlog($line, 'INF');
                }
				$sNewDirName = $sSourceDir . '_' . $sIdentifier;
				if ($bVerbose == 1) {
					$line = 'renaming ' . $sBookDirectory . ' to ' . $sNewDirName;
					wlog($line, 'INF');
				}
				rename($sBookDirectory, $sNewDirName);
			}
			else {
				if ($bVerbose == 1) {
					wlog('Processing ' . $sIdentifier, 'INF');
				}
                
				//check to make sure there is still room
				$sCurrentAssetStore = getAssetStore();
				$sCanBeImported = checkDirectorySize($sCurrentAssetStore, $nSizeImported);
                
				if ($sCanBeImported == 'y') {
					//create import subdirectory
					$sCollectionDestDir = $sTodaysImportDir . $nCollectionId;
					if (!@opendir($sCollectionDestDir)) {
						mkdir($sCollectionDestDir);
					}
					$sThisItemDestinationDir = $sCollectionDestDir . '/qc_' . $sIdentifier . '/';
					//echo 'I would create ' . $sThisItemDestinationDir . "\n";
					if (!@opendir($sThisItemDestinationDir)) {
						mkdir($sThisItemDestinationDir);
					}
            
					//create dublin_core.xml
					$sDublinCoreFile = $sThisItemDestinationDir . 'dublin_core.xml';
					$fh = fopen($sDublinCoreFile, "w");
					fwrite($fh, "<dublin_core>\n");
                   
					$sIdLines = '<dcvalue element="identifier" qualifier="other">' . $sIdentifier . '</dcvalue>' . "\n";
					fwrite($fh, $sIdLines);
				
					//these are not workflow items, so no point in querying the workflowservice
					//all the metadata we need can be deducted from the directory name $sIdentifier
					$sItemTitle = $sIdentifier;
					$sLine = '<dcvalue element="title" qualifier="none">';
					$sLine .= $sItemTitle;
					$sLine .= '</dcvalue>';
					$sLine .= "\n";
					fwrite($fh, $sLine);
				
					//they are always Closed Access
					$sAccLine = '<dcvalue element="rights" qualifier="accessrights">';
					$sAccLine .= 'Closed Access';
					$sAccLine .= '</dcvalue>';
					$sAccLine .= "\n";
					fwrite($fh, $sAccLine);
				
					//since they are Closed Access, give them a date embargo to 2050
					$sEmbargoLine = '<dcvalue element="date" qualifier="embargo">';
					$sEmbargoLine .= '2050-01-01';
					$sEmbargoLine .= '</dcvalue>';
					$sEmbargoLine .= "\n";
					fwrite($fh, $sEmbargoLine);
				
					//content type
					$sTypeLine = '<dcvalue element="type" qualifier="content">';
					$sTypeLine .= 'Quality control scan';
					$sTypeLine .= '</dcvalue>';
					$sTypeLine .= "\n";
					fwrite($fh, $sTypeLine);
				
					//parse date.issued from identifier
					$sDateIssued = substr($sIdentifier, 0, 4) . '-' . substr($sIdentifier, 4, 2) . '-' . substr($sIdentifier, 6 ,2);
					$sDateLine = '<dcvalue element="date" qualifier="issued">';
					$sDateLine .= $sDateIssued;
					$sDateLine .= '</dcvalue>';
					fwrite($fh, $sDateLine);
		
    
					$sVendorLines = '<dcvalue element="vendor" qualifier="none">Digitalisering UBU</dcvalue>';
					$sVendorLines .= '<dcvalue element="contributor" qualifier="digitizer">Universiteitsbibliotheek Utrecht</dcvalue>';
					fwrite($fh, $sVendorLines);
                            
					$aFiles = findFiles($sBookDirectory);
                
					//copy tif files
					$aTifFiles = $aFiles['ft'];
					copySourceFiles($sBookDirectory, $sThisItemDestinationDir, $aTifFiles, $bDevelop);
                            
					$aItemFiles = $oFandD->getItemFiles($sThisItemDestinationDir);
	                
					$sDateTime = date('Y-m-d') . 'T' . date('H:i:s') . 'Z';
                         
					//create file listing (also in dublin_core.xml)
					$sSubmitLine = makeFileDescriptions($aItemFiles, $sDateTime, 'submit', $sBijzCollSubmitter);
					$sApproveLine = makeFileDescriptions($aItemFiles, $sDateTime, 'approve', $sBijzCollSubmitter);
					$sFileBlock = makeFormatElements($aItemFiles);
                   
					fwrite($fh, $sSubmitLine);
					fwrite($fh, $sApproveLine);
					fwrite($fh, $sFileBlock);
         
					fwrite($fh, "</dublin_core>\n");
					fclose($fh);

					$sContentsFile = $sThisItemDestinationDir . 'contents';
					$sContentsBlock = makeContentsBlock($aItemFiles);
					$fc = fopen($sContentsFile, "w");
					fwrite($fc, $sContentsBlock);
					fclose($fc);
                    
					//update total import size
					$aSizeData = $oFandD->getTotalFileSize($sThisItemDestinationDir);
					if (isset($aSizeData['error'])) {
						$aErrorsToMail[] = $aSizeData['error'];
					}
					else {
						$nTotal = $aSizeData['totalsize'];
						$nSizeImported += $nTotal;
					}
				} // end of "if there is enough space for this directory"
				else {
					$line = 'not enough room left in the assetstore ' . $sCurrentAssetStore;
					wlog($line, 'INF');
				}
			}
		}
	}
	if ($bVerbose == 1) {
		$line = 'total import size: ' . $nSizeImported . ' bytes';
		wlog($line, 'INF');
    }

	/**
	* All new books are waiting in the import directory
	* Now run the import
	*/
	$aDoImportResult = doImport($sTodaysImportDir, $nCollectionId, $sBijzCollImporter, $bDoImport);
	wlog('Imported ' . $nCollectionId, 'INF');
	//echo 'Imported ' . $nCollectionId . "\n";
}
else {
	$sLine = 'No qc scans today';
	wlog($sLine, 'INF');
}

//remove sip subdirs that are more than x days old
//mode: test=report only; do=really delete
//$sDelMode = 'test';
//if (ENVIRONMENT == 'prod') {
    $sDelMode = 'do';
//}
$nDelDays = 10;
foreach ($aDoneBooks as $sBookId) {
    $sDirToClean = $sSourceDir . $sBookId;
    //$sLine = 'cleaning ' . $sDirToClean;
    //wlog($sLine, 'INF');
    //check if $sDirToClean exists
    if (file_exists($sDirToClean)) {
        $aClean = $oFandD->cleanDirectory($sDirToClean, $nDelDays, $sDelMode);
        if (isset($aClean['error'])) {
            wlog($aClean['error'], 'INF');
        }
        else {
            $sCleanResult = $aClean['result'];
            if ($sCleanResult == '1') {
                wlog('Bijzcoll import dir ' . $sBookId . ' cleaned up', 'INF');
            }
        }
    }    
}

//cleanup import dir
//mode: test=report only; do=really delete
//$sCleanUpMode = 'test';
//if ($bDevelop == 0) {
    $sCleanUpMode = 'do';
//}
$nCleanDays = 2;
$aImportClean = cleanImport($sImportDirectory, $nCleanDays, $sCleanUpMode, $oFandD);
foreach ($aImportClean as $aOneClean) {
    if (isset($aOneClean['error'])) {
        wlog($aOneClean['error'], 'INF');
    }
}



//check if there is a dspace.err file; if so, write its contents to the error log
$sTodaysErrorFile = $sTodaysImportDir . 'dspace.err';
if (file_exists($sTodaysErrorFile)) {
    $sMessages = file_get_contents($sTodaysErrorFile);
    wlog($sMessages, 'INF');
}

if (!empty ($aErrorsToMail)) {
    $sErrorMessage = 'An error occurred: ';
    foreach ($aErrorsToMail as $aError) {
        $sErrorMessage .= $aError . "\n";
    }
    sendErrorMails('m.muilwijk@uu.nl', 'DSpace errors', $sErrorMessage);
}


$logend = "================ BIJZCOLL QC END ================\n";
wlog($logend, '');



//================================================================

function showUsage()
{
    echo "usage: bijzcoll_import [-ivh] \n";
     echo "    -i: import items \n";
    echo "    -v: verbose \n";

    exit();
}


function getDiskFree($sCurrentAssetStore)
{
    $oFileDisk = new FileAndDisk($sCurrentAssetStore);
    $nResponse = $oFileDisk->getFreeDiskSpace($sCurrentAssetStore);
    
    return $nResponse;
 }

function checkDirectorySize($sCurrentAssetStore, $nSizeImported)
{
    $nDiscFree = disk_free_space($sCurrentAssetStore);
    $nFreeToUse = $nDiscFree - DISK_MARGIN - $nSizeImported;
    
    $sCanBeImported = '';
    if ($nFreeToUse > 0) {
        $sCanBeImported = 'y';
    }
    
    return $sCanBeImported;
}

