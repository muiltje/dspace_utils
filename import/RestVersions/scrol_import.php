<?php
require_once 'import_init.php';

$sVendor = 'Scrol2';

$sSourceDirectory = SIPBASE . 'scrol/';

$sImportDirectory = IMPORTBASE . 'scrol/';
$sScrolSubmitter = 'scrol';
$sScrolImporter = IMPORTER;

$aScrolFields = array(
    'ds_title' => array('element'=>'title', 'qualifier' => 'none'),
    'sc_thesis_keywords' => array('element'=>'subject', 'qualifier' => 'keywords'),
    'sc_thesis_abstract' => array('element'=>'description', 'qualifier' => 'abstract'),
    'sc_thesis_language' => array('element'=>'language', 'qualifier' => 'iso'),
    'sc_thesis_type' => array('element'=>'type', 'qualifier' => 'content'),
    'sc_rights_accessrights' => array('element'=>'rights', 'qualifier' => 'accessrights'),
    'sc_thesis_date_embargo' => array('element'=>'date', 'qualifier' => 'embargo'),
    'ds_contributor_author' => array('element'=>'contributor', 'qualifier' => 'author'),
    'ds_contributor_advisor' => array('element'=>'contributor', 'qualifier' => 'advisor'),
    'ds_studentnummer' => array('element'=>'studentnumber', 'qualifier' => 'none'),
    'ds_courseuu' => array('element'=>'subject', 'qualifier' => 'courseuu'),
    'sc_discipline' => array('element'=>'subject', 'qualifier' => 'discipline'),
	'sc_publication_year' => array('element' => 'date', 'qualifier' => 'issued'),
);

echo 'SCROL import start ' . date('Ymd') . "\n";

$bDoImport = 0;
//$bDevelop = 0;
$bVerbose = 0;
$sDevelop = 'no';

//$sShortOpts = "iDhv";
$aOptions = getopt("iDhv");

if (isset($aOptions['i'])) {
    $bDoImport = 1;
}
if (isset($aOptions['D'])) {
    $sDevelop = 'yes';
}
if (isset($aOptions['v'])) {
    $bVerbose = 1;
}
if (isset($aOptions['h'])) {
    //showUsage();
}
if(empty ($aOptions)) {
    //showUsage();
}

$aErrorsToMail = array();
$aCollectionsSeen = array();

// Get license text
$sLicenseTextFile = './license.txt';
$sVendorTable = 'ubuaux_vendor_recs';
$oFandD = new FileAndDisk();
$oAux = new AuxTables();

$aDoneItems = array('1464263842707');

$sTodaysImportDir = $sImportDirectory . date('Ymd') . '/';
if (!is_dir($sTodaysImportDir)) {
    mkdir($sTodaysImportDir);
}

$logstart = "================ SCROL START ================\n";
wlog($logstart, '');


 //get data from scrol database
$aScrolData = getScrolData();

