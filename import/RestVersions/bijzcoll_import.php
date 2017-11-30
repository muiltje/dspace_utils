<?php
require_once 'import_init.php';

require_once CLASSES . 'DigitizedExport.php';

$sBijzCollSubmitter = 'm.muilwijk@library.uu.nl';
$sBijzCollImporter = 'm.muilwijk@library.uu.nl';


$sBijzcollSIP = SIPBASE . 'bijzcoll/boeken/';
$sBijzcollImport = IMPORTBASE . 'bijzcoll/';
$sMetamorfozeSIP = SIPBASE . 'bijzcoll/bnb/';
$sMetamorfozeImport = IMPORTBASE . 'bnb/';
/*
$aTypeContentToCollection = array(
    'Book' => array('commname' => 'Digitized Objects', 'collname' => 'Digitized Books and Journals'),
    'Journal Volume' => array('commname' => 'Digitized Objects', 'collname' => 'Digitized Books and Journals'), 
    'Map' => array('commname' => 'Digitized Objects', 'collname' => 'Digitized Maps'),
    'Manuscript' => array('commname' => 'Digitized Objects', 'collname' => 'Digitized Manuscripts'),
    'Maculature' => array('commname' => 'Digitized Objects', 'collname' => 'Digitized Manuscripts'),
    'Archival records' => array('commname' => 'Digitized Objects', 'collname' => 'Digitized Manuscripts'),
    'Lecture notes' => array('commname' => 'Digitized Objects', 'collname' => 'Digitized Manuscripts'),
    'Sheet music' => array('commname' => 'Digitized Objects', 'collname' => 'Digitized Sheet music'),
    'Pamphlet' => array('commname' => 'Digitized Objects', 'collname' => 'Digitized Pamphlets'),
    'Photograph' => array('commname' => 'Digitized Objects', 'collname' => 'Digitized or digital born Images'),
    '3-D object' => array('commname' => 'Digitized Objects', 'collname' => 'Digitized or digital born Images'),
    'Video' => array('commname' => 'Digitized Objects', 'collname' => 'Digitized or digital born Video'),
    'Audio' => array('commname' => 'Digitized Objects', 'collname' => 'Digitized or digital born Audio'),
);
 * 
 */

//$aCollections[$sTypeContent] = $nCollectionId;
$aCollections = array(
	'Book' => 257,
	'Journal Volume' => 257,
	'Map' => 259,
	'Manuscript' => 258 ,
	'Maculature' => 258,
	'Archival records' => 258,
	'Lecture notes' => 258,
	'Sheet music' => 261,
    'Pamphlet' => 260,
    'Photograph' => 263,
    '3-D object' => 263,
    'Video' => 264,
    'Audio' => 262,
);



//for which DSpace collections do we have items today
$aCollectionsSeen = array();
// Get license text
$sLicenseTextFile = './license.txt';
//array to catch errors
$aErrorsToMail = array();


$sSourceDir = '';
$sImportDirectory = '';
$bIsMetamorfoze = 0;
$bDoImport = 0;
$bVerbose = 0;
$bDevelop = 0;
$bCheckDiskFree = 0;

$sShortOpts = "MivDdh";
$aLongOpts = array('m::',);
$aOptions = getopt($sShortOpts, $aLongOpts);

if (isset($aOptions['M'])) {
    $sSourceDir = $sMetamorfozeSIP;
    $sImportDirectory = $sMetamorfozeImport;
    $bIsMetamorfoze = 1;
}
else {
    $sSourceDir = $sBijzcollSIP;
    $sImportDirectory = $sBijzcollImport;
}

if (isset($aOptions['i'])) {
    $bDoImport = 1;
}
if (isset($aOptions['v'])) {
    $bVerbose = 1;
}
if (isset($aOptions['D'])) {
    $bDevelop = 1;
}
if (isset($aOptions['d'])) {
    $bCheckDiskFree = 1;
}
if (isset($aOptions['h'])) {
    showUsage();
}
if(empty ($aOptions)) {
    showUsage();
}

