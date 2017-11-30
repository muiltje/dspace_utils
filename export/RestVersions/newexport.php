<?php
/**
 * 
 */
require_once 'init.php';

wlog('Starting the new export', 'INF');

$bShowExpdefList = 0;
$bExportOnly = 0;
$bDerivativesOnly = 0;
$bWithdrawnOnly = 0;
$bFullDump = 0;
$bVerbose = 0;
$sExportDefinitionString = '';
$sExportDirectory = '';

$bUpdateLastRun = 1;

//Get script params
$sShortOpts = "ldhevfw";
$aLongOpts = array('expdef::', 'expdir::');
//expdef can be a string of definition_ids, separated by commas

$aOptions = getopt($sShortOpts, $aLongOpts);

if (isset($aOptions['l'])) {
    $bShowExpdefList = 1;
}
if (isset($aOptions['h'])) {
    usage();
}
if (isset($aOptions['e'])) {
    $bExportOnly = 1;
}
if (isset($aOptions['d'])) {
    $bDerivativesOnly = 1;
}
if (isset($aOptions['f'])) {
    $bFullDump = 1;
}
if (isset($aOptions['v'])) {
    $bVerbose = 1;
}
if (isset($aOptions['w'])) {
    $bWithdrawnOnly = 1;
}

if (isset($aOptions['expdef']) && $aOptions['expdef'] != '') {
    $sExportDefinitionString = $aOptions['expdef'];
}
if (isset($aOptions['expdir']) && $aOptions['expdir'] != '') {
    $sExportDirectory = $aOptions['expdir'];
    //check if export directory exists and is accessable
    if (!is_dir($sExportDirectory)) {
        echo $sExportDirectory . " does not exist or is not a directory \n";
        exit();
    }
}
if ($bShowExpdefList == 1) {
    showExportDefinitions();
}


if ($argc < 2) {
    usage();
}

//
$sReaderUrl = READER_URL;
$sManifestationStore = MANIFESTATION_STORE;
$sResolverExportPath = RESOLVER_EXPORT_PATH;
$sResolverUrl = RESOLVER_URL;
$sTempDerivFile = TEMPDERIV;
$sBaseUrl = HOME_URL;

$sMode = 'noremove';
//============== start of flow =================
$oItem = new Item();
$oMetadataExport = new MetadataExport();
$oEnrichment = new Enrichment($aMetadataFields); //metadatafields are set bij init.php
$oDigExport = new DigitizedExport($aMetadataFields);

