<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$sScrolCredIni = '/usr/local/opt/connectstring/cred_scrol.ini';
$aScrolIniCred = parse_ini_file($sScrolCredIni);

$sScrolUser = $aScrolIniCred['usr'];
$sScrolPwd = $aScrolIniCred['pw'];

$sOsUser = 'BIBLI327';
$sOsPwd = 'igitur23';

try {
    $mysqli = mysqli_connect('apollo_hme', $sScrolUser, $sScrolPwd, 'scrol');
}
catch (Exception $e) {
    $line = 'no connection: ' . $e->getTraceAsString();
    wlog($line, 'INF');
    exit();
}

try {
    $osconn = oci_connect($sOsUser, $sOsPwd);
} 
catch (Exception $ex) {
    echo $ex->getMessage();
    exit();
}

echo 'Connected to both';

$osquery = "SELECT TO_CHAR(examendatum,'YYYY-MM-DD') FROM OST_STUDENT_EXAMEN " .
              "WHERE studentnummer='$solis_id' AND opleiding='$discipline' AND examentype='$type_exam' AND examendatum IS NOT NULL";
   if (!($stmt = oci_parse($osconn,$osquery))) {
      oraerr($osconn);
   }
   
   if (!($res = oci_execute($stmt,OCI_DEFAULT))) {
      oraerr($osconn);
   }

   while ($row = oci_fetch_array($stmt,OCI_NUM)) {
      foreach ($row as $key => $item) {
         $date_exam = $item;
      }
   }
   oci_free_statement($stmt);