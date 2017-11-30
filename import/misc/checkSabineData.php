<?php

require_once '../import_init.php';
//connection to sabine database; it may live on another server
$sCredIni = '/usr/local/opt/connectstring/cred_sabine.ini';
$aIniCred = parse_ini_file($sCredIni);
$sUser = $aIniCred['usr'];
$sPwd = $aIniCred['pw'];

//$mysqli;
try {
    $mysqli = mysqli_connect('apollo_hme', $sUser, $sPwd, 'bb_sabine');
}
catch (Exception $e) {
    $line = 'no connection:' . $e->getMessage();
    wlog($line, 'ERR');
    exit();
}

$sSabineId = '1001';
$aSabineData = getData($sSabineId, $mysqli);

//print_r($aSabineData);

foreach ($aSabineData as $field => $value) {
    if ($value != '' && preg_match('/dat/', $field)) {
        //$bCheckUtf8 = mb_check_encoding($sValue);
        $sValue = makeSabineSafeString($value);
        
        echo $field . ' --- ' . $sValue . "\n";
    }
}



function getData($sRecid, $mysqli)
{
    $aSabineData = array();
    
    $sql = "select * from tbl01 where recid=" . $sRecid;
    try {
        $result = mysqli_query($mysqli, $sql);
        for ($count = 1; $row = mysqli_fetch_array ($result); ++$count) {
            $aSabineData = $row;
        }
    }
    catch (Exception $e) {
        $aSabineData['error'] = 'could not get data from scrol: ' . $e->getTraceAsString();
    }
 
    return $aSabineData;
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
