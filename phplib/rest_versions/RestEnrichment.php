<?php

require_once 'DatabaseConnector.php';
/**
 * Function to add or update metadata for items in DSpace
 *
 * @author muilw101
 */
class RestEnrichment {
    
    private $oItemMetadata;
    private $oItem;
    private $oHandle;
    private $aMetadataFields;
    private $oMetaAux;
    private $dbconn;
    
    /**
     * Construct and get metadata fields.
     * 
     * @param array $aMetadataFields
     */
    function __construct($aMetadataFields) {

        $this->oItemMetadata = new RestItemMetadata();
        $this->oItem = new RestItem();
        $this->oHandle = new RestHandle();
        $this->oMetaAux = new RestMetadataAuxTable();
        $this->aMetadataFields = $aMetadataFields;
         
        $oDC = new DatabaseConnector();
        $this->dbconn = $oDC->getConnection();
    }
    
    /**
     * Add the item page url as full-text link to items that don't have one
     * We put the data in two fields: fulltext and jumpoff
     * 
     * @param int $nFullDump
     * @param string $sBaseUrl
     * @return array
     */
    public function addItemPageUrl($nFullDump, $sBaseUrl)
    {
        $aDebug = array();
        
        $nFTMetadataFieldId = $this->aMetadataFields['url_fulltext'];
        $nJPMetadataFieldId = $this->aMetadataFields['url_jumpoff'];
        $aFTItemsToDo = $this->oItem->findMissingMetadata($nFTMetadataFieldId, $nFullDump);
        $aJPItemsToDo = $this->oItem->findMissingMetadata($nJPMetadataFieldId, $nFullDump);
        
        
        foreach ($aFTItemsToDo as $aOneItem) {
            $nItemId = $aOneItem['item_id'];
            //$aDebug['ftresult'][] = $this->addFullText($nItemId, $nFTMetadataFieldId, $sBaseUrl);
			$aDebug['ftresult'][] = $this->addFullText($nItemId, 'dc.identifier.urlfulltext', $sBaseUrl);
        }
        foreach ($aJPItemsToDo as $aOneItem) {
            $nItemId = $aOneItem['item_id'];
            //$aDebug['jpresult'][] = $this->addFullText($nItemId, $nJPMetadataFieldId, $sBaseUrl);
			$aDebug['jpresult'][] = $this->addFullText($nItemId, 'dc.identifier.urljumpoff', $sBaseUrl);

        }
        
        
        return $aDebug;
    }
    
    /**
     * Add a URN::NBN to items that don't have one
     * 
     * @param int $nFullDump
     * @return array
     */
    public function addUrnNbn($nFullDump)
    {
         $aDebug = array();
        
         //check for items without urn-nbn
         $nMetadataFieldId = $this->aMetadataFields['identifier_urnnbn'];
         $aItemsToDo = $this->oItem->findMissingMetadata($nMetadataFieldId, $nFullDump);
        
        //generate values and add them to the items
        foreach ($aItemsToDo as $aOneItem) {
            $nItemId = $aOneItem['item_id'];
            
            $sNewValue = 'URN:NBN:NL:UI:10-1874-';
            //value is based on handle
            $aHandleData = $this->oHandle->getHandle($nItemId);
            $sHandleId = $aHandleData['handleid']; //number without prefix
            $sNewValue .= $sHandleId;
            
            try {
                //$this->oItemMetadata->addMetadataValue($nItemId, $nMetadataFieldId, $sNewValue);
				$this->oItemMetadata->addMetadataValueRest($nItemId, 'dc.identifier.urnnbn', $sNewValue, null);
                $aDebug['result'][] = 'URN-NBN added for ' . $nItemId;
            }
            catch (Exception $e) {
                $aDebug['error'][] = 'adding URN-NBN failed for ' . $nItemId . ': ' . $e->getMessage();
            }
        } 
        
        return $aDebug;
    }
    
