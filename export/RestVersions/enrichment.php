<?php
/* 
 * Now that Pure does updates at least once an hour, we have to do the enrichtments
 * more often as well.
 * 
 * So we take them out of newexport and put them in this separate script
 * 
 * Make sure these actions are only done on items in the UUR and UMCUR collections
 * 
 * This version: 2014-12-15
 */

require_once 'init.php';

$oHandle = new Handle();
$oItem = new Item();
$oItemMetadata = new ItemMetadata();

$sLastModifiedDate = date('Y-m-d H:i:s', strtotime("-1 hour"));

$aRepCollections = array(587, 617); //PROD
$sBaseUrl = HOME_URL;

$sStartLine = '===== Starting enrichment =====';
echo $sStartLine . "\n";
wlog($sStartLine, 'INF');

//get items modified since LastModifiedDate
$aModifiedItems = $oItem->getModifiedItems($sLastModifiedDate, 'short');
$sLine = 'Enrichment ' .  count($aModifiedItems) . ' items modified since ' . $sLastModifiedDate;
wlog($sLine, 'INF');

//get their collections, only proceed if they are in the right collections
foreach ($aModifiedItems as $aOneItem) {
    $nItemId = $aOneItem['item_id'];
     //check if item is in archive and not withdrawn
    if ($aOneItem['in_archive'] == 't' && $aOneItem['withdrawn'] == 'f') {
        $nOwningCollection = $aOneItem['owning_collection'];
        if (in_array($nOwningCollection, $aRepCollections)) {
            
            $aHandleData = $oHandle->getHandle($nItemId);
            $sHandleId = $aHandleData['handleid'];
            
            $sUrnNbn = 'URN:NBN:NL:UI:10-1874-' . $sHandleId;
            $nUrnFieldId = $aMetadataFields['identifier_urnnbn'];
            processField($nItemId, $nUrnFieldId, $sUrnNbn, $oItemMetadata);
            
            $sUrlFullText = $sBaseUrl . '/1874/' . $sHandleId;
            $nUrlFieldId = $aMetadataFields['url_fulltext'];
            processField($nItemId, $nUrlFieldId, $sUrlFullText, $oItemMetadata);
            $nJopFieldId = $aMetadataFields['url_jumpoff']; //do we still need this?
            processField($nItemId, $nJopFieldId, $sUrlFullText, $oItemMetadata);
        }
    }
}
$sLine = '=== finished enrichtment of modified items ===';
echo $sLine . "\n";
wlog($sLine, 'INF');

//@todo: do we want to use the rest version for adding and updating?
//we can't really use it for getting, because then we'd have to try all possible language values
function processField($nItemId, $nFieldId, $sNewValue, $oItemMetadata)
{
    $aExistingValues = $oItemMetadata->getMetadataValue($nItemId, $nFieldId);
    if (empty($aExistingValues)) {
         $oItemMetadata->addMetadataValue($nItemId, $nFieldId, $sNewValue, 1);
    }
    else {
        $oItemMetadata->updateMetadataValue($nItemId, $nFieldId, $sNewValue, 1);
    }
    
    //echo "\n\n";
}




