<?php

/* 
 * Some metadatafields that we need are not available in PURE. To make sure
 * that we don't lose the information, we copy those fields to an ubuaux
 * table. Then, when a DSpace items has been updated, we copy the information
 * back from ubuaux to the item.
 * 
 * Run this script before we do any enrichment, so before the daily_export.sh!
 */
/*
 * get modified items for utrecht repository collection
 *(owned and mapped)
 *for each item
 * for each metadatafield
 *see if the metadatafield has an entry in the ubuaux table

 *if ubuaux==yes and item==no
 *   add ubuaux field to item
 * if ubuaux==no and item==yes
 *  add item field to ubuaux
 * if ubuaux==no and item==no
 *  nothing to do; ddid and urnnbn will be added to the item in the enrichment
 *   take modified items 
 *  from the last two days (so that we get the case ubuaux==no and item==yes
 * if ubuaux==yes and item==yes
 *  for ddid, urnnbn: overwrite item with ubuaux
 *  for publisherurl, subjectdiscipline: add ubuaux to item (might want to check for duplication)
 *  @authors: this is where it gets complicated. Corrections to authornames
 *  are done in PURE. We want to add DAI but there is no way to do that in 
 *  PURE. So the only thing we can do is overwrite item with ubuaux, thus
 *  undoing the changes made in PURE. This is acceptable to Guido and Sita.
 * 
 */

require 'init.php';

$oMetaAux = new MetadataAuxTable();
$oItemMetadata = new ItemMetadata();
$oHandle = new Handle();
$oItem = new Item();
$oEnrichment = new Enrichment($aMetadataFields);


$aFieldsToSave = array(
    76 => 'dc.publisher', 
    77 => 'dc.publisher.publisherurl', 
	129 => 'dc.subject.discipline', 
     31 => 'dc.description.abstract', 
     59 => 'dc.identifier.ddid', 
	 60 => 'dc.identifier.doi', 
    228 => 'dc.identifier.urnnbn', 
      5 => 'dc.contributor.author', 
    221 => 'dc.contributor.editor', 
    166 => 'dc.contributor.advisor', 
    161 => 'dc.rights.accessrights', 
    193 => 'dc.date.embargo', 
);

$aFieldsToOverwrite = array(59, 228);
$aFieldsToAdd = array(31, 76, 77, 129, 161, 193);
//$aContributorFields = array(5, 221,166);


$sLastModifiedDate = date('Y-m-d', strtotime("-2 day"));
echo $sLastModifiedDate . "\n";
$aModifiedItems = $oItem->getModifiedItems($sLastModifiedDate);

