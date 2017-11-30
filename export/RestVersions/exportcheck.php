<?php
/**
 */
require_once 'init.php';

//============== Check export files =====================

//for each exportfile, check if it is valid XML;
//if not, rename and send mail
$aFileChecks = checkExportFiles();
$aInvalidFiles = array();
$aGoodFiles = array();
if (isset($aFileChecks['files']) && !empty($aFileChecks['files'])) {
    foreach ($aFileChecks['files'] as $sFileName => $sResult) {
        if ($sResult == 'fail') {
            //move to error queue
            $source = EXPORTBASE . $sFileName;
            $destination = ERRORPATH . $sFileName;
            rename($source, $destination);
            echo 'renaming ' . $source  . ' to ' . $destination . "\n";
            $aInvalidFiles[] = $sFileName;
        }
        else {
            $aGoodFiles[] = $sFileName;
        }
    }
}
else {
    wlog('No export files found for today', 'INF');
}

if (count($aInvalidFiles) > 0) {
     $sSubject = 'Invalid DSpace export files';
     $sFrom = 'dspace-noreply@grieg.library.uu.nl';
     $sTo = 'm.muilwijk@uu.nl, e.hackenitz@uu.nl';

     $sMessage = "Some export files are not valid XML \n";
     foreach ($aInvalidFiles as $sFile) {
         $sMessage .= $sFile . "\n";
     }
     $sMessage .= "\n";
     $sMessage .= 'Invalid files can be found in ' . ERRORPATH . "\n";

     $sHeaders = 'From:' . $sFrom . "\r\n";
     mail($sTo, $sSubject, $sMessage, $sHeaders);
 }



//=================== Check derivatives ======================

//check if generating derivatives went well
 $aFailedDerivs = array();
 $sPipeLog = '/opt/local/cachelogs/ubu_log/dsutils/ManifestationPipeLog.txt';
 $aLines = file($sPipeLog);
 foreach ($aLines as $sLine) {
     if (strpos($sLine, 'failed')) {
         $aFailedDerivs[] = $sLine;
     }
 }
 if (count($aFailedDerivs) > 0) {
     $sSubject = 'Failed derivatives';
     $sFrom = 'dspace-noreply@grieg.library.uu.nl';
     $sTo = 'm.muilwijk@uu.nl';

     $sMessage = 'Not all derivatives could be generated. The message is: ' . "\n";
     foreach ($aFailedDerivs as $sFailLine) {
         $sMessage .= $sFailLine;
     }

     $sHeaders = 'From:' . $sFrom . "\r\n";
     mail($sTo, $sSubject, $sMessage, $sHeaders);

 }

 //empty the manifestation pipelog
 $fh = fopen($sPipeLog, "w");
 fwrite($fh, "  ");
 fclose($fh);

wlog('======== DONE ========', 'INF');
wlog('  ', ' ');


function checkExportFiles()
{
    $sToday = date("Ymd");
    $sExportDir = EXPORTBASE;

    $aFileChecks = array();

    $dh = opendir($sExportDir);
    while (($infile = @readdir($dh)) !== false) {
        $sMatch = '/' . $sToday . '/';
        //$sMatch = '/20140514/';

		if (preg_match($sMatch, $infile)) {
            $sFileToTest = $sExportDir . $infile;
            $xml = simplexml_load_file($sFileToTest);
            if ($xml) {
                $aFileChecks['files'][$infile] = 'ok';
            }
            else {
                $aFileChecks['files'][$infile] = 'fail';
                $aFileChecks['fail'] = 1;
            }
        }
    }

    return $aFileChecks;
}
?>
