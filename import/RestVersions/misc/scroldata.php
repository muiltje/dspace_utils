<?php

require_once 'import_init.php';
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

$aTestConnection = testScrolConnection($mysqli);
print_r($aTestConnection);

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
);

//$nScrolId = 1377878367817;
$aItemData = getScrolItemData($mysqli);

print_r($aItemData);

$aScrolData = getScrolData($mysqli);
echo ' I see ' . count($aScrolData) . ' scrol data';

echo "\n";


function getScrolData($mysqli)
{
    $aScrolData = array();
    
    $sql = "SELECT DISTINCT s.scrol_id, s.faculty, s.misc_data, s.date_exam
        FROM scrol s, files f
        WHERE s.approved=1 AND YEAR(s.date_exam) > 0 AND s.scrol_id=f.scrol_id
        ORDER BY s.scrol_id";
    
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

function testScrolConnection($mysqli)
{
    $aScrolTest = array();
    
    $sql = "select count(*) as total from scrol";
    
    try {
        $result = mysqli_query($mysqli, $sql);
        for ($count = 1; $row = mysqli_fetch_array ($result); ++$count) {
            $aScrolTest['count'] = $row['total'];
        }
    }
    catch (Exception $e) {
        $aScrolTest['error'] = 'could not connect to scrol: ' . $e->getTraceAsString();
    }
    
    return $aScrolTest;
}

function getScrolItemData($mysqli)
{
    $aItemData = array();
    
    $sql = "SELECT * FROM scrol_bak WHERE scrol_id=1377878367817";
    //$sql = "SELECT * FROM scrol WHERE scrol_id=1211887554720";
    $aItemData['sql'] = $sql;
    
    
    try {
        $result = mysqli_query($mysqli, $sql);
        for ($count = 1; $row = mysqli_fetch_array($result); ++$count) {
            $aItemData = $row;
        }
    }
    catch (Exception $e) {
        $aItemData['error'] = 'could not get data from scrol: ' . $e->getTraceAsString();
    }
    
    
    return $aItemData;
}
?>
