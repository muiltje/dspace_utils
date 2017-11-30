<?php
/**
 * Generate the derivatives for Digitized Objects
 * by calling the manifestation store webservice
 */

require_once 'init.php';
$oItemMetadata = new ItemMetadata();
$oHandle = new Handle();

$sStartTime = date('Y-m-d H:i:s');
echo 'Derivatives starting at ' . $sStartTime . "\n";


$oDigExport = new DigitizedExport($aMetadataFields); //metadatafields are set by init.php
$sManifestationStore = MANIFESTATION_STORE;
$sTempDerivFile = TEMPDERIV;

$bFullDump = 0;
$sHandlesToDo = '';

$aOptions = getopt("f");
if(isset($aOptions['f'])) {
    $bFullDump = 1;
}

wlog('      ', 'INF');

if ($bFullDump === 1) {
    $sHandlesToDo = '';
    $bFullDump = 1;
    wlog('Doing all derivatives today', 'INF');
    $aGenerateResult = $oDigExport->generateDerivatives($sManifestationStore, $bFullDump, $sHandlesToDo);
    logResults($aGenerateResult, 'derivatives generated');
}
else {
    wlog('Doing derivatives', 'INF');
    
    if (!file_exists($sTempDerivFile)) {
        wlog('No handles found; exiting', 'INF');
        wlog('      ');
        exit();
    }
    
    /* instead of sending the found handles as is,
     * try to determine if they are batch or regular items.
     * Send of the regular items first.
     * 
     * The easiest way is to find the accessrights field; Restricted Access means batch
     * A better way is to check the webservice on the workflow
     */

    
    $sFoundHandles = file_get_contents($sTempDerivFile);
    if (strlen($sFoundHandles) > 0) {
        $sRegularItems = '';
        $sBatchItems = '';
        
        $aHandlesToCheck = explode(',', $sFoundHandles);
        foreach ($aHandlesToCheck as $sHandle) {
            $sStatus = findBatchStatus(trim($sHandle), $oHandle, $oItemMetadata);
            if ($sStatus == 'batch') {
                $sBatchItems .= $sHandle . ',';
            }
            else {
                $sRegularItems .= $sHandle . ',';
            }
        }
        
        $sRegularItemsToDo = preg_replace('/,$/', '', $sRegularItems);
        $sBatchItemsToDo = preg_replace('/,$/', '', $sBatchItems);
        
        $bFullDump = 0;
        
        //echo 'Regular items: ' . $sRegularItemsToDo . "\n";
        //echo 'Batch items: ' . $sBatchItemsToDo . "\n";
        
        $aGenerateResultRegular = $oDigExport->generateDerivatives($sManifestationStore, $bFullDump, $sRegularItemsToDo);
        logResults($aGenerateResultRegular, 'derivatives generated for regular items ' . $sRegularItemsToDo);
        
        if (strlen($sBatchItemsToDo) > 2) {
            $aGenerateResultBatch = $oDigExport->generateDerivatives($sManifestationStore, $bFullDump, $sBatchItemsToDo);
            logResults($aGenerateResultBatch, 'derivatives generated for batch items ' . $sBatchItemsToDo);
        }
   }
    else {
        wlog('No handles found; exiting', 'INF');
        wlog('      ');
        exit();
    }
}
     
wlog('      ', 'INF');

//empty derivtemp.txt, maybe only if there are at least 5 minutes between start and end time?


echo 'Derivatives ending at ' . date('Y-m-d H:i:s') . "\n";


function findBatchStatus($sHandle, $oHandle, $oItemMetadata)
{
    $sBatchStatus = 'regular';
    
    $nWorkflowItemNumber = 0;
    $sWebserviceUrl = 'http://uws4.library.uu.nl/digworkflow/workflowservice/';
    
    $sHandleToUse = str_replace('-', '/', $sHandle);
    $aItemData = $oHandle->getItemIdDb($sHandleToUse);
	if (isset($aItemData['itemid'])) {
	    $nItemId = $aItemData['itemid'];
		$aDigiData = $oItemMetadata->getMetadataValue($nItemId, 271);
		if (isset($aDigiData['values'])) {
			$nWorkflowItemNumber = $aDigiData['values'][0];
        
			$sUrl = $sWebserviceUrl . 'status/' . $nWorkflowItemNumber;
			try {
				$fetchresult = file_get_contents($sUrl);
				$resultfixed=str_replace( array('&lt;','&gt;') ,array('<','>'),$fetchresult);  
				$xml = new SimpleXMLElement($resultfixed);
				$processtypefield = $xml->xpath(('//process_type'));
				if (count($processtypefield) > 0) {
					$processtype = (string) $processtypefield[0];
					if ($processtype == 'b') {
						$sBatchStatus = 'batch';
					}
					else {
						$sBatchStatus = 'regular';
					}
				}
				else {
					$sBatchStatus = 'regular';
				}
			}
			catch (Exception $e) {
            
			}
		}
		else {
			$sBatchStatus = 'regular';
		}
	}
    else {
		wlog('Could not find item id for ' . $sHandle, 'WARN');
	}
    return $sBatchStatus;
    
}

/**
 * Log the results of an action:
 * the error message if there is one, else the success message
 * 
 * @param array $aResults
 * @param string $sSuccessString
 * @return int
 */
function logResults($aResults, $sSuccessString)
{
    if (isset($aResults['error'])) {
        if (is_array($aResults['error'])) {
            foreach ($aResults['error'] as $aOneError) {
                wlog($aOneError, 'INF');
            }
        }
        else {
            wlog($aResults['error'], 'INF');
        }
    }
    else {
        wlog($sSuccessString, 'INF');
    }
    
    return 1;
}



