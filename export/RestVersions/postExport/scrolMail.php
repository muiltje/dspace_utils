<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once '/home/dspace/utils/newexport/init.php';

$sToday = date("Ymd");
$sExportDir = EXPORTBASE;
$sExportFileName = 'scrol.' . $sToday . '.xml';
//$sExportFileName = 'scrol.20141109.xml';
$sExportFile = $sExportDir . $sExportFileName;

$aItemsForMail = array();

if (file_exists($sExportFile)) {
    $aNewItems = array();

    $sReadFile = file_get_contents($sExportFile);
    $xml = new SimpleXMLElement($sReadFile);
    if (is_object($xml)) {
        $aItemsForMail = getItems($xml);
    }
    else {
        echo 'cannot read';
    }

}
//send email if there are any items to be mailed about
if (count($aItemsForMail) > 0) {
    sendCheckEmail($aItemsForMail);
}


function getItems($xml)
{
    $aItems = array();

    $records = $xml->xpath('dspace_record');
    foreach ($records as $record) {
        $urifield = $record->xpath('DC.identifier.uri');
        $sFullUri = (string) $urifield[0];
        $handle = substr($sFullUri, 22);
            
        $aItems[] = $handle;
    }
    
    return $aItems;
}

function sendCheckEmail($aMailItems) {
    $mailtext = '';
    
    if (count($aMailItems) == 1) {
        $mailtext .= 'Er is een scriptie geexporteerd: ';
    }
    else {
        $mailtext .= 'Er zijn scripties geexporteerd: ';
    }
    $mailtext .= "\n";
    
    foreach ($aMailItems as $sHandle) {
        $mailtext .= $sHandle . "\n";
    }
    
    $from = 'g.vandongen@uu.nl, m.muilwijk@uu.nl';
    $to = 'm.muilwijk@uu.nl';
    $subject = 'Scriptie export van vandaag';
    $message = $mailtext;

    $headers = 'To:' . $to . "\r\n";
    $headers .= 'From: ' . $from . "\r\n";
 
    $result = mail($to, $subject, $message, $headers);
    
    return $result;
}