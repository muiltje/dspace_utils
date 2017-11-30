<?php

require_once 'init.php';
require_once CLASSES . 'UkbStatistics.php';

$nFirstYear = 2005;
//$nMaxYears = 16;
$bVerbose = 0;
$bDoTableUpdate = 0;
$nThisYear = date("Y");


$oUStats = new UkbStatistics();



//get the statistics data
$aInitResults = $oUStats->initStatistics();

$oUStats->updateStatsTable($aFieldIds);

/*
//print_r($aInitResults);
$aCollections = $aInitResults['collections'];
$aCollectionIds = parseCollectionIds($aCollections);
//print_r($aCollectionIds);

$aCollectionStats = $oUStats->getCollectionStats($nFirstYear, $nThisYear);
print_r($aCollectionStats);
 * 
 */


function parseCollectionIds($aCollections) {
    $aCollectionIds = array();
    
    foreach ($aCollections as $aOneColl) {
        $sName = $aOneColl['text_value'];
        $nId = $aOneColl['resource_id'];
        $aCollectionIds[$nId] = $sName;
    }
    
    return $aCollectionIds;
}