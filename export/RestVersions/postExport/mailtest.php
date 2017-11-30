<?php

require_once '../init.php';
require_once CLASSES . 'SherpaRomeo.php';
$oSherpa = new SherpaRomeo();

$sISSN = '0305-7364';
$aSherpaInfo = checkSherpaRomeo($sISSN, $oSherpa);
//print_r($aSherpaInfo);
$sEmailText = $aSherpaInfo['outputtext'];
$sTo = 'm.muilwijk@uu.nl';
$sMailSent = sendMail($sEmailText, $sTo);
print_r($sMailSent);

function checkSherpaRomeo($sISSN, $oSherpa)
{
    $sSherpaResult = $oSherpa->checkSherpaRomeo($sISSN);
    
    return $sSherpaResult;
}

function sendMail($sEmailText, $sTo)
{
    $sHeaders = 'MIME-Version: 1.0' . "\r\n";
    $sHeaders .= 'Content-type: text/html; charset=utf-8' . "\r\n";
    $sHeaders .= 'From: d-arch@uu.nl' . "\r\n";
    
    $sSubject = 'Your publication has been added to the archive';
    
    $sResult = mail($sTo, $sSubject, $sEmailText, $sHeaders);
    
    return $sResult;
}