//maximum size we can process
$nMaxProcessSize = MAX_TSIZE;
$matches = array();

if (isset($aOptions['m']) && $aOptions['m'] != '') {
    if (is_int($aOptions['m'])) {  
        $nMaxProcessSize = $aOptions['m']*10;
    }
    elseif (preg_match('/(\d{1,3})([mg])/', $aOptions['m'], $matches)) {
        if ($matches[2] == 'm') {
            $nMaxProcessSize = $matches[1] * 1000;
        }
        elseif ($matches[2] == 'g') {
            $nMaxProcessSize = $matches[1] * 1000000;
        }
    }
}

if ($bCheckDiskFree == 1) {
    $sCurrentAssetStore = getAssetStore();
    $sDiskFree = getDiskFree($sCurrentAssetStore);
    
    echo 'on ' . $sCurrentAssetStore . ' we have ' . $sDiskFree . ' available ' . "\n";
    exit();
}

if ($bVerbose == 1) {
    //echo 'Your max size today is ' . $nMaxProcessSize . "\n";
}

$oAux = new AuxTables();
$oFanD = new FileAndDisk();

$sTodaysImportDir = $sImportDirectory . date('Ymd') . '/';
if (!@opendir($sTodaysImportDir)) {
    mkdir($sTodaysImportDir);
}

$logstart = '';
if ($bIsMetamorfoze === 1) {
    $logstart .= "================ BNB BIJZCOLL START ================\n";
}
else {
    $logstart .= "================ BIJZCOLL START ================\n";
}
$logstart .= date('Ymd');
wlog($logstart, '');
echo 'Bijzcoll start ' . date('Ymd') . "\n";


/**
 * Get collection-ids for the various Digitized Objects collections
 */
/*
$sCommunityName='Digitized Objects';
$aCollections = array();
foreach ($aTypeContentToCollection as $sTypeContent=>$aTypeContentData) {
    $sCommunityName = $aTypeContentData['commname'];
    $sCollectionName = $aTypeContentData['collname'];
    $aCollectionData = $oAux->getCollectionId($sCommunityName, $sCollectionName);
    
    if (isset($aCollectionData['error'])) {
        $aErrorsToMail[] = $aCollectionData['error'];
    }
    else {
        $nCollectionId = $aCollectionData['collectionid'];
        $aCollections[$sTypeContent] = $nCollectionId;
    }
}
 * 
 */
$aNewBooks = array();
$aDoneBooks = array();

//what do we have in BOOK_SIP
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
    $line = count($aNewBooks) . ' new items found';
    wlog($line, 'INF');
}

/**
 * For all new books
 *  check if the item is in dspace.
 *      if yes, rename dir to _dir
 *  Get metadata
 *  Create import directory
 *  Write dublin_core.xml in import directory
 *  Copy files to import directory
 *  Create file listing in import directory
 *  Copy license to import directory
 *  Bookkeeping: keep track of how many GB we have processed
 */
$sVendor = 'Digitalisering UBU';
$sVendorTable = 'ubuaux_vendor_recs';
$sDigWorkflowService = DIGWORKFLOW;
$oDigWf = new DigWorkflow($sDigWorkflowService);
$oFandD = new FileAndDisk();

$nSizeImported = 0;

