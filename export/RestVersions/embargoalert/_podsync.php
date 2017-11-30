<?php

require_once 'alert_init.php';
//require_once CLASSES . 'Handle.php';

$mysqli;
$oHandle = new Handle();
$oAux = new AuxTables();

try {
    $mysqli = mysqli_connect(IGDISSHOSTPROD, $sUser, $sPwd, 'igitur');
}
catch (Exception $e) {
    //echo 'no connection';
    wlog('No connection to igdiss', '');
    exit();
}
echo "\n";

$aPodData = findIgdissPodData($mysqli);
foreach ($aPodData as $aOneItem) {
    $sHandle = $aOneItem['handle'];
    $nPrice = $aOneItem['price_pod'];
    $aItemIdData = $oHandle->getItemId($sHandle);
    $nItemId = $aItemIdData['itemid'];
    
    $aItemPodData = array(
        'handle' => $sHandle,
        'price' =>  $nPrice,
        'itemid' => $nItemId,
            );
   
    $aSync = $oAux->syncPodItem($aItemPodData);
    if (isset($aSync['add'])) {
        $aEmpty = $aSync['add'];
        print_r($aEmpty);
    }
}



function findIgdissPodData($mysqli)
{
    $aPodData = array();
    $sql = "SELECT handle,price_pod FROM igdiss WHERE price_pod > '0' AND LENGTH(handle) > 0 ORDER BY handle";
    $result = mysqli_query($mysqli, $sql);
    for ($count = 1; $row = mysqli_fetch_array ($result); ++$count) {
        $aPodData[] = $row;
    }
    
    return $aPodData;
}
?>
