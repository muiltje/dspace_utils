<?php

/* 
 * Find all student theses that have a date.available in 2014
 * For each of these, find the size of their bitstreams
 */

require_once '../import_init.php';

require_once CLASSES . 'Item.php';
require_once CLASSES . 'ItemMetadata.php';
require_once CLASSES . 'Bundle.php';
require_once CLASSES . 'Bitstream.php';

$oItem = new Item();
$oItemMetadata = new ItemMetadata();
$oBundle = new Bundle();
$oBitstream = new Bitstream();

$aAllThesesCollections = array(56, 69, 98, 99, 107, 111, 112, 137, 198);

$nTotalSize = 0;
//$nCalculatedTotal = 65143910371; //for all years
$nCalculatedTotal = 11491945404;

foreach ($aAllThesesCollections as $nCollectionId) {
    $aItems = $oItem->getCollectionItems($nCollectionId);
    //print_r($aItems);
    foreach ($aItems as $aOneItem) {
        $nItemId = $aOneItem['item_id'];
        
        //find date.issued (field 27)
        $nMetadataFieldId = 27;
        $aIssuedData = $oItemMetadata->getMetadataValue($nItemId, $nMetadataFieldId);
        $sDateIssued = $aIssuedData['values'][0];
        
        if (substr($sDateIssued, 0, 4) == '2014') {
            //find bundle
            $aItemBundles = $oBundle->getItemBundles($nItemId);
            
            if (count($aItemBundles) > 0 ) {
                foreach ($aItemBundles as $aOneBundle) {
                    //check if there are resource policies for the bundles
                    $nBundleId = $aOneBundle['bundle_id'];
            
                    //we don't care about the license bundle, because that's always open
                    $aBundleDetails = $oBundle->getBundleDetails($nBundleId);
                    if (isset ($aBundleDetails['name']) && $aBundleDetails['name'] != 'LICENSE') {
                        $aBundleBitstreams = $oBitstream->getBundleBitstreams($nBundleId);
                        foreach ($aBundleBitstreams as $aOneBitstream) {
                            $nBitstreamId = $aOneBitstream['bitstream_id'];
                            $aBitstreamData = $oBitstream->getBitstreamData($nBitstreamId);
                            //print_r($aBitstreamData);
                            $nBitstreamSize = $aBitstreamData['size_bytes'];
                            $nTotalSize += $nBitstreamSize;
                        }
                    }
                }
            }
        }
    }
}

echo 'Total thesis size is ' . $nTotalSize . "\n";