    /**
     * Add repository fields to items
     * 
     * Items can have more than one repository, so always check if all of them are present
     * 
     * @param array $aItems
     * @return array
     */
	public function addUbuRepository($aItems)
    {
        $oAux = new AuxTables();
        $aDebug = array();
    
        foreach ($aItems as $aOneItem) {
            $nItemId = $aOneItem['item_id'];
        
            $nMetadataFieldId = $this->aMetadataFields['repository_ubu'];
			$sMetadataFieldName = 'dc.repository.ubu';
        
            $aRepositoryData = $this->oItemMetadata->getMetadataValue($nItemId, $nMetadataFieldId);
            
            if (isset($aRepositoryData['error'])) {
                $aDebug['error'] = $aRepositoryData['error'];
                return $aDebug;
            }
            else {
                $aRepositoryNames = $oAux->findRepository($nItemId);
                if (isset($aRepositoryNames['error'])) {
                    $aDebug['error'] = $aRepositoryNames['error'];
                }
                else {
                    if (isset($aRepositoryNames['repositories'])) {
                        $aRepositories = $aRepositoryNames['repositories'];   
                        
                        //if an item has no repositories, add all of the found ones
                        if (!isset($aRepositoryData['values']) || count($aRepositoryData['values']) < 1) { //no repository found, add it
                            $sLine = 'no repositories found ';
                            foreach ($aRepositories as $sOneName) {
                                $sLine .= 'adding ' . $sOneName . ' - ';
                                //$aResult = $this->oItemMetadata->addMetadataValue($nItemId, $nMetadataFieldId, $sOneName);
								$aResult = $this->oItemMetadata->addMetadataValueRest($nItemId, $sMetadataFieldName, $sOneName, 'en');
								
                                if (isset($aResult['error'])) {
                                    $aDebug['error'] = $aResult['error'];
                                }
                            }   
                            $sLine .= "\n";
                            $aDebug['debug'][$nItemId] = $sLine;
                        }
                        else { //check if all found repositories are present
                            $aExistingValues = $aRepositoryData['values'];
                            $sLine = 'checking repositories ';
                            foreach ($aRepositories as $sOneName) {
                                if (!in_array($sOneName, $aExistingValues)) {
                                    $sLine .= ' adding ' . $sOneName . ' - ';
                                    //$aResult = $this->oItemMetadata->addMetadataValue($nItemId, $nMetadataFieldId, $sOneName);
									$aResult = $this->oItemMetadata->addMetadataValueRest($nItemId, $sMetadataFieldName, $sOneName, 'en');
                                    if (isset($aResult['error'])) {
                                        $aDebug['error'] = $aResult['error'];
                                    }
                                }
                                else {
                                    //$sLine .= $sOneName . ' is already present - ';
                                }
                            }
                            $sLine .= "\n";
                            $aDebug['debug'][$nItemId] = $sLine;
                            //NB: do not remove repositories; we assume extra ones have been added by hand
                        }
                    }
                    else {
                          $aDebug['error'] = 'no repositories found in addUbuRepository';
                    }
                }
            }
           }
  
    
        return $aDebug;
    }
    
    
    /**
     * Find all author strings with | in them, split the string into name and DAI.
     * These are authors of items that have been imported from elsewhere.
     * There is no way to check if the DAI is correct, so we trust that it is
     * and write it to the database.
     * 
     * Update the text_value field with the name
     * Update the authority field with the DAI
     * Update the confidence field 
     * 
     * @return array
     */
    public function correctAuthors()
    {
        $aDebug = array();
        
        $aContributorFields = array(5, 166, 221);
        
        foreach ($aContributorFields as $nFieldId) {
            $aItemsToCorrect = $this->oItemMetadata->findUncorrectedAuthors($nFieldId);
        
            foreach ($aItemsToCorrect as $aOneItem) {
                $sOrgAuthorValue = $aOneItem['text_value'];
                $nValueId = $aOneItem['metadata_value_id'];
                $sOrgAuthorityValue = $aOneItem['authority'];
                $aSplitValues = splitAuthorString($sOrgAuthorValue);
                $sNewAuthorValue = $aSplitValues['authorname'];
                $sNewAuthorityValue = $sOrgAuthorityValue;
                $nConfidence = 600;
        
                //if there already is an authority value, do not overwrite it
                if ($sOrgAuthorityValue != NULL && $sOrgAuthorityValue != '') {
                    $sNewAuthorityValue = $sOrgAuthorityValue;
                }
                else {
                    $sNewAuthorityValue = $aSplitValues['DAI'];
                    $nConfidence = 600;
                    //in case the new authority value is also empty
                    if ($sNewAuthorityValue == '') {
                        $nConfidence = -1;
                    }
                }
          
                $aNewValues = array(
                    'metadata_value_id' => $nValueId,
                    'author_name' => $sNewAuthorValue,
                    'authority' => $sNewAuthorityValue,
                    'confidence' => $nConfidence,
                );
                $aUpdateResult = $this->oItemMetadata->updateAuthorRecord($aNewValues);
                if (isset($aUpdateResult['error'])) {
                    $aDebug['error'][] = $aUpdateResult['error'];
                }
            }
        }
        return $aDebug;
    }    
    
   
 
	private function addFullText($nItemId, $sMetadataField, $sBaseUrl)
	{
		$aDebug = array();
		//find the handle
        $aHandleData = $this->oHandle->getHandle($nItemId);
        $sHandle = $aHandleData['handle']; //with prefix
        if ($sHandle == '') {
            $aDebug['error'] = 'No handle found for ' . $nItemId;
        }
        
        $sNewValue = $sBaseUrl . '/' . $sHandle;
        try {
            //$this->oItemMetadata->addMetadataValue($nItemId, $nMetadataFieldId, $sNewValue);
			$this->oItemMetadata->addMetadataValueRest($nItemId, $sMetadataField, $sNewValue, null);
            $aDebug['result'] = 'Item Page URL added: ' . $sNewValue;
        }
        catch (Exception $e) {
            $aDebug['error'] = 'adding Item Page URL failed: ' . $e->getMessage();
        }
        
        
        return $aDebug;
   
	}
}


