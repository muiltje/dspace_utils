<?php

$inifile = '/home/dspace/utils/newexport/embargoalert/alert.ini';

$aIniArray = parse_ini_file($inifile, true);


//Determine the server entry
switch (strtolower(php_uname('n')))
{
    case 'grieg.library.uu.nl':
        define('HOST_TYPE', 'grieg');
        break;
	case 'elgar.library.uu.nl':
		define('HOST_TYPE', 'elgar');
		break;
	case 'bizet.library.uu.nl':
		define('HOST_TYPE', 'bizet');
		break;
    default :
        define('HOST_TYPE', 'elgar');
}

//dbconnection
require_once ($aIniArray[HOST_TYPE]['dbconnect']);
$dbconn = pg_connect($connect);

//classpath
define('CLASSES', $aIniArray[HOST_TYPE]['classes']);
define('LOGPATH', $aIniArray[HOST_TYPE]['log_path']);
define('PHPTOOLPATH', $aIniArray[HOST_TYPE]['phptools']);
define('ENV', $aIniArray[HOST_TYPE]['environment']);
define('IGDISSSERVICE', $aIniArray[HOST_TYPE]['igdissservice']);
define('SCROLSERVICE', $aIniArray[HOST_TYPE]['scrolservice']);
$sLogFile = LOGPATH . 'embargoalert_' . date('Ymd') . '.log';
require_once (PHPTOOLPATH . 'utils/log/Logger.php');
$log = new Logger($sLogFile);

require_once (CLASSES . 'AuxTables.php');
require_once (CLASSES . 'ItemMetadata.php');
require_once (CLASSES . 'Handle.php');

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

