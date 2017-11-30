<?php
/**
 * 
 */
require_once 'init.php';

 $sPrevious = strtotime('14 hour ago');
$sPrevDate = date('Y-m-d H:i:s', $sPrevious);
 

 echo $sPrevDate . "\n";

exit();

$oAux = new AuxTables();
$oAux->updateLastRunTimeStamp($sPrevDate);
 