if ($bDerivativesOnly == 1) {
    wlog('======== Derivatives Only ========', 'INF');
    if ($bExportOnly != 1) {
        //update fulltext link
        logVerbose('updating the link to the full text', $bVerbose);
        $aRecentItemsResult = $oDigExport->processRecentItems($sReaderUrl);
        logResults($aRecentItemsResult, 'recent dig items success');
        
        //prepare string of handles to be used when generating derivatives
        //do it only for the new and modified items, unless fulldump=1
        $sHandles = '';
        if ($bFullDump == 0) {
            if (isset($aRecentItemsResult['handles']) && $aRecentItemsResult['handles'] != '') {
                $sHandles = $aRecentItemsResult['handles'];
            }
        }
        $fh = fopen($sTempDerivFile, "w");
        fwrite($fh, $sHandles);
        fclose($fh);
    }
    
    //export data for resolver
    //make sure you only set the mode to 'doremove' if you want to replace items 
    //in the production manifestation store
    //set the maximum number of items to prevent large files in test
    //$sMode = 'test';
    //$nMaxItems = 10;
    //if (ENVIRONMENT == 'production') {
//        $sMode = 'doremove';
        $nMaxItems = 0;
    //}
    logVerbose('export resolver data in mode ' . $sMode, $bVerbose);
    $aResolverExportResult = $oDigExport->exportResolverData($sResolverExportPath, $sResolverUrl, $sMode, $nMaxItems);
    logResults($aResolverExportResult, 'data exported to resolver');
}
else {
    if ($bExportOnly != 1) {
        wlog('======== Do Enrichments ========', 'INF');
        //correct issn (from some sources, the issn can be in the wrong metadata field)
        //$aEnrIssn = $oEnrichment->updateISSN();
        //logResults($aEnrIssn, $aEnrIssn['text']);
    
        //insert URN-BNB
        logVerbose('inserting urn-bnb', $bVerbose);
        $aEnrUrn = $oEnrichment->addUrnNbn($bFullDump);
		//if this fails, abort, because everything else will probably fail as well
		if (isset($aEnrUrn['error'])) {
			$sErrorMessage = 'first database action failed; exiting' . $aEnrUrn['error'];
			logResults($sErrorMessage, 'ERR');
			exit();
		}

        logResults($aEnrUrn, 'URN-NBN added');
          
        //update fulltext link for digitized objects and get their handles
        logVerbose('updating the link to the full text', $bVerbose);
        $aRecentItemsResult = $oDigExport->processRecentItems($sReaderUrl);
        logResults($aRecentItemsResult, 'recent dig items success');

        //make sure the handles you get from $aRecentItemsResult are available
        //when you start generating derivatives
        //by writing them to a temp file
        //logVerbose('generating derivs', $bVerbose);
        //$sHandles = '';
        $fh = fopen($sTempDerivFile, "w");
        if (isset($aRecentItemsResult['handles']) && $aRecentItemsResult['handles'] != '') {
            $sHandles = $aRecentItemsResult['handles'];
            fwrite($fh, $sHandles);
        }
        fclose($fh);
     
        
        //export data for resolver
        //$sMode = 'test';
        //$nMaxItems = 10;
        //if (ENVIRONMENT == 'production') {
//            $sMode = 'doremove';
            $nMaxItems = 0;
        //}
        
        logVerbose('export resolver data', $bVerbose);
        $aResolverExportResult = $oDigExport->exportResolverData($sResolverExportPath, $sResolverUrl, $sMode, $nMaxItems);
        logResults($aResolverExportResult, 'data exported to resolver');

            
        //Add url for item page to fulltext and jumpoffpage fields, if an item doesn't already have them.
        //Make sure this happens AFTER the fulltext link for digitized objects has been updated.
        //That way we make sure that those have a link, so that they aren't overwritten by this one.
        //Igitur Journals should not get a urlfulltext and closed access items should be skipped
        //(both are managed in the addItemPageUrl method)
        logVerbose('adding missing full text links', $bVerbose);
        $aFullTextResult = $oEnrichment->addItemPageUrl($bFullDump, $sBaseUrl);
        logResults($aFullTextResult, 'full text links added');
     }
    
    wlog('======== Export Metadata ========', 'INF');
    
    $aExportDefs = array();
    if ($sExportDefinitionString != '') {
        $aDefIds = explode(',', $sExportDefinitionString);
        foreach ($aDefIds as $nId) {
            $aExportDefs[] = $oMetadataExport->findExportDefinitionDetails($nId);
        }
    }
    else {
        //no export definition set
        //get all export definitions that are in the database
        $aExportDefs = $oMetadataExport->findAllExportDefinitions(); 
    }
    
	//if no export definitions were found, there's no point in continuing
	if (empty($aExportDefs)) {
		$sErrMessage = 'could not find any export definitions; exiting';
		logResults($sErrMessage, 'ERR');
		exit();
	}	
	
    foreach ($aExportDefs as $aOneDef) {
        $nExportDefId = $aOneDef['exportdef_id'];
        $sExportFileName = 'resttest_' . $aOneDef['exportdef_filename'];
        
        $bDefIsFullDump = 0;
        if ($bFullDump == 1) {
            $bDefIsFullDump = 1;
        }
        elseif ($aOneDef['fulldump'] == 't') {
             $bDefIsFullDump = 1;
        }
     
        if ($bWithdrawnOnly != 1) {
            //get metadata for normal items
            $aNormalExportItems = $oItem->getExportItems($nExportDefId, $bDefIsFullDump, 0);
            $sLine = count($aNormalExportItems) . " normal items to export for " . $nExportDefId;
            logVerbose($sLine, $bVerbose);

            //insert or update ubu.repository field
            //although this is a metadata action, it is done only on items that will be exported
            if ($bExportOnly != 1) {
                $sLine = 'adding ubu.repository to items for exportdef ' . $nExportDefId;
                logVerbose($sLine, $bVerbose);
                $aUbuRep = $oEnrichment->addUbuRepository($aNormalExportItems);
                logResults($aUbuRep, 'ubu.repository added');
            }

            //export metadata
            $sLine = 'exporting normal items for ' . $nExportDefId;
            logVerbose($sLine, $bVerbose);
            $bWithdrawnItems = 0;
            $aNormalExport = $oMetadataExport->exportMetadata($aNormalExportItems, $bDefIsFullDump, $bWithdrawnItems, $sExportDirectory, $sExportFileName);
			logResults($aNormalExport, 'export done');
        }
        
        
        //get data for withdrawn items
        $aWithdrawnExportItems = $oItem->getExportItems($nExportDefId, $bDefIsFullDump, 1);
        $sLine = count($aWithdrawnExportItems) . " withdrawn items to export for " . $nExportDefId;
        logVerbose($sLine, $bVerbose);
   
        
        //export withdrawn items
		//@todo: manifestation-store remove
		$sLine = "exporting withdrawn items";
        logVerbose($sLine, $bVerbose);
        $bWithdrawnItems = 1;
        $aWithdrawnExport = $oMetadataExport->exportMetadata($aWithdrawnExportItems, $bDefIsFullDump, $bWithdrawnItems, $sExportDirectory, $sExportFileName);
        logResults($aWithdrawnExport, 'withdrawn export done');

        
       
        //if $aOneDef['fulldump'] is true, now set it to false
        if ($aOneDef['fulldump'] == 't') {
            //echo 'resetting fulldump value ' . "\n";
            $oMetadataExport->updateExportDefinition($nExportDefId);
            $line = 'resetting fulldump for ' . $nExportDefId;
            wlog($line, 'INF');
        }
        
        //at this point, check if exportfiles for this definition are valid
        //if they are, send them off to the dspacequeue webservice
        $sExportDefFileName = $aOneDef['exportdef_filename'];
        $aFileCheck = checkExportFiles($sExportDefFileName);
        
        if (isset($aFileCheck['OK'])) {
            $aOkFiles = $aFileCheck['OK'];
            foreach ($aOkFiles as $sExportFile) {
                //if (!preg_match('/digobjects/', $sExportFile)) {
                    //send to webservice
                    $sSendResult = sendFileNameToService($sExportFile);
                    wlog($sSendResult, 'INF');
                //}
            }
        }
        if (isset($aFileCheck['invalid'])) {
           $aInvalidFiles = $aFileCheck['invalid'];
           foreach ($aInvalidFiles as $sFileName) {
                echo $sFileName . ' is invalid ' . "\n";
           }
        }
    } //end of foreach exportdef
  
    
     if ($bExportOnly != 1) {
        wlog('======== Last Bits ========', 'INF');
        
        //update last run timestamp
        if ($bUpdateLastRun == 1) {
            $sNow = date('Y-m-d H:i:s');
            $sLine = 'last run timestamp is ' . $sNow;
            logVerbose($sLine, $bVerbose);
            $oAux = new AuxTables();
            $aTimestampResult = $oAux->updateLastRunTimeStamp($sNow);
            logResults($aTimestampResult, 'last run timestamp updated');
        }
     }
}



