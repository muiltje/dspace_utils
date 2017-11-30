<?php

require_once '../import_init.php';

require_once CLASSES . 'Bitstream.php';

$oBitstream = new Bitstream();

$aBitstreams = $oBitstream->getOrphanBitstreams();

echo count($aBitstreams) . "\n";

print_r($aBitstreams);

/*
foreach ($aBitstreams as $aOneBitstream) {
    $nBitstreamId = $aOneBitstream['bitstream_id'];
    $nStoreNumber = $aOneBitstream['store_number'];
    $nInternalId = $aOneBitstream['internal_id'];
    
    $sFilePath = $oBitstream->getBitstreamPath($nInternalId, $nStoreNumber);
    
    $aDelete = $oBitstream->deleteBitstream($nBitstreamId, $sFilePath);
    
    print_r($aDelete);
}
 * 
 */
