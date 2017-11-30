<?php
/* 
 * If a dissertation with Printing on Demand is updated from Pure, it loses the
 * information needed for PoD. 
 * 
 * Update from Pure can come in at any time of the day, so we need a Pod restore
 * script that can run at least once an hour
 */
require 'init.php';

$oPdf = new PDF();
$oBitstream = new Bitstream();
$oItemMetadata = new ItemMetadata();
$oItem = new Item;
$oHandle = new Handle();
$oAux = new AuxTables();

$sStartLine = '===== Starting pod restore ' . date('ymdh') . ' ====';
echo $sStartLine . "\n";
wlog($sStartLine, 'INF');

$sLastModifiedDate = date('Y-m-d h:i:s', strtotime("-1 hour"));
$aModifiedItems = $oItem->getModifiedItems($sLastModifiedDate, 'y');

foreach ($aModifiedItems as $aOneItem) {
	$nItemId = $aOneItem['item_id'];
	
	//temporary debug
	//echo 'Pod restoring item ' . $nItemId . "\n";
	
	//find the pdf
	$aPdfData = $oPdf->findDissertationPdf($nItemId);
	
	if (isset($aPdfData['error'])) {
		
	}
	elseif (count($aPdfData) < 1) {
		$sLine = 'no pdf found for ' . $nItemId;
		wlog($sLine, 'ERR');
	}
	elseif (count($aPdfData) > 1) {
		$sLine = 'multiple pdfs found for ' . $nItemId;
		wlog($sLine, 'INF');
	}
	else {
		$sInternalId = $aPdfData[0]['internal_id'];
		$sStoreNumber = $aPdfData[0]['store_number'];
		$nOldPageCount = $aPdfData[0]['nrofpages'];
		//$sSequenceId = $aPdfData[0]['sequence_id'];
		
		$sFilePath = $oBitstream->getBitstreamPath($sInternalId, $sStoreNumber);
		$nNumberOfPages = $oPdf->countPages($sFilePath);
    
		if ($nNumberOfPages > 0) {
			if ($nOldPageCount > 0) {
				//$oItemMetadata->updateMetadataValue($nItemId, 264, $nNumberOfPages, $sSequenceId);
				$oItemMetadata->updateMetadataValueRest($nItemId, 'dc.relation.ispartofnumberofpages', $nNumberOfPages, null);
			}
			else {
				//$oItemMetadata->addMetadataValue($nItemId, 264, $nNumberOfPages, $sSequenceId);
				$oItemMetadata->addMetadataValueRest($nItemId, 'dc.relation.ispartofnumberofpages', $nNumberOfPages, null);
			}
		}
		else {
			echo 'No pages found for item ' . $nItemId . "\n";
		}
	
		$aHandleData = $oHandle->getHandle($nItemId);
		$sHandle = $aHandleData['handle'];
		$aPodAuxData = $oAux->findItemPod($sHandle);
		if (!empty($aPodAuxData)) {
			//check if service field is present (279)
			$aServiceData = $oItemMetadata->getMetadataValue($nItemId, 279);
			//if not, add it
			if (empty($aServiceData)) {
				echo 'adding' . "\n";
				$oItemMetadata->addMetadataValue($nItemId, 279, 'yes');
			}
		}
	}		
}

$sEndLine = '==== End of Pod Restore for ' . $sLastModifiedDate . ' ====';
wlog($sEndLine);