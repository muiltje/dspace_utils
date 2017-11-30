<?php
require_once 'import_init.php';

$oAux = new AuxTables();


wlog('updating vendor table', 'INF');

$aSemOn = flipSemaphore(1);
if (isset($aSemOn['error'])) {
    wlog($aSemOn['error'], 'ERROR');
    sendErrorMails('m.muilwijk@uu.nl', $aSemOn['error'], $aSemOn['error']);
    exit();
}

if (isset($aSemOn['warning'])) {
    wlog($aSemOn['warning'], 'INF');
    sendErrorMails('m.muilwijk@uu.nl', $aSemOn['warning'], $aSemOn['warning']);
}
    
$aUpdateVendorResults = $oAux->makeVendorsTable();
if (isset($aUpdateVendorResults['error'])) {
    wlog($aUpdateVendorResults['error'], 'INF');
}
else {
    wlog('vendor table updated', 'INF');
}
    
$aSemOff = flipSemaphore(0);
if (isset($aSemOff['warning'])) {
    wlog($aSemOff['warning'], 'INF');
    sendErrorMails('m.muilwijk@uu.nl', $aSemOff['warning'], $aSemOff['warning']);
}

/**
 * Value 1: on; 0 off
 * @param type $nValue 
 */
function flipSemaphore($nValue)
{
    $aResult = array();
    
    $sSemaphoreFile = VENDOR_SEM;
    
    if ($nValue == 0) {
        if (!(@unlink($sSemaphoreFile))) {
            $aResult['warning'] = 'cannot clear semaphore';
        }
    }
    else {
        if (file_exists($sSemaphoreFile)) {
             $aResult['error'] = 'semaphore: vendor update running';
            return $aResult;
        }
        else {
            $fh = @fopen($sSemaphoreFile, "w");
            //if ($fh = fopen($sSemaphoreFile, "w")) {
            if ($fh) {
                fclose($fh);
            }
            else {
                $aResult['warning'] = 'cannot set semaphore';
            }
        }
    }
    
    return $aResult;
}

?>
