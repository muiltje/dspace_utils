<?php

require_once '../init.php';

$oAux = new AuxTables();

$nCollectionId = 258;

$aHandleData = $oAux->getCollectionHandle($nCollectionId);

print_r($aHandleData);