foreach ($aModifiedItems as $aOneItem) {
    $nItemId = $aOneItem['item_id'];
    
    //check if item is in archive and not withdrawn
    if ($aOneItem['in_archive'] == 't' && $aOneItem['withdrawn'] == 'f') {
    
        //check if the current vendor (151) is pure
        //if not, skip this item
        $aVendorData = $oItemMetadata->getMetadataValue($nItemId, 151);
        if (empty($aVendorData)) {
            //echo "There is no vendor, I don't know what to do. \n";
            $sLine = 'Item ' . $nItemId . ' : there is no vendor, I don\'t know what to do';
            wlog($sLine, 'INF');
        }
        else {
            $aVendorValues = $aVendorData['values'];
            if ($aVendorValues[0] != 'Pure') {
                //echo $nItemId . ' is not a PURE item, nothing to do' . "\n";
                $sLine = $nItemId . ' is not a PURE item, nothing to do';
                wlog($sLine. 'INF');
            }
            else {
                $aHandleData = $oHandle->getHandle($nItemId);
                $sHandle = $aHandleData['handle'];
                //echo $sHandle . "\n";

                foreach ($aFieldsToOverwrite as $nMetadataFieldId => $sFieldName) {
                    //echo $nMetadataFieldId . "\n";
    
                    //echo 'UbuAux' . "\n";
                    $aAuxValues = $oMetaAux->getMetadataAuxValue($sHandle, $nMetadataFieldId);
                    //print_r($aAuxValues);    
    
                    //echo 'Pure' . "\n";
                    $aPureValues = array();
                    $aExisting = $oItemMetadata->getMetadataValue($nItemId, $nMetadataFieldId);
                    if (!empty($aExisting['values'])) {
                        $aPureValues = $aExisting['values'];
                        //print_r($aPureValues);
                    }
    
                    //if field in Pure: overwrite, otherwise add
                    if (!empty($aAuxValues)) {
                        if (empty($aPureValues)) {
                            //echo 'Auxvalues but no Purevalues; adding field'. "\n";
                            $sLine = 'Item ' . $nItemId . ' : auxvalues but no Purevalues; adding ' . $nMetadataFieldId ;
                            wlog ($sLine, 'INF');
                            //$aResult = addAuxData($nItemId, $nMetadataFieldId, $aAuxValues, $oItemMetadata);
							$aResult = addAuxDataRest($nItemId, $sFieldName, $aAuxData, $oItemMetadata);
                            print_r($aResult);
                        }
                        else {
                            //echo 'Pure values present but will be overwritten' . "\n";
                            $sLine = 'Item ' . $nItemId . ' : Purevalues present but will be overwritten for ' . $nMetadataFieldId ;
                            wlog($sLine, 'INF');
                            //$aResult = updateItemWithAux($nItemId, $nMetadataFieldId, $aAuxValues, $oItemMetadata);
							$aResult = updateItemWithAuxRest($nItemId, $sFieldName, $aAuxData, $oItemMetadata);
                            print_r($aResult);
                        }
                    }
                    else {
                        //do nothing
                        //echo 'No ubuaux values yet' . "\n";
                    }
                    //echo "\n";
                }
                
                //only add if there is no Pure value
                foreach ($aFieldsToAdd as $nMetadataFieldId) {
                    $aPureValues = array();
                    $aExisting = $oItemMetadata->getMetadataValue($nItemId, $nMetadataFieldId);
                    if (!empty($aExisting['values'])) {
                        $aPureValues = $aExisting['values'];
                    }
                    //only do something if there are no PureValues
                    if (empty($aPureValues)) {
                        $aAuxValues = $oMetaAux->getMetadataAuxValue($sHandle, $nMetadataFieldId);  
                        if (!empty($aAuxValues)) {
                            $sLine = 'Item ' . $nItemId . ' : auxvalues but no Purevalues; adding for field ' . $nMetadataFieldId;
                            wlog($sLine, 'INF');
                            addAuxData($nItemId, $nMetadataFieldId, $aAuxValues, $oItemMetadata);    
                        }
                    }
                    else {
                        $sLine = 'Pure values present, not adding ' . $nMetadataFieldId . ' to ' . $nItemId;
                        wlog($sLine, 'INF');
                    }
                        
                }
                
                //update last.modified, so it will be harvested again
                $sToday = date('Y-m-d H:i:s');
                $oItem->updateLastModified($nItemId, $sToday);
            } 

        }
    }
}

function updateItemWithAux($nItemId, $nMetadataFieldId, $aAuxData, $oItemMetadata)
{
    $aResults = array();
    //updateMetadataValue($nItemId, $nMetadataFieldId, $sNewValue, $nPlace=-1)
    foreach ($aAuxData as $aOneAux) {
        $sTextValue = $aOneAux['text_value'];
        $aResults[] = $oItemMetadata->updateMetadataValue($nItemId, $nMetadataFieldId, $sTextValue);
    }
    
    return $aResults;
}

function updateItemWithAuxRest($nItemId, $sMetadataFieldName, $aAuxData, $oItemMetadata) {
    $aResults = array();
    //updateMetadataValue($nItemId, $nMetadataFieldId, $sNewValue, $nPlace=-1)
    foreach ($aAuxData as $aOneAux) {
        $sTextValue = $aOneAux['text_value'];
        //$aResults[] = $oItemMetadata->updateMetadataValue($nItemId, $nMetadataFieldId, $sTextValue);
		$aResults[] = $oItemMetadata->updateMetadataValueRest($nItemId, $sMetadataFieldName, $sTextValue, 'en');
    }
    
    return $aResults;
	
}

/**
 * Normal way of adding a metadata field
 * We use this for fields where we don't care about authority, confidence
 * or language information
 * 
 * @param type $nItemId
 * @param type $nMetadataFieldId
 * @param type $aAuxData
 */
function addAuxData($nItemId, $nMetadataFieldId, $aAuxData, $oItemMetadata) {
    $aResults = array();
    
    foreach ($aAuxData as $aOneAux) {
         $sTextValue = $aOneAux['text_value'];
         $aResults[] = $oItemMetadata->addMetadataValue($nItemId, $nMetadataFieldId, $sTextValue);
    }
    
    return $aResults;
}

function addAuxDataRest($nItemId, $sMetadataFieldName, $aAuxData, $oItemMetadata) {
    $aResults = array();
    
    foreach ($aAuxData as $aOneAux) {
         $sTextValue = $aOneAux['text_value'];
 		$aResults[] = $oItemMetadata->addMetadataValueRest($nItemId, $sMetadataFieldName, $sTextValue, 'en');
    }
    
    return $aResults;
}