<?php
date_default_timezone_set('Europe/Amsterdam');

$inifile = '/home/dspace/utils/resourcepolicies/resourcepolicy.ini';

$aIniArray = parse_ini_file($inifile, true);


//Determine the server entry
switch (strtolower(php_uname('n')))
{
    case 'grieg':
        define('HOST_TYPE', 'grieg');
        break;
	case 'elgar':
	    define('HOST_TYPE', 'elgar');
	    break;
    case 'bizet':
        define('HOST_TYPE', 'bizet');
        break;
    default :
        define('HOST_TYPE', 'grieg');
}

//dbconnection
//require_once ($aIniArray[HOST_TYPE]['dbconnect']);
//$dbconn = pg_connect($connect);

define('CLASSES', $aIniArray[HOST_TYPE]['classes']);
define('LOGPATH', $aIniArray[HOST_TYPE]['log_path']);
define('PHPTOOLPATH', $aIniArray[HOST_TYPE]['phptools']);
define('ADMINURL', $aIniArray[HOST_TYPE]['adminurl']); 
define('DEVEMAIL', $aIniArray[HOST_TYPE]['devemail']);
define('ADMINEMAIL', $aIniArray[HOST_TYPE]['adminemail']);
define('BASEURL', $aIniArray[HOST_TYPE]['baseurl']);

$sLogFile = LOGPATH . 'resource_' . date('Ymd') . '.log';
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
