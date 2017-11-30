<?php
require_once 'import_init.php';

require_once (CLASSES . 'Handle.php');

$sVendor = 'sabine';
$sSourceDirectory = SIPBASE . 'sabine/';
$sImportDirectory = IMPORTBASE . 'sabine/';
$sSabineSubmitter = 'sabine';
$sSabineImporter = IMPORTER;

$aSabineToDspaceFields = array(
    'recid' => array('element' => 'identifier', 'qualifier' => 'other'),
    'datm01' => array('element' => 'title', 'qualifier' => 'none'),
    'dat03' => array('element' => 'contributor', 'qualifier' => 'author'),
    'dat02' => array('element' => 'type', 'qualifier' => 'content'),
    'dat04' => array('element' => 'rights', 'qualifier' => 'placeofpublication'),
    'dat05' => array('element' => 'publisher', 'qualifier' => 'none'),
    'dat07' => array('element' => 'date', 'qualifier' => 'issued'),
    'dat12' => array('element' => 'relation', 'qualifier' => 'ispartofseries'),
    'datm02' => array('element' => 'relation', 'qualifier' => 'ispartofseries'),
    'datm03' => array('element' => 'description', 'qualifier' => 'abstract'),
    'dat14' => array('element' => 'subject', 'qualifier' => 'keywords'),
    'dat15' => array('element' => 'coverage', 'qualifier' => 'spatial'),
    'datm04' => array('element' => 'description', 'qualifier' => 'note'),
);

$aSabineToTypeContent = array(
    'ART' => 'Article',
    'MON' => 'Book',
    'RAP' => 'Report',
    'SCR' => 'Student thesis',
    'SER' => 'Article',
);

//echo 'SABINE import start ' . date('Ymd') . "\n";

$bDoImport = 0;
$bDevelop = 0;
$bVerbose = 0;
//$sShortOpts = "iDhv";
$aOptions = getopt("iDhv");

if (isset($aOptions['i'])) {
    $bDoImport = 1;
}
if (isset($aOptions['D'])) {
    $bDevelop = 1;
}
if (isset($aOptions['h'])) {
    //showUsage();
}
if (isset($aOptions['v'])) {
    $bVerbose = 1;
}
if(empty ($aOptions)) {
    //showUsage();
}

$aErrorsToMail = array();
// Get license text
$sLicenseTextFile = './license.txt';
$sVendorTable = 'ubuaux_vendor_recs';
$oFandD = new FileAndDisk();
$oAux = new AuxTables();

$aDoneItems = array();

$sTodaysImportDir = $sImportDirectory . date('Ymd') . '/';

//get sabine collection id
//hard coded, because it won't change anymore
$nCollectionId = 200;


$sCollectionDestDir = $sTodaysImportDir . $nCollectionId . '/';


$logstart = "================ SABINE START ================\n";
wlog($logstart, '');


//get records from sabine database
$aSabineRecords = getSabineRecords();

