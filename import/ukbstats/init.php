<?php

$inifile = '/home/dspace/utils/newexport/ukbstats/stats.ini';

$aIniArray = parse_ini_file($inifile, true);


//Determine the server entry
switch (strtolower(php_uname('n')))
{
    case 'grieg':
        define('HOST_TYPE', 'grieg');
        break;
    default :
        define('HOST_TYPE', 'grieg');
}

//dbconnection
require_once ($aIniArray[HOST_TYPE]['dbconnect']);
$dbconn = pg_connect($connect);

//classpath
define('CLASSES', $aIniArray[HOST_TYPE]['classes']);
define('EXPORTPATH', $aIniArray[HOST_TYPE]['exportpath']);
define('LOGPATH', $aIniArray[HOST_TYPE]['log_path']);
define('PHPTOOLPATH', $aIniArray[HOST_TYPE]['phptools']);

$sLogFile = LOGPATH . 'ukbstats_' . date('Ymd') . '.log';
require_once (PHPTOOLPATH . 'utils/log/Logger.php');
$log = new Logger($sLogFile);


/**
 *Simple wrapper for the log object where loglevel is adjusted
 * @global Logger $log
 * @param type $msg
 * @param type $typ
 * @param type $level
 * @return type 
 */
function wlog($msg,$typ='INF',$level=1)
{
  global $log;

  if ($level==0)
  {
    return; //no logging
  }
  else {
      $log->log($msg,$typ );
  }

}
?>