if (isset($aScrolData['error'])) {
    wlog($aScrolData['error'], 'ERR');
}
else {
    //$oAux->makeVendorsTable();
    if (empty($aScrolData)) {
        wlog('No new data for Scrol', 'INF');
    }
    else {
        foreach ($aScrolData as $aOneRecord) {
            $sScrolId = $aOneRecord['scrol_id'];
			echo $sScrolId . "\n";
            $aVendorItemData = $oAux->checkVendorItem($sVendorTable, $sVendor, $sScrolId);
			//print_r($aVendorItemData);

            if (isset($aVendorItemData['data']) && $aVendorItemData['data']['mdstatus'] == 'a') {
                if ($bVerbose == 1) {
                    $line = 'moving ' . $sScrolId . ' to bak table';
					//echo $line . "\n";
                    wlog($line, 'INF');
                }
				//$aMoveResult = moveRecordToBak($sScrolId, $bDevelop);
				$aMoveResult = setRecordAsImported($sScrolId, $sDevelop);
				print_r($aMoveResult);
                if (isset($aMoveResult['error'])) {
                        wlog($aMoveResult['error'], 'INF');
                    }
                    else {
                        $aDoneItems[] = $sScrolId;
                    }
            }
            else {
                $sFaculty = $aOneRecord['faculty'];
                //get collection id based on faculty
                $aCollectionData = $oAux->getScrolCollection($sFaculty);
                if (isset($aCollectionData['error'])) {
                    wlog($aCollectionData['error'], 'INF');
                }
                elseif (!isset($aCollectionData['collection_id'])) {
                    wlog('could not find collection_id for ' . $sFaculty, 'INF');
                }
                elseif ($aCollectionData['collection_id'] == '') {
                    wlog('could not find collection_id for ' . $sFaculty, 'INF');
                }
                else {
                    $nCollectionId = $aCollectionData['collection_id'];
                    $aCollectionsSeen[] = $nCollectionId;

                   //get file names and paths
                    $sItemDirectory = $sSourceDirectory . $sScrolId . '/';
                    //cho $sItemDirectory . "\n";

                    //check if itemdirectory exists
                    if (!is_dir($sItemDirectory)) {
                        echo $sItemDirectory . ' does not exist' . "\n";
						wlog($sItemDirectory . ' does not exist', 'INF');
                    }
                    else {
                        //make import dirs
                        $sCollectionDestDir = $sTodaysImportDir . $nCollectionId . '/';
						echo 'trying ' . $sCollectionDestDir . "\n";
                        if (!is_dir($sCollectionDestDir)) {
                            echo 'creating ' . $sCollectionDestDir . "\n";
							mkdir($sCollectionDestDir);
                        }
                        $sThisItemDestinationDir = $sCollectionDestDir .  'sc_' . $sScrolId . '/';
						echo 'trying ' . $sThisItemDestinationDir . "\n";
                        if (!is_dir($sThisItemDestinationDir)) {
							echo 'creating ' . $sThisItemDestinationDir . "\n";
                            mkdir($sThisItemDestinationDir);
                        }

                        $aFiles = findFiles($sItemDirectory);
                        echo count($aFiles) . ' files' . "\n";

                        //copy files
                        $aCopyResult = copyScrolSourceFiles($aFiles, $sItemDirectory, $sThisItemDestinationDir);


                        if (isset($aCopyResult['error'])) {
                            foreach ($aCopyResult['error'] as $sOneCopyError) {
                                wlog($sOneCopyError, 'INF');
                            }
                        }
                        else {
                            //copy license
                            $sLicenseDest = $sThisItemDestinationDir . 'license.txt';
                            copy($sLicenseTextFile, $sLicenseDest);

                            $aItemFiles = $oFandD->getItemFiles($sThisItemDestinationDir);
                            $nCount = count($aItemFiles);
                            if ($bVerbose == 1) {
                                wlog("found $nCount itemfiles", 'INF');
                            }

                            //create dublin_core.xml
                            $sDublinCoreFile = $sThisItemDestinationDir . 'dublin_core.xml';
                            $fh = fopen($sDublinCoreFile, "w");
                            fwrite($fh, "<dublin_core>\n");

                            $sIdLines = '<dcvalue element="identifier" qualifier="other">' . $sScrolId . '</dcvalue>' . "\n";
                            fwrite($fh, $sIdLines);
                            //parse data
                            $sMetadataBlock = createDublinCoreMetadata($aOneRecord, $aScrolFields);
							fwrite($fh, $sMetadataBlock);

							$sFixedLines = '<dcvalue element="vendor" qualifier="none">Scrol2</dcvalue>' . "\n";
                            $sFixedLines .= '<dcvalue element="utrechtposition" qualifier="none">yes</dcvalue>' . "\n";
                            $sFixedLines .= '<dcvalue element="description" qualifier="sponsorship">Universiteit Utrecht</dcvalue>' . "\n";
                            fwrite($fh, $sFixedLines);

							//write our file data
                            $sDateTime = date('Y-m-d') . 'T' . date('H:i:s') . 'Z';

                            //create file listing (also in dublin_core.xml)
                            $sSubmitLine = makeFileDescriptions($aItemFiles, $sDateTime, 'submit', $sScrolSubmitter);
                            $sApproveLine = makeFileDescriptions($aItemFiles, $sDateTime, 'approve', $sScrolSubmitter);
                            //$sAvailLine = makeFileDescriptions($aItemFiles, $sDateTime, 'avail', $sScrolSubmitter);
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

                        } //end of "we have the files in the import directory"
                    } //end of "item directory exists"
                } //end of "we have collection data"
            } //end of "item is not in dspace"
        } // end of "foreach scroldata"

        $aImportCollections = array_unique($aCollectionsSeen);
        //do import
        foreach ($aImportCollections as $nDsCollectionId) {
            try {
//                doImport($sTodaysImportDir, $nDsCollectionId, $sScrolImporter, $bDoImport);
//                wlog('Imported ' . $nCollectionId, 'INF');
            }
            catch (Exception $e) {

            }
        }
    }//end of "is scroldata empty
} //end of "we have data from the scrol webservice"



