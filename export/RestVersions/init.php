<?php
date_default_timezone_set('Europe/Amsterdam');

$inifile = '/home/dspace/utils/newexport/export.ini';

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


//classpath
define('CLASSES', $aIniArray[HOST_TYPE]['classes']);
define('READER_URL', $aIniArray[HOST_TYPE]['reader_url']);
define('MANIFESTATION_STORE', $aIniArray[HOST_TYPE]['manif_store']);
define('RESOLVER_EXPORT_PATH', $aIniArray[HOST_TYPE]['resolver_path']);
define('RESOLVER_URL', $aIniArray[HOST_TYPE]['resolver_url']);
define('INDEX_QUEUE', $aIniArray[HOST_TYPE]['index_queue']);
define('HOME_URL', $aIniArray[HOST_TYPE]['home_url']);
define('LOGPATH', $aIniArray[HOST_TYPE]['log_path']);
define('PHPTOOLPATH', $aIniArray[HOST_TYPE]['phptools']);
define('EXPORTBASE', $aIniArray[HOST_TYPE]['export_base']);
define('ERRORPATH', $aIniArray[HOST_TYPE]['error_path']);
define('ENVIRONMENT', $aIniArray[HOST_TYPE]['environment']);
define('TEMPDERIV', $aIniArray[HOST_TYPE]['temp_deriv']);
define('SCROL_SERVICE', $aIniArray[HOST_TYPE]['scrol_service']);
define('REPORT_BASE', $aIniArray[HOST_TYPE]['report_base']);

$sLogFile = LOGPATH . 'export_' . date('Ymd') . '.log';
require_once (PHPTOOLPATH . 'utils/log/Logger.php');
$log = new Logger($sLogFile);

require_once (CLASSES . 'Item.php');
require_once (CLASSES . 'ItemMetadata.php');
require_once (CLASSES . 'MetadataExport.php');
require_once (CLASSES . 'DigitizedExport.php');
require_once (CLASSES . 'PDF.php');
require_once (CLASSES . 'Bitstream.php');
require_once (CLASSES . 'AuxTables.php');
require_once (CLASSES . 'MetadataAuxTable.php');
require_once (CLASSES . 'Enrichment.php');
require_once (CLASSES . 'Handle.php');

$aMetadataFields = array(
    'identifier_ddid' => 59,
    'identifier_issn' => 64, 
    'identifier_urnnbn' => 228,
    'relation_ispartofissn' => 90, 
    'source_alephid' => 127,
    'repository_ubu' => 218,
    'service_pod' => 279,
    'id_digitization' => 271,
    'partofalephid' => 265,
    'date_issued' => 27,
    'title' => 143,
    'partofvolume' => 162,
    'startpage' => 164,
    'accessrights' => 161,
    'numberofpages' => 264,
    'url_fulltext' => 158,
    'url_jumpoff' => 159,
	'type_content' => 146,
);


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