foreach ($aNewBooks as $sIdentifier) {
    $sBookDirectory = $sSourceDir . $sIdentifier . '/';
    echo 'doing ' . $sBookDirectory . "\n";
    
    //is it in DSpace
    $aVendorItemData = $oAux->checkVendorItem($sVendorTable, $sVendor, $sIdentifier);
    
	
    if (isset($aVendorItemData['error'])) {
        wlog($aVendorItemData['error'], 'INF');
        $aErrorsToMail[] = $aVendorItemData['error'];
    }
    else {
		if (isset($aVendorItemData['data'])) {
            if ($aVendorItemData['data']['mdstatus'] == 'a') {
                if ($bVerbose == 1) {
                    $line = $sIdentifier . ' has already been archived ';
                    wlog($line, 'INF');
                    
                }
                $sNewDirName = $sSourceDir . '_' . $sIdentifier;
                if ($bVerbose == 1) {
                    $line = 'renaming ' . $sBookDirectory . ' to ' . $sNewDirName;
                    wlog($line, 'INF');
                }
                if (ENVIRONMENT == 'prod') {
                    //do the actual marking
                    @rename($sBookDirectory, $sNewDirName);
                }
            }
        }
        else {
            if ($nSizeImported < $nMaxProcessSize) {
                if ($bVerbose == 1) {
                    wlog('Processing ' . $sIdentifier, 'INF');
                }
                
                //check to make sure there is still room
                $sCurrentAssetStore = getAssetStore();
                $sCanBeImported = checkDirectorySize($sCurrentAssetStore, $nSizeImported);
                
                if ($sCanBeImported == 'y') {
                    //get metadata
                    $aFoundMetadata = $oDigWf->getMetadata($sIdentifier);
                    
                    if (isset($aFoundMetadata['error'])) {
                        echo $aFoundMetadata['error'];
                        $aErrorsToMail[] = $aFoundMetadata['error'];
                    }
                    else {
                        $aMetadata = $aFoundMetadata['metadata'];
						//print_r($aMetadata);
                
                        $sSequenceString = $aMetadata['sequence'];
            
                        //check if all tif files are present
                        $aMissing = parseSequence($sSequenceString, $sBookDirectory);
                
                        if (!empty ($aMissing)) {
                            if ($bVerbose == 1) {
                                $line = 'Missing ' . count($aMissing) . ' tifs for book ' . $sIdentifier . "\n";
                                foreach ($aMissing as $sMissingTif) {
                                    $line .= $sMissingTif . ' - ';
                                }
                                wlog($line, 'INF');
                            }
                            //$aErrorsToMail[] = $line;
                            mailMissing($aMissing);
                         }
                        else {
                            //get collectionid
                            $sTypeContentFound = $aMetadata['type_content'];
							
							//Aleph has some unusual doctypes and most of them are some form of manuscript material
							//so default to manuscript
							$nDestinationCollection = $aCollections['Manuscript'];
							if (isset($aCollections[$sTypeContentFound])) {
								$nDestinationCollection = $aCollections[$sTypeContentFound];
							}
                            $sCollectionDestDir = $sTodaysImportDir . $nDestinationCollection . '/';
                            if (!is_dir($sCollectionDestDir)) {
                                mkdir($sCollectionDestDir);
                            }
                            //remember this collection
                            $aCollectionsSeen[] = $nDestinationCollection;

                            //create import subdirectory
                            $sThisItemDestinationDir = $sCollectionDestDir .  'bijzcoll_' . $sIdentifier . '/';
                            if (!is_dir($sThisItemDestinationDir)) {
                                mkdir($sThisItemDestinationDir);
                            }
            
                            //create dublin_core.xml
                            $sDublinCoreFile = $sThisItemDestinationDir . 'dublin_core.xml';
                            $fh = fopen($sDublinCoreFile, "w");
                            fwrite($fh, "<dublin_core>\n");
                    
                            $sIdLines = '<dcvalue element="identifier" qualifier="other">' . $sIdentifier . '</dcvalue>' . "\n";
                            $sIdLines .= '<dcvalue element="identifier" qualifier="digitization">' . $sIdentifier . '</dcvalue>' . "\n";
                            fwrite($fh, $sIdLines);
                            
                            foreach ($aMetadata as $sFieldName=>$sFieldValue) {
                                if ($sFieldValue != '') {
                                    

                                    $sUseValue = makeSafeString($sFieldValue);
                                    
                                    $sElement = '';
                                    $sQualifier = '';
                            
                                    $sPos = strpos($sFieldName, '_');
                                    if ($sPos) {
                                        $sElement = substr($sFieldName, 0, $sPos);
                                        $sQualifier = substr($sFieldName, $sPos+1);
                                    }
                                    else {
                                        $sElement = $sFieldName;
                                        $sQualifier = 'none';
                                    }
                                    $sLine = '<dcvalue';
                                    $sLine .= ' element="' . $sElement . '"';
                                    $sLine .= ' qualifier="' . $sQualifier . '"';
                                    $sLine .= '>';
                                    $sLine .= $sUseValue;
                                    $sLine .= '</dcvalue>';
                                    $sLine .= "\n";
                            
                                    fwrite($fh, $sLine);
                                }
                            }
							
							//see if there is any quality control data in the webservice
							$aQcData = $oDigWf->getQCData($sIdentifier);
							if ($aQcData['filenames'] != '') {
								$sQcLine = '<dcvalue element="relation" qualifier="hasqcscan">';
								$sQcLine .= $aQcData['filenames'];
								$sQcLine .= '</dcvalue>';
								$sQcLine .= "\n";
								fwrite($fh, $sQcLine);
							}
							
							
                            $sVendorLines = '<dcvalue element="vendor" qualifier="none">Digitalisering UBU</dcvalue>';
                            $sVendorLines .= '<dcvalue element="contributor" qualifier="digitizer">Universiteitsbibliotheek Utrecht</dcvalue>';
                            fwrite($fh, $sVendorLines);
                            
                            $aFiles = findFiles($sBookDirectory);
                            
                            //send htm files to the manifestation store
                            if (!empty ($aFiles['htms'])) {
                                processHtms($aFiles['htms'], $sIdentifier, $sBookDirectory, $bDevelop);
                            }
                            //copy tif files
                            $aTifFiles = $aFiles['ft'];
                            copySourceFiles($sBookDirectory, $sThisItemDestinationDir, $aTifFiles, $bDevelop);
                            
                            //copy license
                            $sLicenseDest = $sThisItemDestinationDir . 'license.txt';
                            copy($sLicenseTextFile, $sLicenseDest);
                    
                            $aItemFiles = $oFandD->getItemFiles($sThisItemDestinationDir);
                    
                            $sDateTime = date('Y-m-d') . 'T' . date('H:i:s') . 'Z';
                            
                            //create file listing (also in dublin_core.xml)
                            $sSubmitLine = makeFileDescriptions($aItemFiles, $sDateTime, 'submit', $sBijzCollSubmitter);
                            $sApproveLine = makeFileDescriptions($aItemFiles, $sDateTime, 'approve', $sBijzCollSubmitter);
                            //$sAvailLine = makeFileDescriptions($aItemFiles, $sDateTime, 'avail', $sBijzCollSubmitter);
                            $sFileBlock = makeFormatElements($aItemFiles);
                    
                            fwrite($fh, $sSubmitLine);
                            fwrite($fh, $sApproveLine);
                            //fwrite($fh, $sAvailLine);
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
                            
                            
            
                        } //end of "if not tifs are missing"
                    } // end of "if metadata have been found"
                } // end of "if there is enough space for this directory"
                else {
                    $line = 'not enough room left in the assetstore ' . $sCurrentAssetStore;
                    wlog($line, 'INF');
                }
            } //end of "if max size hasn't been reached"
            else {
                wlog('max size reached', 'INF');
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
 * You need to pass the collection id to the command, 
 * so do the import per collection_directory
 */
$aImportCollections = array_unique($aCollectionsSeen);

foreach ($aImportCollections as $nDsCollectionId)
{
    $aDoImportResult = doImport($sTodaysImportDir, $nDsCollectionId, $sBijzCollImporter, $bDoImport);
    print_r($aDoImportResult);
    wlog('Imported ' . $nDsCollectionId, 'INF');
}

//remove sip subdirs that are more than x days old
//mode: test=report only; do=really delete
$sDelMode = 'test';
if (ENVIRONMENT == 'prod') {
    $sDelMode = 'do';
}
$nDelDays = 2;
foreach ($aDoneBooks as $sBookId) {
    $sDirToClean = $sSourceDir . $sBookId;
    
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
$sCleanUpMode = 'test';
if ($bDevelop == 0) {
    $sCleanUpMode = 'do';
}
$nCleanDays = 2;
$aImportClean = cleanImport($sImportDirectory, $nCleanDays, $sCleanUpMode, $oFandD);
foreach ($aImportClean as $aOneClean) {
    if (isset($aOneClean['error'])) {
        wlog($aOneClean['error'], 'INF');
    }
}


//cleanup download directory: delete subdirs older than 2 days
$sDownloadDirectory = BCDOWNLOAD;
if (file_exists($sDownloadDirectory)) {
    //cleanImport($sDownloadDirectory, 2, 'do', $oFanD);
    //TODO: find the subdirs whose names start with download_; only clean those
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


$logend = "================ BIJZCOLL END ================\n";
wlog($logend, '');


//================================================================

function showUsage()
{
    echo "usage: bijzcoll_import [-Mimvsdh] \n";
    echo "    -M: import metamorfoze \n";
    echo "    -i: import items \n";
    echo "    -m: max. import size \n";
    echo "    -v: verbose \n";
    echo "    -d: show diskfree \n";

    exit();
}

function parseSequence($sSequenceStringFound, $sBookDirectory) {
    //strip trailing ;
    $sSequenceString = substr($sSequenceStringFound, 0, -1);
    
    $aPages = explode('+', $sSequenceString);
    $aMissingPages = array();
    
    foreach ($aPages as $sPage) {
        $split = explode('%', $sPage);
        
        $sTifName = $split[1];
        
        //check if the tif exists in the book source directory
        $sFile = $sBookDirectory . $sTifName;
        if (file_exists($sFile)) {
        }
        else {
            $aMissingPages[] = $sTifName;
        }
        
    }
    
    return $aMissingPages;
}

function mailMissing($aMissingFiles)
{
    $sFromAddress = NOREPLY;
    $sToAddress = 'm.muilwijk@uu.nl';
    $sSubject = 'Missing Tifs';
    $sHeaders = 'From:' . $sFromAddress . "\r\n";
    
    $sMessage = 'Er ontbreken tifs: ';
    foreach ($aMissingFiles as $sTif) {
        $sMessage .= $sTif . "\n";
    }
    $sMessage .= "\nGroeten van DSpace \n";
    
    mail($sToAddress, $sSubject, $sMessage, $sHeaders);
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

function getDiskFree($sCurrentAssetStore)
{
    $oFileDisk = new FileAndDisk($sCurrentAssetStore);
    $nResponse = $oFileDisk->getFreeDiskSpace($sCurrentAssetStore);
    
    return $nResponse;
 }



function processHtms($aHtmFiles, $sIdentifier, $sSourceDirectory, $bDev)
{
    $aResult = array();
    
    $sManifestationUrl = MANIFESTATION_STORE;
    
    $oDigExp = new DigitizedExport();
    
    try {
        foreach ($aHtmFiles as $sFile) {
            if ($bDev == 1) {
                $aResult[] = 'sending ' . $sFile;
            }
            else {
                $sFileToSend = $sSourceDirectory . $sFile;
                $oDigExp->sendHtm($sFileToSend, $sIdentifier, $sManifestationUrl);
                $aResult[] = 'htm ' . $sFile . ' sent';
            }
        }
    }
    catch (Exception $e) {
        $aResult['error'] = 'could not send htm to manifestation store: ' . $e->getMessage();
    }
    
    return $aResult;
}


