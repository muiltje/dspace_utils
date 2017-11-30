<?php

exit();

require_once 'alert_init.php';

$sStartDate = '2014-03-10';
$sEndDate = '2014-03-19';
$oAux = new AuxTables();

/*
$aFutureEmbargoData = $oAux->findFutureEmbargoAlert();
echo count($aFutureEmbargoData) . ' toekomstige embargodata ' . "\n";

//print_r($aFutureEmbargoData);
$aRetroAlerts = array();

foreach ($aFutureEmbargoData as $aOneEmbargo) {
    $sEmbargoEnd = $aOneEmbargo['text_value'];
    
    if (substr($sEmbargoEnd, 0, 7) == '2014-05') {
        $nDay = (int) substr($sEmbargoEnd, -2);
        if ($nDay > 14) {
            //print_r($aOneEmbargo);
            $aRetroAlerts[] = $aOneEmbargo;
        }
    }
    elseif (substr($sEmbargoEnd, 0, 7) == '2014-06') {
        //print_r($aOneEmbargo);
        //$counter++;
        $aRetroAlerts[] = $aOneEmbargo;
    }
}

echo count($aRetroAlerts) . ' retro Alerts ' . "\n";

var_export($aRetroAlerts);
 * 
 */

$oHandle = new Handle();
$oItemMetadata = new ItemMetadata();

foreach ($aRetroAlert as $aOneAlert) {
    $sEmail = $aOneAlert['email'];
    if (strpos($sEmail, '@')) {
        $sEmbargoDate = $aOneAlert['text_value'];
        $sHandle = $aOneAlert['handle'];
        $aItemIdData = $oHandle->getItemId($sHandle);
        $nItemId = $aItemIdData['itemid'];
        $aMetadata = $oItemMetadata->getMetadataValue($nItemId, 143);
        $sTitle = $aMetadata['values'][0];
        echo $sTitle . "\n";
        
        sendMail($sEmail, $sTitle, $sEmbargoDate);
    }
    else {
        echo "no email \n";
    }
}

function sendMail($sEmail, $sTitle, $sEmbargoDate)
{
    $sAlertText = 'The embargo on the dissertation ' . $sTitle  
        . ' will expire on ' . $sEmbargoDate . "\n" 
        . 'From then on, this dissertation will be freely available in the Igitur Archive and on the Internet.';
    $sAlertText .= "\n\n";
    $sAlertText .= 'Please contact us at support-igitur@uu.nl should you have any questions.';
    $sAlertText .= "\n\n --\n";
    $sAlertText .= "Utrecht University Library\n";
    $sAlertText .= "http://www.uu.nl/university/library/EN/igitur/Pages/default.aspx\n";
    
    $sIntroText = 'The following email would be sent to ' . $sEmail;
    
    $sFrom = 'support-igitur@uu.nl';
    
    $sSubject = 'Doctoral thesis embargo alert - test';
    $sTo = 'm.muilwijk@uu.nl';
    $sMessage = $sIntroText . "\n\n" . $sAlertText;
    $sHeaders = 'From:' . $sFrom . "\r\n";
    
     
    /*  
    $sSubject = 'Doctoral thesis embargo alert';
    $sTo = $sEmail;
    $sMessage = $sAlertText;
    $sHeaders = 'From:' . $sFrom . "\r\n";
    $sHeaders .= 'BCC: m.muilwijk@uu.nl' . "\r\n";
     * 
     */

    
    mail($sTo, $sSubject, $sMessage, $sHeaders);
    

}