<?php

exit();

require_once '../import_init.php';

require_once CLASSES . 'Item.php';
require_once CLASSES . 'ItemMetadata.php';
require_once CLASSES . 'Handle.php';

require_once 'allCollections.php';

$oItem = new Item();
$oItemMetadata = new ItemMetadata();
$oHandle = new Handle();

$aCollectionGroup = $aIgiturJournalCollections; //set bij allCollections.php

//VERY IMPORTANT!
//AS SOON AS ALL HAS BEEN DONE change the check in Enrichment.php that
//currently filters out all items not issued in 2014


$nCount = 0;

foreach ($aCollectionGroup as $nCollectionId) {
    $aAllItems = getItems($nCollectionId);
    foreach ($aAllItems as $key=>$aItemData) {
        $nItemId = $aItemData['item_id'];
        $nMetadataFieldId = 228;
        $aHandleData = $oHandle->getHandle($nItemId);
        $sHandle = $aHandleData['handleid'];
    
        $sNewValue = 'URN:NBN:NL:UI:10-1874-' . $sHandle;
        //check if the item already has this field
        $aCheckData = $oItemMetadata->getMetadataValue($nItemId, $nMetadataFieldId);
        
        if (!isset($aCheckData['values']) || count($aCheckData['values']) < 1) {
            //no URN-NBN, so add it
            echo 'for item ' . $nItemId . ' I would add ' . $sNewValue . "\n";
            $nCount++;
            //$oItemMetadata->addMetadataValue($nItemId, $nMetadataFieldId, $sNewValue);
            
            //and change date modified to make sure it's harvested again
            //do not update date modified for digitized objects!
            //changeLastModified($nItemId, $oItem);
        }
        else {
            $sExistingUrn = $aCheckData['values'][0];
             if (preg_match('/1874-/', $sExistingUrn)) {
                //OK, nothing to do
                //echo 'OK. item ' . $nItemId . ' already has ' . $sExistingUrn . "\n";
            }
            elseif (preg_match('/1-/', $sExistingUrn)) {
                //also OK, so nothing to do
                //echo 'OK. item ' . $nItemId . ' already has ' . $sExistingUrn . "\n";
            }
            else {
                //wrong format, update
                $oItemMetadata->updateMetadataValue($nItemId, $nMetadataFieldId, $sNewValue);
                //echo 'changing ' . $sExistingUrn . ' to ' . $sNewValue . "\n";
                
                $nCount++;
                
                //and change date modified to make sure it's harvested again
                //do not update date modified for digitized objects!
                //changeLastModified($nItemId, $oItem);
            }
        }
    }
    
}

echo $nCount . ' items' . "\n";


function getItems($nCollectionId)
{
    $aItems = array();
    
    $query = 'select * from collection2item where collection_id=' . $nCollectionId;
    
    try {
        $result = pg_query($query);
    
        while ($row = pg_fetch_assoc($result)) {
            $aItems[] = $row;
        }
    
        pg_free_result($result);
    }
    catch (Exception $e) {
        $aItems['error'] = 'could not find items for collection ' . $nCollectionId . ': ' . $e->getMessage();
    }
    return $aItems;
    
}

function changeLastModified($nItemId, $oItem)
{
    //update last_modified
    $sToday = date('Y-m-d H:i:s');
    $oItem->updateLastModified($nItemId, $sToday);
   
    return;
}