<?php

/* 
 * If an Igitur Journal is about to be removed, you will want to unmap
 * any items from other collections that map to it.
 */


require_once '../import_init.php';
require_once CLASSES . 'Item.php';
require_once CLASSES . 'Collection.php';

$oItem = new Item();
$oColl = new Collection();


//the collection id of the Igitur journal
$nIgiturCollectionId = 123;
$nUUCollection = 587;

//get all articles in the journal
$aCollectionItems = $oItem->getCollectionItems($nIgiturCollectionId);


$nCount = 0;

echo count($aCollectionItems) . "\n";

foreach ($aCollectionItems as $aOneItem) {
    $nItemId = $aOneItem['item_id'];
    
    $aItemData = $oItem->getItemData($nItemId);
    $nOwningCollection = $aItemData['owning_collection'];
    
    if ($nOwningCollection == $nUUCollection) {
        $nCount++;
        
        //unmap from Igitur Journal
        //$oColl->purgeCollectionToItem($nIgiturCollectionId, $nItemId);
        
    }
}

echo 'UU pubs: ' . $nCount . "\n";