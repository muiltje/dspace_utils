<?php

/*
 * Change the owning collection of Igitur articles that also appear in other
 * collections. 
 * You do this if the original Igitur journal collection will be deleted (rather
 * than moved to "Igitur journal ceased") and you want to keep the articles
 * that have a Utrecht author.
 */

require_once '../import_init.php';
require_once CLASSES . 'Item.php';

$oItem = new Item();


//the collection id of the Igitur journal
$nIgiturCollectionId = 529;


//get all articles in the journal
$aCollectionItems = $oItem->getCollectionItems($nIgiturCollectionId);


$nCount = 0;
foreach ($aCollectionItems as $aOneItem) {
    $nItemId = $aOneItem['item_id'];

     //get all collections to which the article belongs
     $aCollectionData = $oItem->getItemCollections($nItemId);
        
     //we only care about items that have more than one collection
     if (count($aCollectionData) > 1) {
        $nTheOtherCollection = 0;
            
        foreach ($aCollectionData as $aOneCollection) {
            $nFoundCollId = $aOneCollection['collection_id'];
            if ($nFoundCollId != $nIgiturCollectionId) {
                $nTheOtherCollection = $nFoundCollId;
            }
        }
            
        //get the owning collection
        $aItemData = $oItem->getItemData($nItemId);
        $nOwningCollection = $aItemData['owning_collection'];
        //if Igitur is the owning one
        //change ownership to the other collection
        if ($nOwningCollection == $nIgiturCollectionId) {
            echo 'changing ' . $nItemId . ' to collection ' . $nTheOtherCollection . "\n";
            $nCount++;
            //$oItem->updateOwningCollection($nItemId, $nTheOtherCollection);
            //update last_modified
            //$sToday = date('Y-m-d H:i:s');
            //$oItem->updateLastModified($nItemId, $sToday);
        }
    }
}

echo $nCount . ' items to be done ' . "\n";
?>
