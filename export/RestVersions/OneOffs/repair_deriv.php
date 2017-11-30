<?php
require_once '/home/dspace/utils/newexport/init.php';

$sReaderUrl = READER_URL;
$sTempDerivFile = TEMPDERIV;

$aItemsToCheck = array(
'1874/287839', '1874/287194', '1874/288142', 
'1874/287190', '1874/287625', '1874/288138', '1874/287189', '1874/288145', 
'1874/287191', '1874/288141', '1874/287736', '1874/287624', '1874/288139', 
'1874/288028', '1874/287192', '1874/287626', '1874/288144', '1874/287623',
'1874/287622', '1874/288143', '1874/288140', '1874/287627', '1874/287944',
'1874/287945',
);
/*
$aItemsToCheck = array(
'1874/287839', 
);
 * 
 */

$aItemsToDo = array();

foreach ($aItemsToCheck as $sHandle) {
    $sReaderHandle = str_replace('/', '-', $sHandle);
    $sUrlToTest = $sReaderUrl . $sReaderHandle;
    $sWebsiteText = getWebsite($sUrlToTest);
    $sCheck = checkPageNums($sWebsiteText);
    if ($sCheck == 'n') {
        $aItemsToDo[] = $sReaderHandle;
    }
}

if (empty($aItemsToDo)) {
    exit();
}
else {
    $sHandleString = '';
    for ($i=0; $i<5; $i++) {
        $sHandleString .= $aItemsToDo[$i];
        if ($i<4) {
            $sHandleString .= ',';
        }
    }

    
    //echo $sHandleString;
    $fh = fopen($sTempDerivFile, "w");
    fwrite($fh, $sHandleString);
    fclose($fh);
    
    //$aGenerateResult = $oDigExport->generateDerivatives($sManifestationStore, $bFullDump, $sHandlesToDo);
//http://manifestation-store.library.uu.nl/build?handle=1874-284749&material=infofile&force=1
    
}



function getWebsite($sUrl)
{
    $aParams = array('http' => array('method' => 'GET'));
    $oCtx = stream_context_create($aParams);

    try {
	$fp = fopen($sUrl, 'rb', false, $oCtx);
	$sResult = stream_get_contents($fp);
     }
    catch (Exception $e) {
        $sResult = 'something went wrong: ' . $e->getMessage();
   }
    
    return $sResult;
}


function checkPageNums($sText)
{
    $sPattern = 'http666';
    
    $sProperPages = 'y';
    
    if (strpos($sText, $sPattern)) {
        $sProperPages = 'n';
    }
    
    return $sProperPages;
}


function launchBuild($sBuildUrl)
    {
        $aDebug = array();
        $sLogFile = "/opt/local/cachelogs/ubu_log/dsutils/ManifestationPipeLog.txt";
        
        $sCommand = "/usr/bin/curl " . $sBuildUrl;
   
        $fh = fopen($sLogFile, "a");
        
        try {
            $ph = popen($sCommand, "r");
            while(!feof($ph)) {
                $read = fread($ph, 2096);
                fwrite($fh, $read);
            }
            pclose($ph);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not open pipe: ' . $e->getTraceAsString();
        }
        
        fclose($fh);
         
        return $aDebug;
    }


?>
