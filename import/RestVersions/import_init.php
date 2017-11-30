<?php
date_default_timezone_set('Europe/Amsterdam');

$inifile = '/home/dspace/utils/newimport/import.ini';

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

define('ENVIRONMENT', $aIniArray[HOST_TYPE]['envtype']);
define('LOGPATH', $aIniArray[HOST_TYPE]['log_path']);
define('PHPTOOLPATH', $aIniArray[HOST_TYPE]['phptools']);
define('SIPBASE', $aIniArray[HOST_TYPE]['sipbase']);
define('IMPORTBASE', $aIniArray[HOST_TYPE]['importbase']); 
define('DISK_MARGIN', $aIniArray[HOST_TYPE]['disksafety']);
define('MAX_TSIZE', $aIniArray[HOST_TYPE]['maxtsize']);
define('DSCONFIG', $aIniArray[HOST_TYPE]['dspaceconfig']);
define('VENDOR_SEM', $aIniArray[HOST_TYPE]['vendor_semaphore']);
define('CLASSES', $aIniArray[HOST_TYPE]['classes']);
define('DIGWORKFLOW', $aIniArray[HOST_TYPE]['digwf_service']);
define('MANIFESTATION_STORE', $aIniArray[HOST_TYPE]['manif_store']);
define('BASEURL', $aIniArray[HOST_TYPE]['dspace_url']);
define('BCDOWNLOAD', $aIniArray[HOST_TYPE]['bcdownload']);
define('NOREPLY', $aIniArray[HOST_TYPE]['dspacemail']);
define('IMPORTER', $aIniArray[HOST_TYPE]['importer']);
define('SABINESERVICE', $aIniArray[HOST_TYPE]['sabineservice']);
define('SABINEKEY', $aIniArray[HOST_TYPE]['sabine_processkey']);
define('SCROLSERVICE', $aIniArray[HOST_TYPE]['scrolservice']);
define('THESISKEY', $aIniArray[HOST_TYPE]['thesis_processkey']);



$sLogFile = LOGPATH . 'import_' . date('Ymd') . '.log';
require_once (PHPTOOLPATH . 'utils/log/Logger.php');
$log = new Logger($sLogFile);


require_once 'import_aux.php';
require_once CLASSES . 'AuxTables.php';
require_once CLASSES . 'DigWorkflow.php';
require_once CLASSES . 'FileAndDisk.php';
require_once CLASSES . 'FixEncoding.php';


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

//er zijn n assetstores, opgesomd in dspace.cfg
//in dspace.cfg kun je aangeven welke voor inkomende bitstreams gebruikt moet worden
//dat doe je door assetstore.incoming te zetten
//het nummer dat daar staat verwijst naar het nummer in de opsomming
//als er 0 staat of als het uitgecommentarieerd is, dan gebruik je nummer 0
function getAssetStore()
{
    $nIncomingAssetStore = 0;
    $aLines = file(DSCONFIG);
    $sTextToFind = 'assetstore.incoming';
    
    foreach ($aLines as $sLine) {
        if (substr($sLine, 0, strlen($sTextToFind)) == $sTextToFind) {
            $sFound = trim($sLine);
            $nIncomingAssetStore = substr($sFound, -1);
        }
    }
    
    
    $sLineToFind = 'assetstore.dir';
    if ($nIncomingAssetStore > 0) {
        $sLineToFind .= '.' . $nIncomingAssetStore;
    }
    $sLineToFind .= ' = ';
    $sLength = strlen($sLineToFind);
    $sRelevantLine = '';
    
    foreach ($aLines as $sLine) {
        if (substr($sLine, 0, $sLength) == $sLineToFind) {
            $sRelevantLine = $sLine;
        } 
    }
    
    $nOffSet = strpos($sRelevantLine, '=');
    $sCurrentAssetStore = trim(substr($sRelevantLine, $nOffSet+2));
    
    return $sCurrentAssetStore;
    
    
}
?>