/**
 * Check if today's export files are valid XML
 * 
 * @return array
 */
function checkExportFiles($sExportFileName)
{
    $sToday = date("Ymd");
    $sExportDir = EXPORTBASE;
    
    $aFileChecks = array();
    
    $sNormalFileName = $sExportFileName . '.' . $sToday . '.xml';
    $sWithdrawnFileName = $sExportFileName . '.withdrawn.' . $sToday . '.xml';
    
    $sNormalFileToTest = $sExportDir . $sNormalFileName;
    $sWithdrawnFileToTest = $sExportDir . $sWithdrawnFileName;
    
    if (file_exists($sNormalFileToTest)) {
        $sNXml = simplexml_load_file($sNormalFileToTest);
        if ($sNXml) {
            $aFileChecks['OK'][] = $sNormalFileName;
        }
        else {
            $aFileChecks['invalid'][] = $sNormalFileName;
        }
    }
    else {
        $aFileChecks['missing'][] = $sNormalFileName;
    }
    
    if (file_exists($sWithdrawnFileToTest)) {
        $sWXml = simplexml_load_file($sWithdrawnFileToTest);
        if ($sWXml) {
            $aFileChecks['OK'][] = $sWithdrawnFileName;
        }
        else {
            $aFileChecks['invalid'][] = $sWithdrawnFileName;
        }
    }
    else {
        $aFileChecks['missing'][] = $sWithdrawnFileName;
    }
    
    return $aFileChecks;
}


