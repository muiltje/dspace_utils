<?php

/*
 * Move Igitur journal items from a "living" to a "ceased" collection
 */


require_once '../import_init.php';
require_once CLASSES . 'Item.php';
require_once CLASSES . 'Handle.php';
require_once CLASSES . 'ItemMetadata.php';
require_once CLASSES . 'Collection.php';
require_once CLASSES . 'Community.php';

$oItem = new Item();
$oHandle = new Handle();
$oItemMetadata = new ItemMetadata();
$oCollection = new Collection();
$oCommunity = new Community();

//NB: collection-ids amd community-ids can differ between environments!
//so always check these numbers before running this script
$nOldCommunityId = 36; 
$nOldCollectionId = 132; 
$nNewCommunityId = 55; 
$nNewCollectionId = 1100;  
$aWeirdData = array();
$aItemsMoved = array();
$aMappedItems = array();

//get all items for the "old" collection
$aCollectionItems = $oItem->getCollectionItems($nOldCollectionId);

echo count($aCollectionItems) . ' items in the old collection';


foreach ($aCollectionItems as $aOneItem) {
    $nItemId = $aOneItem['item_id'];
    
    //check if the item is also mapped to other collections
    $aItemCollections = $oItem->getItemCollections($nItemId);
    if (count($aItemCollections) > 1) {
        $aMappedItems[$nItemId] = $aItemCollections;
    }
    
    
    //check if the old collection is the owning collection
    $aItemData = $oItem->getItemData($nItemId);
    $aHandleData = $oHandle->getHandle($nItemId);
    $sHandle = $aHandleData['handleid'];
    $nOwningCollection = $aItemData['owning_collection'];
    if ($nOwningCollection == $nOldCollectionId) {
        //echo 'Item ' . $nItemId . ' is owned by ' . $nOwningCollection;
        //echo ' and should be moved to ' . $nNewCollectionId;
        //echo "\n";
                
	    $sNewUrl = BASEURL . '/handle/1874/' . $sHandle;
        echo 'the new fulltexturl is ' . $sNewUrl . "\n\n";
        
		/*
	    //move
        $oItem->updateOwningCollection($nItemId, $nNewCollectionId);
        //echo 'New URL will be ' . $sNewUrl . "\n";
        
        //update collection2item
        $oCollection->updateCollectionToItem($nOldCollectionId, $nNewCollectionId, $nItemId);
        
        //update communities2item and community2item
        //community2item is a view and will be updated automatically
        $oCommunity->updateCommunitiesToItem($nOldCommunityId, $nNewCommunityId, $nItemId);
       
        //now change urljumpoff to new URL
        $oItemMetadata->updateMetadataValue($nItemId, 159, $sNewUrl);
        
        //add urlfulltext (metadata_field_id:158)
        $oItemMetadata->addMetadataValue($nItemId, 158, $sNewUrl);
		 
		//and add a link to the Creative Commons License if needed
		$sLicenseLink = 'http://creativecommons.org/licenses/by/3.0/';
		$oItemMetadata->addMetadataValue($nItemId, 119, $sLicenseLink);
	
	 
        //update last_modified
        $sToday = date('Y-m-d H:i:s');
        $oItem->updateLastModified($nItemId, $sToday);
                
        //and remove publisherurl if desired
        $oItemMetadata->deleteMetadataValue($nItemId, 77);
		 * 
		 */
	
	   $aItemsMoved[] = array('handle' => '1874/' . $sHandle, 'itemid' => $nItemId);
       
    }
    else {
        //if the old collection is not the owning collection, report the item
        echo 'Item ' . $nItemId . ' is owned by ' . $nOwningCollection . "\n";

        if ($nOwningCollection == 587) {
            //item belongs to UU rep; unmap from oldcollection
            $oCollection->purgeCollectionToItem($nOldCollectionId, $nItemId);
            //and remove publisherurl
            $oItemMetadata->deleteMetadataValue($nItemId, 77);
        }
        else {
            $aWeirdData[] = array(
                'handle' => '1874/' . $sHandle, 
                'itemid' => $nItemId,
                'owning' => $nOwningCollection,
            );
        }
    }
}

//print_r($aWeirdData);

//if desired, change the fulltext url for the "weird" items
foreach ($aWeirdData as $aOneWeirdItem) {
    $sHandle = $aOneWeirdItem['handle'];
    $nItemId = $aOneWeirdItem['itemid'];
    $sNewUrl = BASEURL . '/handle/1874/' . $sHandle;
    
    
    //$oItemMetadata->updateMetadataValue($nItemId, 159, $sNewUrl);
    
    //and we may have to remove the item from the old collection and community
    //$oCollection->purgeCollectionToItem($nOldCollectionId, $nItemId);
    //$aCollPurge = $oCollection->purgeCollectionToItem($nOldCollectionId, $nItemId);
    //print_r($aCollPurge);
    
    //$oCommunity->purgeCommunitiesToItem($nOldCommunityId, $nItemId);
    //$aCommPurge = $oCommunity->purgeCommunitiesToItem($nOldCommunityId, $nItemId);
    //print_r($aCommPurge);
    
    //and remove publisherurl (field 77)
    //$oItemMetadata->deleteMetadataValue($nItemId, 77);
}

//if desired, move mapped items to the collection they used to be mapped to
//that is: change owning collection that collection
/*
foreach ($aMappedItems as $nItemId => $aMappedCollections) {
    ///print_r($aMappedCollections);
    foreach ($aMappedCollections as $aOneCollection) {
        $nCollectionId = $aOneCollection['collection_id'];
        if ($nCollectionId != $nOldCollectionId) {
            $nMoveToCollectionId = $nCollectionId;
            echo 'for item ' . $nItemId . ' the new owner is ' . $nMoveToCollectionId . "\n";
            //$oItem->updateOwningCollection($nItemId, $nMoveToCollectionId);
            
            //and remove publisherurl (field 77)
            //$oItemMetadata->deleteMetadataValue($nItemId, 77);
        }
    }
}
 * 
 */



echo count($aItemsMoved) . ' items moved' . "\n";
echo count($aMappedItems) . ' items belonging to several collections ' . "\n";
//print_r($aMappedItems);
//echo 'weird: ';
//print_r($aWeirdData);
echo "\n done \n";

