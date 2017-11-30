<?php
require_once 'DatabaseConnector.php';
require_once 'RestConnector.php';
        

/**
 * A class for handling metadata about items
 *
 * @author muilw101
 */

class RestItemMetadata {
    private $dbconn;
    private $oRest;
   
    
    public function __construct() {
        $oDC = new DatabaseConnector();
        $this->dbconn = $oDC->getConnection();
        $this->oRest = new RestConnector();
    }
	
	
    
	/**
	 * Get all available metadata for the given id.
	 * Use this when you want several (or all) metadata fields.
	 * 
	 * @param int $nItemId
	 * @return array
	 */
    public function getAllMetadata($nItemId)
	{
		$sService = 'items/' . $nItemId . '/metadata';
		$aFoundMetadata = $this->oRest->doRequest($sService, 'GET', '');
		$sResponse = $aFoundMetadata['response'];
		$aMetadata = json_decode($sResponse, true);
		
		return $aMetadata;
	}
	
	/**
     * Find the value of the given metadata field for the given item id.
     * This can be used to find, for instance, the accessrights.
     * The metadata field is identified by its metadata_field_id, which 
     * can be found in the metadata registry. 
	 * Use this when you're only interested in a single field. 
     * 
     * @param int $nItemId
     * @param int $nMetadataFieldId 
     * @return array 
     */
    public function getMetadataValue($nItemId, $nMetadataFieldId)
    {
        $aDebug = array();
    
        if (!isset($nItemId) || $nItemId == '') {
            $aDebug['error'] = 'No itemid in getMetadataValue, looking for ' . $nMetadataFieldId;
            return $aDebug;
        }
        
        $sql = "select * from metadatavalue where resource_id=$nItemId and metadata_field_id=$nMetadataFieldId and resource_type_id=2";
        
        try {
            $result = pg_query($this->dbconn, $sql);
    
            while ($row = pg_fetch_assoc($result)) {
                $aDebug['values'][] = $row['text_value'];
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not get metadata value: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
	
	/**
	 * This will only work if you know the language value for the field
	 * 
	 * @param type $sMetadataFieldName
	 * @param type $sValue
	 * @return type
	 */
	public function getItemsByMetadataRest($sMetadataFieldName, $sValue, $sLanguage)
	{
		$aItemIds = array();
		$aSearchData = array('key' => $sMetadataFieldName, 'value' => $sValue, 'language' => $sLanguage);
		$sMetadataEntry = json_encode($aSearchData);
		
		$sService = 'items/find-by-metadata-field';
		
		$aFoundItems = $this->oRest->doRequest($sService, 'POST', $sMetadataEntry);
		$aItems = json_decode($aFoundItems['response'], true);
		
		foreach ($aItems as $aOneItem) {
			$nItemId = $aOneItem['id'];
			$aItemIds[] = $nItemId;
		}
		
		return $aItemIds;
	}
	
    /**
     * Find all items where the given metadata field has the given value.
     * This function only checks for exact matches for the value, but doesn't care about the language.
 	 * 
     * @param int $nMetadataFieldId
     * @param string $sValue
     * @return array
     */
    public function findItemByMetadata($nMetadataFieldId, $sValue)
    {
        $aDebug = array();
        
        $sql = "select * from metadatavalue where metadata_field_id=$nMetadataFieldId and
                text_value='$sValue' and resource_type_id=2";
        
        try {
            $result = pg_query($this->dbconn, $sql);
    
            while ($row = pg_fetch_assoc($result)) {
                $aDebug['itemids'][] = $row['resource_id'];
            }
            //pg_free_result($result);
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not get metadata value: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
	
	
     /**
     * Find all items where the given metadata field contains the given value.
     * This function searches by "like %value%" and is slower than findItemByMetadata.
     * So only use it if searching for an exact value is impossible
     * 
     * @param type $nMetadataFieldId
     * @param type $sValue
     */
    public function findItemByContainsMetadata($nMetadataFieldId, $sValue)
    {
        $aDebug = array();
        
        $sql = "select * from metadatavalue where metadata_field_id=$nMetadataFieldId and 
                text_value like '%" . $sValue . "%' and resource_type_id=2";
        
         
        try {
            $result = pg_query($this->dbconn, $sql);
    
            while ($row = pg_fetch_assoc($result)) {
                $aDebug['itemids'][] = $row['resource_id'];
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not get metadata value: ' . $e->getMessage();
        }
        
        return $aDebug;

    }

	/**
     * Find author strings that have a | in them.
     *  
     * @return array
     */
    public function findUncorrectedAuthors($nFieldId)
    {
        $aDebug = array();
        
        $sql = "select * from metadatavalue where metadata_field_id=" . $nFieldId . " and 
                text_value like '%|%' and resource_type_id=2";
        
        try {
            $result = pg_query($this->dbconn, $sql);
    
            while ($row = pg_fetch_assoc($result)) {
                $aDebug[] = $row;
            }
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not get metadata value: ' . $e->getMessage();
        }
        
        return $aDebug;
    }


	 /**
     * Update an author record, including values for authority and confidence
	 * Since the REST API doesn't support authority and confidence,
	  * we do this directly in the database 
     * @param array $aNewValues
     * @return array
     */
    public function updateAuthorRecord($aNewValues)
    {
        $aDebug = array();
        
        if (!isset($aNewValues['metadata_value_id'])) {
            $aDebug['error'] = 'no metadata value id set';
            return $aDebug;
        }
        elseif (!isset($aNewValues['author_name'])) {
            $aDebug['error'] = 'now author name set';
            return $aDebug;
        }
        else {
            $nMetadataValueId = $aNewValues['metadata_value_id'];
            $sAuthorName = pg_escape_string($aNewValues['author_name']);
            $sAuthority = '';
            if (isset($aNewValues['authority'])) {
                $sAuthority = $aNewValues['authority'];
            }
            $nConfidence = -1;
            if (isset($aNewValues['confidence'])) {
                $nConfidence = $aNewValues['confidence'];
            }
        
            $sql = "update metadatavalue set text_value='" . $sAuthorName . "', 
                authority='" . $sAuthority . "', confidence=" . $nConfidence . "
                    where metadata_value_id=" . $nMetadataValueId;
            
            $aDebug['sql'] = $sql;
            
            try {
                $result = pg_query($this->dbconn, $sql);
                $aDebug['success'] = $result;
            }
            catch (Exception $e) {
                $aDebug['error'] = 'update author record failed: ' . $e->getMessage();
            }


       
            return $aDebug;
        }
        
        return $aDebug;
    }   
	

	 /**
     * Update an existing metadatafield directly in the database
 	 * 
     * @param int $nItemId
     * @param int $nMetadataFieldId
     * @param string $sNewValue
     * @param int $nPlace
     * @return array
     */
    public function updateMetadataValue($nItemId, $nMetadataFieldId, $sNewValue)
    {
        $aDebug = array();
        
        $sql = "update metadatavalue set text_value='" . pg_escape_string($sNewValue) . "' where 
            resource_id=" . $nItemId . " and metadata_field_id=" . $nMetadataFieldId . " and resource_type_id=2";
        
         try {
            $result = pg_query($this->dbconn, $sql);
            $aDebug['success'] = $result;
        }
        catch (Exception $e) {
            $aDebug['error'] = 'update metadata value failed: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
	
	
	/**
	 * Update an existing field 
	 * 
	 * @param int $nItemId
	 * @param string $sMetadataField
	 * @param string $sNewValue
	 * @param string $sLanguage
	 * @return array
	 */
	public function updateMetadataValueRest($nItemId, $sMetadataField, $sNewValue, $sLanguage)
	{
		//the documentation doesn't say so, but for update the API expects an array of 
		//metadata objects
		$aMetadata = array( 
				array('key' => $sMetadataField, 'value' => $sNewValue, 'language' => $sLanguage),
			);

		$sMetadataEntry = json_encode($aMetadata);
		//return $sMetadataEntry;
		
		$sService = 'items/' . $nItemId . '/metadata';
		$aResult = $this->oRest->doRequest($sService, 'PUT', $sMetadataEntry);
		
		return $aResult;
	}
	
	
	/**
     * Add a metadata field to an item directly into the database
	  * 
     * @param int $nItemId
     * @param int $nMetadataFieldId
     * @param string $sNewValue
     * @param int $nPlace
     * @return array
     */
    public function addMetadataValue($nItemId, $nMetadataFieldId, $sNewValue)
    {
        $aDebug = array();
        
        $sql = "insert into metadatavalue (resource_id, metadata_field_id, text_value, resource_type_id) 
            values (" . $nItemId . ", " . $nMetadataFieldId . ", '" . pg_escape_string($sNewValue) . "', 2)";
        
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'add metadata value failed: ' . $e->getMessage();
        }
        
        return $aDebug;
    }

	
	
	/**
	 *  Add a metadata value to an item
	 * 
	 * 
	 * @param int $nItemId
	 * @param string $sMetadataField
	 * @param string $sValue
	 * @param string $sLanguage
	 * @return array
	 */
	public function addMetadataValueRest($nItemId, $sMetadataField, $sValue, $sLanguage)
	{
		//you must send an array of metadata objects, so even if we send just
		//one object, we must make an array for it.
		$aMetadata = array( 
				array('key' => $sMetadataField, 'value' => $sValue, 'language' => $sLanguage),
			);
		$sMetadataEntry = json_encode($aMetadata);
		
		$sService = 'items/' . $nItemId . '/metadata';
		$aResult = $this->oRest->doRequest($sService, 'POST', $sMetadataEntry);
		
		return $aResult;
	
	}
	
	
	public function getMetadataFieldId($sMetadataName) {
		$sShortName = preg_replace('/^dc\./', '', $sMetadataName);
		$aNameParts = explode('.', $sShortName);
		$sElement = $aNameParts[0];
		$sQualifier = '';
		if (isset($aNameParts[1])) {
			$sQualifier = $aNameParts[1];
		}
		
		$sql = "select * from metadatafieldregistry where metadata_schema_id=1 "
				. "and element='" . $sElement . "'"; 
		//	and qualifier='" . $sQualifier . "' and metadata_schema_id=1";
		if ($sQualifier == '') {
			$sql .= ' and qualifier is null';
		}
		else {
			$sql .= " and qualifier='" . $sQualifier . "'";
		}
		
		$nMetadataFieldId = 0;
		try {
            $result = pg_query($this->dbconn, $sql);
    
            while ($row = pg_fetch_assoc($result)) {
                $nMetadataFieldId = $row['metadata_field_id'];
            }
        }
        catch (Exception $e) {
            $nMetadataFieldId = 0;
        }
		
		return $nMetadataFieldId;
	}
	
	public function getMetadataFieldName($nMetadataField) {
		$sql = "select * from metadatafieldregistry where metadata_field_id=" . $nMetadataField;
		
		$sFieldName = '';
		try {
            $result = pg_query($this->dbconn, $sql);
    
            while ($row = pg_fetch_assoc($result)) {
                $sFieldName = $row['element'];
				if ($row['qualifier'] != '' && $row['qualifier'] != null) {
					$sFieldName .= '.' . $row['qualifier'];
				}
            }
        }
        catch (Exception $e) {
            $sFieldName = '';
        }
		
		
		
		return $sFieldName;
	}
}