function sendFileNameToService($sExportFile)
{
    //$sServiceUrl = 'http://uws5.library.uu.nl/messages/messages/DspaceQueue/';
    $sServiceUrl = INDEX_QUEUE;
    $data_array = array('filename'=>$sExportFile);
    $data = http_build_query($data_array);
    $params = array('http' => array('method' => 'POST', 'content' => $data, 'header'  => 'Content-type: application/x-www-form-urlencoded'));
    $ctx = stream_context_create($params);

    try {
        $fp = fopen($sServiceUrl, 'rb', false, $ctx);
        $sResult = stream_get_contents($fp);
        $sLine = $sExportFile . ' posted to webservice';
        wlog($sLine, 'INF');
    }
    catch (Exception $e) {
        $sLine = 'Post to webservice went wrong: ' . $e->getMessage();
        wlog($sLine, 'INF');
        $sResult = $e->getMessage();
    }
    
    return $sResult;
}



/**
 * Log the results of an action:
 * the error message if there is one, else the success message
 * 
 * @param array $aResults
 * @param string $sSuccessString
 * @return int
 */
function logResults($aResults, $sSuccessString)
{
    if (isset($aResults['error'])) {
        if (is_array($aResults['error'])) {
            foreach ($aResults['error'] as $aOneError) {
                wlog($aOneError, 'INF');
            }
        }
        else {
            wlog($aResults['error'], 'INF');
        }
    }
    elseif (isset($aResults['debug'])) {
        if (is_array($aResults['debug'])) {
            foreach ($aResults['debug'] as $key => $sDebug) {
                wlog($key . ': ' . $sDebug, 'INF');
            }
        }
        else {
            wlog($aResults['debug'], 'INF');
        }
    }
    else {
        wlog($sSuccessString, 'INF');
    }
    
    return 1;
}

/**
 * Log a message if verbose is on
 * 
 * @param string $sLine
 * @param ing $bVerbose
 * @return int
 */
function logVerbose($sLine, $bVerbose)
{
    if ($bVerbose == 1) {
        wlog($sLine, 'INF');
    }
    
    return 1;
}

/**
 * Usage
 */
function usage()
{
    echo "usage: newexport.php [-ldhevfw][--expdef][--expdir] \n";
    echo "  -l: show list of export definitions \n";
    echo "  -d: derivatives only 
        (no enrichment of metadata, no export of anything but digitized objects) \n";
    echo "  -e: export only (no enrichment of metadata, no derivatives) \n";
    echo "  -v: verbose logging \n";
    echo "  -f: fulldump 
        (can be combined with -e for a full dump of --expdef for the collections in the definitions) \n";
    echo "  -w: export only the withdrawn items \n";
    echo "  -h: this help \n";
    echo "  --expdef: use export definitions; comma-separated list with definition ids \n";
    echo "  --expdir: write export to the directory given 
        (instead of the one defined in the export definitions) \n";
    
    exit();
}

/**
 * Show a list of all available export definitions
 */
function showExportDefinitions()
{
    $oAux = new AuxTables();
    
    $aExpDefData = $oAux->findExportDefinitions();
    
    if (isset($aExpDefData['error'])) {
        wlog($aExpDefData['error'], ' ');
        echo "No export definitions found! \n";
    }
    else {
        echo "Export definitions: \n";
        foreach ($aExpDefData['expdefs'] as $aOneDef) {
            echo $aOneDef['exportdef_id'];
            echo ' - ';
            echo $aOneDef['exportdef_name'];
            echo "\n";
        }
    }
    
    exit();
}

