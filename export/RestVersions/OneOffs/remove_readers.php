<?php

/* 
 * For the UvA readers: find all sub-communities and sub-subcommunities 
 * For each sub-subcommunity: find all collections
 * For each collection: find all items
 * Then: remove all items, remove all collections, remove all sub-subcommunities
 * Maybe also remove sub-communities
 * Removal of the parent communities can be done by hand
 * 
 * For the UU readers
 * There is only one sub-community
 * Find all collections in it
 * For each collections, find all items
 * Remove items and collections 
 * 
 * 
 * Alternatively: 
 * Find the items with 
 * $aReaderItems = array();
 * $aUUReaders = $oItemMetadata->findItemByMetadata(151, 'readers');
 * $aUvAReaders = $oItemMetadata->findItemByMetadata(151, 'readersuva');
 * Remove them, their parent collections and the collections' parent sub-community
 * We can then do the communities by hand
 */

require_once '../init.php';
require_once CLASSES . 'RestCollection.php';
require_once CLASSES . 'RestResourcePolicy.php';

$oCollection = new RestCollection();
$oItem = new RestItem();
$oItemMetadata = new RestItemMetadata();
$oBitstream = new RestBitstream();
$oResourcePolicy = new RestResourcePolicy();

$aReaderItems = array();
$aCollections = array();
$aSubCommunities = array();

$aUUReaders = $oItemMetadata->findItemByMetadata(151, 'readers');
foreach ($aUUReaders['itemids'] as $nItemId) {
	$aReaderItems['UU'][] = $nItemId;
}

$aUvAReaders = $oItemMetadata->findItemByMetadata(151, 'readersuva');
foreach ($aUvAReaders['itemids'] as $nItemId) {
	$aReaderItems['UvA'][] = $nItemId;
}

$nStart = 0;
$nNumber = 2;
$nEnd = $nStart + $nNumber;

for ($i=$nStart; $i < $nEnd; $i++) {
	$nUUItemId = $aReaderItems['UU'][$i];
	$aUUParents = findParents($nUUItemId, $oItem);
	if (!in_array($aUUParents['collection'], $aCollections)) {
		$aCollections[] = $aUUParents['collection'];
	}
	foreach ($aUUParents['communities'] as $nCommunityId) {
		if (!in_array($nCommunityId, $aSubCommunities)) {
			$aSubCommunities[] = $nCommunityId;
		}
	}

	$nUvAItemId = $aReaderItems['UvA'][$i];
	$aUvAParents = findParents($nUvAItemId, $oItem);
	if (!in_array($aUvAParents['collection'], $aCollections)) {
		$aCollections[] = $aUvAParents['collection'];
	}
	foreach ($aUvAParents['communities'] as $nCommunityId) {
		if (!in_array($nCommunityId, $aSubCommunities)) {
			$aSubCommunities[] = $nCommunityId;
		}
	}
}


echo  (count($aReaderItems['UU']) + count($aReaderItems['UvA'])) . ' items in readers' . "\n";
echo count($aCollections) . ' collections' . "\n";
echo count($aSubCommunities) . ' sub-communities' . "\n";

//$nTestItem = $aReaderItems[16];
//echo $nTestItem . "\n";
//$aRemoveResult = removeItem($nItemId, $oItem, $oBitstream, $oResourcePolicy);
//print_r($aRemoveResult);




function findParents($nItemId, $oItem)
{
	$aParentData = array();
	
	$aCollectionData = $oItem->getItemCollection($nItemId);
	//return $aCollectionData;
	$nParentCollectionId = $aCollectionData['parentCollectionList'][0]['id'];
	$aParentData['collection'] = $nParentCollectionId;
	$aParentCommunities = $aCollectionData['parentCommunityList'];
	foreach ($aParentCommunities as $aOneParent) {
		$nCommunityId = $aOneParent['id'];
		$aParentData['communities'][] = $nCommunityId;
	}
	
	return $aParentData;
}

/**
 * We can do this with the database, but it's really better to do this with the REST API
 * 
 * @param type $nItemId
 */
function removeItem($nItemId, $oItem, $oBitstream, $oResourcePolicy)
{
	$aResults = array();
	//find bitstreams
	$aBitstreams = $oBitstream->getItemBitstreamsRest($nItemId);
	
	foreach ($aBitstreams as $aOneBitstream) {
		$nBitstreamId = $aOneBitstream['id'];
		//find policies
		$sPoliciesFound = $oResourcePolicy->getBitstreamPolicies($nBitstreamId);
		$aPolicies = json_decode($sPoliciesFound, true);
		foreach ($aPolicies as $aOnePolicy) {
			$nPolicyId = $aOnePolicy['id'];
			//delete policy
			$aResults[] = $oResourcePolicy->deleteBitstreamPolicy($nBitstreamId, $nPolicyId);
		}
		//delete bitstream
		$aResults[] = $oBitstream->deleteBitstreamRest($nBitstreamId);
	}
	//remove item
	$aResults[] = $oItem->deleteItem($nItemId);
	
	return $aResults;
}