if (isset($aSabineRecords['error'])) {
    wlog($aSabineRecords['error'], 'ERR');
}
else {
    $oHandle = new Handle();
    
    if (!empty($aSabineRecords)) {
		
		if (!is_dir($sTodaysImportDir)) {
			mkdir($sTodaysImportDir);
		}
		if (!is_dir($sCollectionDestDir)) {
			mkdir($sCollectionDestDir);
		}
		
        foreach ($aSabineRecords as $aOneRecord) {
            $sSabineId = $aOneRecord['recid'];
			
		    if ($bVerbose == 1) {
                $line = 'looking at sabine id ' . $sSabineId;
                wlog($line, 'INF');
            }
            
            
            //check if it is in dspace
            $aVendorItemData = $oAux->checkVendorItem($sVendorTable, $sVendor, $sSabineId);
			//print_r($aVendorItemData);
            if (isset($aVendorItemData['data']) && $aVendorItemData['data']['mdstatus'] == 'a') {
                //update sabine database: set urlfulltext field to item page url
                $nItemId = $aVendorItemData['data']['item_id'];
                $sHandle = $oHandle->getHandle($nItemId);
                $sFullTextUrl = BASEURL . '/handle/1874/' . $sHandle['handleid'];
                
                $sLine = 'I will now try the link ' . $sFullTextUrl . 'for ' . $sSabineId;
				//echo $sLine . "\n";
                wlog($sLine, 'INF');
          
				$aSetResult = setFullTextLink($sSabineId, $sFullTextUrl, $bDevelop);
                if (isset($aSetResult['error'])) {
                    $line = $aSetResult['error'];
                    wlog($line, 'INF');
                }
                elseif (isset($aSetResult['linked'])) {
                    if ($bVerbose == 1) {
                        $line = $aSetResult['linked'];
                        wlog($line, 'INF');
                    }
                    $aDoneItems[] = $sSabineId;
                }
                else {
                    $line = 'Tried to set link for ' . $sSabineId;
                    wlog($line, 'INF');
                }
            }
            else {
				//exit();
                $line = 'Importing ' . $sSabineId;
                wlog($line, 'INF');
				//echo $line . "\n";
                $sItemDirectory = $sSourceDirectory . $sSabineId . '/';
               
                //check if itemdirectory exists
                if (!is_dir($sItemDirectory)) {
                   $sLine = $sItemDirectory . ' does not exist; cannot import';
                   wlog($sLine, 'INF');
				   //echo $sLine . "\n";
                }
                else {
                    //create import subdirectory
                    $sThisItemDestinationDir = $sCollectionDestDir .  'sab_' . $sSabineId . '/';
                    if (!is_dir($sThisItemDestinationDir)) {
                        mkdir($sThisItemDestinationDir);
                    }
                    
                    $aFiles = findFiles($sItemDirectory);
                    $aFullText = $aFiles['ft'];
                    copySourceFiles($sItemDirectory, $sThisItemDestinationDir, $aFullText, $bDevelop);
            
                    //copy license
                    $sLicenseDest = $sThisItemDestinationDir . 'license.txt';
                    copy($sLicenseTextFile, $sLicenseDest);
            
                    //create dublin_core.xml
                    $sDublinCoreFile = $sThisItemDestinationDir . 'dublin_core.xml';
                    $fh = fopen($sDublinCoreFile, "w");
                    fwrite($fh, "<dublin_core>\n");
            
                    $sLine = '';
                    foreach ($aSabineToDspaceFields as $sOrgField => $aOneField) {
                        $sValue = $aOneRecord[$sOrgField];
						
						//echo 'orgfield ' . $sOrgField . ' becomes ';
						//print_r($aOneField);
						//echo 'value ' . $sValue;
						//echo "\n";
						
                        if ($sValue != '') {
                            $sElement = $aOneField['element'];
                            $sQualifier = $aOneField['qualifier'];
                        
                            //for keywords and spatial: split value on ;
                            if (($sElement == 'subject' && $sQualifier == 'keywords') || ($sElement == 'coverage' && $sQualifier == 'spatial')) {
                                $aAllValues = explode(';', $sValue);
                                foreach ($aAllValues as $sOneValue) {
                                    //$sAmpValue = preg_replace('/ & /', ' &amp; ', $sOneValue);
                                    //$sUseValue = utf8_encode($sAmpValue);
                                    $sUseValue = makeSabineSafeString($sOneValue);
                                    $sLine .= '<dcvalue element="' . $sElement . '" qualifier="' . $sQualifier . '">';
                                    $sLine .= $sUseValue;
                                    $sLine .= '</dcvalue>';
                                    $sLine .= "\n";
                                }
                            }
                            elseif ($sElement == 'contributor' && $sQualifier == 'author') {
                                //split authors on /
                                $aAllValues = explode('/', $sValue);
                                foreach ($aAllValues as $sOneValue) {
                                    $sUseValue = makeSabineSafeString($sOneValue);
                                    //$sUseValue = makeSafeString($sOneValue);
                                    $sLine .= '<dcvalue element="' . $sElement . '" qualifier="' . $sQualifier . '">';
                                    $sLine .= $sUseValue;
                                    $sLine .= '</dcvalue>';
                                    $sLine .= "\n";
                                }
                            }
                            else {
                                $sUseValue = '';
                                //type content mapping
                                if ($sElement == 'type' && $sQualifier == 'content') {
                                    $sMappedValue = $aSabineToTypeContent[$sValue];
                                    $sUseValue = makeSabineSafeString($sMappedValue);
                                    //$sUseValue = makeSafeString($sMappedValue);
                                }
                                else {
                                    $sUseValue = makeSabineSafeString($sValue);
                                    //$sUseValue = makeSafeString($sValue);
                                }
                                
                                $sLine .= '<dcvalue element="' . $sElement . '" qualifier="' . $sQualifier . '">';
                                $sLine .= $sUseValue;
                                $sLine .= '</dcvalue>';
                                $sLine .= "\n";
                            }
                        }
                    } //end of for each field
                    $sLine .= '<dcvalue element="vendor" qualifier="none">' . $sVendor . '</dcvalue>' . "\n";
                    fwrite($fh, $sLine);
					//echo $sLine;

                    $aItemFiles = $oFandD->getItemFiles($sThisItemDestinationDir);
                        //$nCount = count($aItemFiles);
                        //echo "found $nCount itemfiles \n";
                    
                    $sDateTime = date('Y-m-d') . 'T' . date('H:i:s') . 'Z';
                    //create file listing (also in dublin_core.xml)
                    $sSubmitLine = makeFileDescriptions($aItemFiles, $sDateTime, 'submit', $sSabineSubmitter);
                    $sApproveLine = makeFileDescriptions($aItemFiles, $sDateTime, 'approve', $sSabineSubmitter);
                    //$sAvailLine = makeFileDescriptions($aItemFiles, $sDateTime, 'avail', $sSabineSubmitter);
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

                } //end of "does the original item directory exist"
            } //end of "not in dspace"
        } //end of "for each sabine record"
		
		//run the import
		$aImportResult = doImport($sTodaysImportDir, $nCollectionId, $sSabineImporter, $bDoImport);
		if (isset($aImportResult['error'])) {
			wlog($aImportResult['error'], 'INF');
			$aErrorsToMail[] = $aImportResult['error'];
		}
		else {
			//print_r($aImportResult);
		}
		
		//copy dspace.err to log
		$sTodaysErrorFile = $sTodaysImportDir . 'dspace.err';
		if (file_exists($sTodaysErrorFile)) {
			$sMessages = file_get_contents($sTodaysErrorFile);
			wlog($sMessages, 'INF');
		}
    } // end of "if sabine records found"
	else {
		$sLine = 'No Sabine records found today';
		wlog($sLine, 'INF');
	}
} //end of "fetch sabine records









