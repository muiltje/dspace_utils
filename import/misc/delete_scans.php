<?php
/* 
 * Delete a scan from an item that has more than 400 of them.
 * For such items, deleting via DSpace GUI doesn't work.
 */
require_once '../import_init.php';
require_once CLASSES . 'Bitstream.php';

$oBitstream = new Bitstream();

$nItemId = 241182;

$sFileName = '0094890025.tif';

exit();

/*
//find the bitstreamid etc
$aBitstreamData = $oBitstream->getBitstreamByName($sFileName);
//print_r($aBitstreamData);

$nBitstreamId = $aBitstreamData['bitstream_id'];
$sInternalId = $aBitstreamData['internal_id'];
$sAssetStore = $aBitstreamData['store_number'];

$sFilePath = $oBitstream->getBitstreamPath($sInternalId, $sAssetStore);

//remove from tables bitstream, bundletobitstream and filesystem
$aRemoveResult = $oBitstream->deleteBitstream($nBitstreamId, $sFilePath);
print_r($aRemoveResult);

//adjustment of bitstreamorder etc is done through dspace-admin,
//to avoid duplication of that code here
 * 
 */
