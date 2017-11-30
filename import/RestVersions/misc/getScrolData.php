<?php

require_once '../import_init.php';
$sCredIni = '/usr/local/opt/connectstring/cred_scrol.ini';
$aIniCred = parse_ini_file($sCredIni);

$sUser = $aIniCred['usr'];
$sPwd = $aIniCred['pw'];

//$mysqli;
try {
    $mysqli = mysqli_connect('apollo_hme', $sUser, $sPwd, 'scrol');
}
catch (Exception $e) {
    $line = 'no connection: ' . $e->getTraceAsString();
    wlog($line, 'INF');
    exit();
}

$sScrolId = '1372870999793';
$aScrolData = getData($mysqli, $sScrolId);

print_r($aScrolData);



function getData($mysqli, $sScrolId)
{
    $aScrolData = array();
    
    $sql = "SELECT * FROM scrol WHERE scrol_id='" . $sScrolId . "'";
    
    try {
        $result = mysqli_query($mysqli, $sql);
        for ($count = 1; $row = mysqli_fetch_array ($result); ++$count) {
            $aScrolData[] = $row;
        }
    }
    catch (Exception $e) {
        $aScrolData['error'] = 'could not get data from scrol: ' . $e->getTraceAsString();
    }
    
    return $aScrolData;
}
?>