//clean up source dirs with imported items after a few months
//$sDelMode = 'test';
//if (ENVIRONMENT == 'prod') {
    $sDelMode = 'do';
//}
$nDelDays = 60;
foreach ($aDoneItems as $sItemId) {
    $sDirToClean = $sSourceDirectory . $sItemId;
    $sLine = 'cleaning ' . $sDirToClean;
    wlog($sLine, 'INF');

    $aClean = $oFandD->cleanDirectory($sDirToClean, $nDelDays, $sDelMode);
    if (isset($aClean['error'])) {
        wlog($aClean['error'], 'INF');
    }
    else {
        $sCleanResult = $aClean['result'];
        if ($sCleanResult == '1') {
            wlog('Scrol import dir ' . $sItemId . ' cleaned up', 'INF');
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
//print_r($aImportClean);
foreach ($aImportClean as $aOneClean) {
    if (isset($aOneClean['error'])) {
        wlog($aOneClean['error'], 'INF');
    }
    else {
        wlog('Scrol import dirs cleaned up', 'INF');
    }
}

//process scrol(check)
//move items from scrol to scrol_bak if they have successfully been imported
$aScrolDataCheck = getScrolData();
foreach ($aScrolDataCheck as $aOneRecord) {
    $sScrolId = $aOneRecord['scrol_id'];
    $aVendorItemData = $oAux->checkVendorItem($sVendorTable, $sVendor, $sScrolId);
    if (isset($aVendorItemData['data']) && $aVendorItemData['data']['mdstatus'] == 'a' && $sDevelop == 0) {
        $aMoveResult = setRecordAsImported($sScrolId, $sDevelop);
        if (isset($aMoveResult['error'])) {
            wlog($aMoveResult['error'], 'INF');
        }
    }
}

//ds_get_dspace_errmess(dspace_imp_dir,run_date,DSPACE_ADD,NULL,errs);
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


$logend = "================ SCROL END ================\n";
wlog($logend, '');


function getScrolData()
{
	$sThesisDataUrl = SCROLSERVICE . '/thesestoimport';
	$sDataFound = file_get_contents($sThesisDataUrl);
	$aScrolData = json_decode($sDataFound, true);

	return $aScrolData;
}

function setRecordAsImported($sScrolId, $sDevelop)
{
	$aResults = array();

	$sProcessUrl = SCROLSERVICE . '/thesisimported';

	$data_array = array('sScrolId' => $sScrolId, 'sGivenKey' => THESISKEY, 'sDevelop' => $sDevelop);
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

function copyScrolSourceFiles($aFiles, $sSourceDir, $sImportDir)
{
    $aResult = array();

    $aFtFiles = $aFiles['ft'];

    foreach ($aFtFiles as $sFile) {
        //$sFile has the full path of the source file
        //find the file name part of that
        //$sLastSlash = strrpos($sFile, '/');
        //$sFileName = substr($sFile, $sLastSlash+1);

        $sSource = $sSourceDir .  $sFile;
        $aResult['text'] = 'source is ' . $sSource;
        //if (!file_exists($sSource)) {
        //    $aResult['error'][] = $sSource . ' does not exist';
        //}
        //else {
            $sDestination = $sImportDir . $sFile;
             try {
                //@symlink($sSource, $sDestination);
                copy($sSource, $sDestination);
            }
            catch  (Exception $e) {
                $aResult['error'][] = 'could not copy ' . $sSource . ' to ' . $sDestination . ': ' . $e->getMessage();
            }
        //}
    }

   return $aResult;
}


function createDublinCoreMetadata($aThesisData, $aScrolFields)
{
	$sDublinCoreText = '<dcvalue element="identifier" qualifier="other">' . $aThesisData['scrol_id'] . '</dcvalue>' . "\n";
	$aMetadata = $aThesisData['metadata'];
	foreach ($aMetadata as $sFieldName => $aValues) {
		if (!empty($aValues)) {
			foreach ($aValues as $sOneValue) {
				$sUseValue = '';

				if ($sOneValue != '') {
					if ($sFieldName == 'sc_thesis_date_embargo') {
						$aDateParts = explode('-', $sOneValue);
						$sUseValue = $aDateParts[2] . '-' . $aDateParts[1] . '-' . $aDateParts[0];
					}
					elseif ($sFieldName == 'ds_courseuu') {
						$firstbracket = strpos($sOneValue, '[');
						$sGoodValue = substr($sOneValue, 0, $firstbracket);

						if (isset($aMetadata['sc_discipline']) && $aMetadata['sc_discipline'] != '') {
							$aDisc = $aMetadata['sc_discipline'];
							$sDiscipline =  $aDisc[0];
							$sUseValue = $sGoodValue . ' - ' . $sDiscipline;
						}
						else {
							$sUseValue = $sGoodValue;
						}
					}
					elseif ($sFieldName == 'sc_rights_accessrights') {
						if($sOneValue == 'No Open Access (not free)') {
							$sUseValue = 'Closed Access';
						}
						else {
							$sUseValue = $sOneValue;
						}
					}
					else {
						$sUseValue = $sOneValue;
					}
					$aElementData = $aScrolFields[$sFieldName];
					$sDublinCoreText .= '<dcvalue element="' . $aElementData['element']
						. '" qualifier="' . $aElementData['qualifier'] . '">'
						. makeSafeString($sUseValue) . '</dcvalue>' . "\n";
				}
			}
		}
	}
	//add embargo date for closed items
	if (isset($aMetadata['sc_rights_accessrights'])) {
		$sAccessRights = $aMetadata['sc_rights_accessrights'][0];
		if ($sAccessRights == 'Closed Access' || $sAccessRights == 'No Open Access (not free)') {
			$sDublinCoreText .= '<dcvalue element="date" qualifier="embargo">2050-01-01</dcvalue>' . "\n";
		}
	}


	return $sDublinCoreText;
}



function makeScrolSafeString($sValue) {
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

$aWrongChars = array(
    "Ã¿" => "ÿ",
    "Ã©" => "é",
    "Ã§" => "ç",
    "Ã¯" => "ï",
    "Ã«" => "ë",
    "Ã¨" => "è",
    "Ã¤" => "ä",
    "Ã¶" => "ö",
    "â " => "'",
    "Ã³" => "ó",
    "Ã¡" => "á",
    "Ã¼" => "ü",
    "â " => "–",
    "Ã±" => "ñ",
    "Ã¨" => "è",
    "Ã¤" => "ä",
    "Ã¶" => "ö",
    "â " => "'",
    "Ã³" => "ó",
    "Ã¡" => "á",
    "Ã¼" => "ü",
    "â " => "–",
    "Ã±" => "ñ",
    );

    $sUseValue = '';
    $bCheckUtf8 = mb_check_encoding($sValue);
    if ($bCheckUtf8) {
        $sUseValue = $sValue;
    }
    else {
        $oEncoding = new FixEncoding();
        $sFixedString = $oEncoding->toUTF8($sValue);
        $sUseValue = $oEncoding->fixUTF8($sFixedString);
    }

    $sControlledString = strtr($sUseValue, $aControlChars);
    $sCorrString = strtr($sControlledString, $aWrongChars);
    $sAmpValue = preg_replace('/&/', '&amp;', $sCorrString);
    $sLessValue = preg_replace('/</', '&lt;', $sAmpValue);
    $sMoreValue = preg_replace('/>/', '&gt;', $sLessValue);

    return $sMoreValue;
}



