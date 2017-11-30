<?php

/* 
 * Save handle and all "identifier.other" fields for items in the repository
 * collections.
 * 
 * We do this so that we can find the external identifiers for items that have
 * been delete by the Pure-DSpace connector
 */

require 'init.php';

$oMetaAux = new MetadataAuxTable();
$oItemMetadata = new ItemMetadata();
$oHandle = new Handle();
$oItem = new Item();

$aFields = array(
    71, //identifierother
    304, //identifierpure
);

$sFile = 'items_to_save_' . date('Y_m_d') . '.php';
$aItemsToSave = array();


/*
 * Regular run, items that were added  today
 */
$counter = 0;
$sLastModifiedDate = date('Y-m-d', strtotime("-1 day"));
$aModifiedItems = $oItem->getModifiedItems($sLastModifiedDate);
foreach ($aModifiedItems as $aOneItem) {
    $nItemId = $aOneItem['item_id'];
    
    if ($aOneItem['in_archive'] == 't' && $aOneItem['withdrawn'] == 'f') {
    
        //check if the current vendor (151) is pure
        //if not, skip this item
        $aVendorData = $oItemMetadata->getMetadataValue($nItemId, 151);
        if (empty($aVendorData)) {
            $sLine = 'Item ' . $nItemId . ' : there is no vendor, I don\'t know what to do';
            wlog($sLine, 'INF');
        }
        else {
            $aVendorValues = $aVendorData['values'];
            if ($aVendorValues[0] != 'Pure') {
                //echo 'Not a PURE item, nothing to do' . "\n";
            }
            else {
                $aHandleData = $oHandle->getHandle($nItemId);
                $sHandle = $aHandleData['handle'];
                $nMetadataFieldId = 304;
                
                //check if there are items in ubuaux_metadata that have field
                //304 (identifier.pure) for this handle
                $aAuxValues = $oMetaAux->getMetadataAuxValue($sHandle, $nMetadataFieldId);
                if (!empty($aAuxValues)) {
                    //we already have we want; these fields don't change, 
                    //so nothing to do
                }
                else {
                    //see if the item has values for 304
                    $aExisting = $oItemMetadata->getMetadataValue($nItemId, $nMetadataFieldId);
                    if (!empty($aExisting['values'])) {
                        $aPureValues = $aExisting['values'];
                        
                        //add the values to ubuaux_metadata
                        //print_r($sHandle);
                        //if ($counter == 5) {
                            addAuxData($sHandle, $nMetadataFieldId, $aPureValues, $oMetaAux);
                        //}
                        
                        $counter++;
                     }
                }
                
            }
        }
    }
}
$sLine = 'Save_pure_basics: ' . $counter . ' items done ';
wlog($sLine, 'INF');



/**
 * Add to aux table
 * 
 * @param type $nItemId
 * @param type $nMetadataFieldId
 * @param type $aAuxData
 */
function addAuxData($sHandle, $nMetadataFieldId, $aAuxData, $oMetaAux) {
    foreach ($aAuxData as $nPlace => $sValueToSave) {
        $aFieldData = array(
                        'text_value' => $sValueToSave,
                        'text_lang' => 'nl_NL',
                        'place' => $nPlace,
                        'authority' => NULL,
                        'confidence' => '-1',
                        );
                   
        $aDataToSave = array();
        $aDataToSave['handle'] = $sHandle;
        $aDataToSave['metadatafieldid'] = $nMetadataFieldId;
        $aDataToSave['data'] = $aFieldData;
        
        //print_r($aDataToSave);
                    
        $oMetaAux->addMetadataAuxValues($aDataToSave);
    }

    return 1;
}