//clean up source dirs with imported items after a few days
//$sDelMode = 'test';
//if (ENVIRONMENT == 'prod') {
    $sDelMode = 'do';
//}
$nDelDays = 2;
foreach ($aDoneItems as $sItemId) {
    $sDirToClean = $sSourceDirectory . $sItemId;
    //$sLine = 'cleaning ' . $sDirToClean;
    //wlog($sLine, 'INF');
    $aClean = $oFandD->cleanDirectory($sDirToClean, $nDelDays, $sDelMode);
    if (isset($aClean['error'])) {
        wlog($aClean['error'], 'INF');
    }
    else {
        $sCleanResult = $aClean['result'];
        if ($sCleanResult == '1') {
            wlog('Sabine import dir ' . $sItemId . ' cleaned up', 'INF');
        }
    }
}

//cleanup import dirs
//mode: test=report only; do=really delete
//$sCleanUpMode = 'test';
//if ($bDevelop == 0) {
    $sCleanUpMode = 'do';
//}
$nDays = 10;
$aImportClean = cleanImport($sImportDirectory, $nDays, $sCleanUpMode, $oFandD);
foreach ($aImportClean as $aOneClean) {
    if (isset($aOneClean['error'])) {
        wlog($aOneClean['error'], 'INF');
    }
    else {
        wlog('Sabine import dirs cleaned up', 'INF');
    }
}

//mail any errors
if (!empty ($aErrorsToMail)) {
    $sErrorMessage = 'An error occurred: ';
    foreach ($aErrorsToMail as $aError) {
        $sErrorMessage .= $aError . "\n";
    }
    sendErrorMails('m.muilwijk@uu.nl', 'DSpace errors', $sErrorMessage);
}

$logend = "================ SABINE END ================\n";
wlog($logend, '');

function getSabineRecords()
{
	$sServiceUrl = SABINESERVICE . '/recentrecords';
	$sFound = file_get_contents($sServiceUrl);
	$aSabineRecords = json_decode($sFound, true);
	return $aSabineRecords;
}

function setFullTextLink($nRecid, $sLink, $bDebug)
{
	$aResults = array();
	$sProcessUrl = SABINESERVICE . '/fulltextlink';
	
	$data_array = array('nRecid' => $nRecid, 'sLink' => $sLink, 'sGivenKey' => SABINEKEY,  'bDebug' => $bDebug);
	$data = http_build_query($data_array);
    $params = array('http' => array('method' => 'POST', 'content' => $data, 'header'  => 'Content-type: application/x-www-form-urlencoded'));
    $ctx = stream_context_create($params);

    try {
		$fp = fopen($sProcessUrl, 'rb', false, $ctx);
		$result = stream_get_contents($fp);
    }
    catch (Exception $e) {
        $result = 'post went wrong: ' . $e->getMessage();
   }
	$aResults['url'] = $sProcessUrl;
	$aResults['result'] = $result;

	return $aResults;
}

 function makeSabineSafeString($sValue) {
    $aControlChars = array(
        "\x01" => ' ',
        "\x02" => ' ',
        "\x03" => ' ',
        "\x04" => ' ',
        "\x05" => ' ',
        "\x06" => ' ',
        "\x07" => ' ',
        "\x0B" => ' ',
        "\x0C" => ' ',
        "\x0E" => ' ',
        "\x14" => ' ',
        "\x19" => ' ',
    );
        
    
        $oEncoding = new FixEncoding();
        $sFixedString = $oEncoding->toUTF8($sValue);
        $sUseValue = $oEncoding->fixUTF8($sFixedString);            

    $sControlledString = strtr($sUseValue, $aControlChars);
    $sAmpValue = preg_replace('/&/', '&amp;', $sControlledString);
    $sLessValue = preg_replace('/</', '&lt;', $sAmpValue);
    $sMoreValue = preg_replace('/>/', '&gt;', $sLessValue);
    
    return $sMoreValue;
}






?>
