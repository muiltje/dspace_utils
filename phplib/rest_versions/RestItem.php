<?php
require_once 'DatabaseConnector.php';
require_once 'RestConnector.php';

/**
 * Utility functions about items
 *
 * @author muilw101
 */
class RestItem {
    private $dbconn;
    private $oRest;
    
    public function __construct() {
        $oDC = new DatabaseConnector();
        $this->dbconn = $oDC->getConnection();
        $this->oRest = new RestConnector();
    }
  
	public function getItemData($nItemId)
    {
        $aItemData = array();
        
        $query = 'select * from item where item_id=' . $nItemId;
        
        try {
            $result = pg_query($this->dbconn, $query);
            while ($row = pg_fetch_assoc($result)) {
                $aItemData = $row;
            }
        }
        catch (Exception $e) {
            $aItemData['error'] = 'could not get item data for ' . $nItemId;
        }
        
        return $aItemData;
    }
	
	public function getItemDataRest($nItemId)
	{
		$sService = 'items/' . $nItemId;
		$aFoundData = $this->oRest->doRequest($sService, 'GET', '');
		$sResponse = $aFoundData['response'];
		$aItemdata = json_decode($sResponse, true);
		
		return $aItemdata;
	}
	
	
	public function getItemCollection($nItemId)
	{
		$sService = 'items/' . $nItemId . '?expand=parentCollectionList,parentCommunityList';
		$aFoundData = $this->oRest->doRequest($sService, 'GET', '');
		$sResponse = $aFoundData['response'];
		$aCollectionData = json_decode($sResponse, true);
		
		return $aCollectionData;
	}

	/**
     * Find all items that belong to the given collection
     * REST: GET /collections/{collectionId}/items
     * @param int $nCollectionId 
     * @return array $aItems
     */
    public function getCollectionItems($nCollectionId)
    {
        $aItems = array();
    
        $query = 'select * from collection2item where collection_id=' . $nCollectionId;
        try {
            $result = pg_query($this->dbconn, $query);
    
            while ($row = pg_fetch_assoc($result)) {
                $aItems[] = $row;
            }
    
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aItems['error'] = 'could not find items for collection ' . $nCollectionId . ': ' . $e->getMessage();
        }
    
        return $aItems;
        
    }
	 /**
     * Select items to be exported, based on params
     * 
     * @param int $nExportDefId
     * @param int $nFullDump
     * @param int $nWithdrawn
     * @return array with item_id, handle and the export_filename
      */
    public function getExportItems($nExportDefId, $nFullDump, $nWithdrawn)
    {
        $aExportItems = array();
        
        $bDrawn = 'f';
        if ($nWithdrawn == 1) {
            $bDrawn = 't';
        }
        
        $query = "SELECT DISTINCT i.item_id, h.handle
                 FROM 
                    item i,
                    dspace2omega d2o,
                    ubuaux_exportdef e,
                    ubuaux_exportdef2coll e2coll,
                    collection col,
                    collection2item col2i,
                    handle h
                 WHERE
                    i.withdrawn='$bDrawn'";
        if ($nWithdrawn != 1) {
            $query .= " AND i.in_archive='t'";
        }
        if ($nFullDump != 1) {
            $query .= " AND i.last_modified >= d2o.last_run ";
        }
        $query .= " AND i.item_id=h.resource_id 
                    AND h.resource_type_id=2
                    AND i.item_id=col2i.item_id
                    AND col2i.collection_id=col.collection_id 
                    AND col2i.collection_id=e2coll.collection_id 
                    AND e.exportdef_id=e2coll.exportdef_id ";
        if ($nExportDefId != 0) {
            $query .= " AND e.exportdef_id=$nExportDefId ";
        }
        $query .= " ORDER BY i.item_id";
        
                      
        $result = pg_query($this->dbconn, $query);
    
        while ($row = pg_fetch_assoc($result)) {
            $aExportItems[] = $row;
        }
        
        
        pg_free_result($result);
        
            
        return $aExportItems;
    }
	
	    /**
     * Find all items that were last modified after the given date
     * If the param $sShortPeriod is set, we want to look at changes in the last
     * hours (or seconds).
     * Otherwise we look for changes in the last days.
     *      
     * @param string $sLastModifiedDate 
     * @param string $sShortPeriod
     * @return array 
     */
    public function getModifiedItems($sLastModifiedDate, $sShortPeriod = NULL)
    {
        $aItems = array();
        
        $query = "select * from item ";
        if ($sShortPeriod != NULL) {
            $query .= "where last_modified > to_timestamp('$sLastModifiedDate', 'YYYY-MM-DD HH24:MI:SS')";
        }
        else {
            $query .= "where last_modified > to_timestamp('$sLastModifiedDate', 'YYYY-MM-DD')";
        }
        $result = pg_query($this->dbconn, $query);        
        while ($row = pg_fetch_assoc($result)) {
            $aItems[] = $row;
        }
     
        return $aItems;
    }
    
	    /**
     * Find all items that were last modified between two given dates
     *
     * @param string $sStartDate
     * @param string $sEndDate
     * @return array with all fields from item table
     */
    public function getModifiedBetweenItems($sStartDate, $sEndDate)
    {
        $aItems = array();
        
        $query = "select * from item where last_modified > to_timestamp('$sStartDate', 'YYYY-MM-DD')
            and last_modified < to_timestamp('$sEndDate', 'YYYY-MM-DD')";
        $result = pg_query($this->dbconn, $query);
    
        while ($row = pg_fetch_assoc($result)) {
            $aItems[] = $row;
        }
        pg_free_result($result);
    
        return $aItems;
    }
	
	/**
	 * Find all items that belong to the given export definition are were last modified after the given date
	 * 
	 * @param string $sLastModifiedDate
	 * @param int $nExportDefId
	 */
    public function getModifiedExportItems($nExportDefId, $sStartDate, $sEndDate = NULL)
	{
	    $aExportItems = array();
        
        $query = "SELECT DISTINCT i.item_id
                 FROM 
                    item i,
                    ubuaux_exportdef e,
                    ubuaux_exportdef2coll e2coll,
                    collection col,
                    collection2item col2i
                 WHERE
                    i.withdrawn='f'
	                AND i.item_id=col2i.item_id
                    AND col2i.collection_id=col.collection_id 
                    AND col2i.collection_id=e2coll.collection_id 
                    AND e.exportdef_id=e2coll.exportdef_id 
					AND e.exportdef_id=$nExportDefId 
					AND i.last_modified  > to_timestamp('$sStartDate', 'YYYY-MM-DD')";
				if ($sEndDate != NULL) {
					$query .= " AND i.last_modified < to_timestamp('$sEndDate', 'YYYY-MM-DD')";
				}
        
        //echo $query;              
        $result = pg_query($this->dbconn, $query);
    
        while ($row = pg_fetch_assoc($result)) {
            $aExportItems[] = $row;
        }
         return $aExportItems;	
	}
	
	    /**
     * Find digitized items that have recently been added or modified
     * 
     * @return array 
     */
    public function getManifestationItems()
    {
        $aDebug = array();
        $aManifestationItems = array();
        
        $query = "SELECT DISTINCT i.item_id, h.handle, m.text_value
                    FROM item i
                    INNER JOIN handle h ON i.item_id=h.resource_id AND h.resource_type_id=2
                    INNER JOIN dspace2omega_collection d2oc ON i.owning_collection=d2oc.collection_id 
                        AND d2oc.derivatives='t'
                    LEFT JOIN metadatavalue m ON i.item_id=m.resource_id AND m.resource_type_id=2 AND m.metadata_field_id=158
                    WHERE i.in_archive='t' AND i.withdrawn='f' AND i.item_id IN 
                        (SELECT i.item_id FROM item i,dspace2omega d2o WHERE i.last_modified >= d2o.last_run)
                    ORDER BY i.item_id";
        
        try {
            $result = pg_query($this->dbconn, $query);
            while ($row = pg_fetch_assoc($result)) {
                $aManifestationItems[] = $row;
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not get manifestation items: ' . $e->getMessage();
            return $aDebug;
        }
                
        return $aManifestationItems;
    }
	
	
	    /**
     * Get all items that should be send to the reader
     * 
     * @param $nSelectionLimit limits number of items we get
     * @return array
     */
    public function getObjectReaderExportItems($nSelectionLimit)
    {
        $aDebug = array();
        $aExportItems = array();
        
        $aFieldIds = array(
            'source_alephid' => 127,
            'id_digitization' => 271,
            'partofalephid' => 265,
            'date_issued' => 27,
            'title' => 143,
            'partofvolume' => 162,
            'startpage' => 164,
            'accessrights' => 161,
        );
        
         $query = 'SELECT DISTINCT 
            i.item_id, 
            h.handle, 
            m1.text_value as aleph, 
            r.element, r.qualifier, 
            m2.text_value as dateissued, 
            m3.text_value as title, 
            m4.text_value as volume, 
            m5.text_value as startpage,
            m6.text_value as accessrights,
            m7.text_value as digi_id,
            i.withdrawn
            FROM item i
            INNER JOIN dspace2omega_collection d2oc ON i.owning_collection=d2oc.collection_id 
                AND d2oc.derivatives=\'t\'
            INNER JOIN handle h ON i.item_id=h.resource_id AND h.resource_type_id=2
            INNER JOIN (metadatavalue m1
                 INNER JOIN metadatafieldregistry r ON m1.metadata_field_id=r.metadata_field_id)
            ON i.item_id=m1.resource_id AND m1.resource_type_id=2 AND m1.metadata_field_id IN (' .
                 $aFieldIds['source_alephid'] . ', ' . $aFieldIds['partofalephid'] . ')';
        $query .= ' LEFT JOIN metadatavalue m2 ON i.item_id=m2.resource_id AND m2.resource_type_id=2 AND m2.metadata_field_id=' . $aFieldIds['date_issued']; 
        $query .= ' LEFT JOIN metadatavalue m3 ON i.item_id=m3.resource_id AND m3.resource_type_id=2 AND m3.metadata_field_id=' . $aFieldIds['title'];
        $query .= ' LEFT JOIN metadatavalue m4 ON i.item_id=m4.resource_id AND m4.resource_type_id=2 AND m4.metadata_field_id=' . $aFieldIds['partofvolume'];
        $query .= ' LEFT JOIN metadatavalue m5 ON i.item_id=m5.resource_id AND m5.resource_type_id=2 AND m5.metadata_field_id=' . $aFieldIds['startpage'];
        $query .= ' LEFT JOIN metadatavalue m6 ON i.item_id=m6.resource_id AND m6.resource_type_id=2 AND m6.metadata_field_id=' . $aFieldIds['accessrights'];
        $query .= ' LEFT JOIN metadatavalue m7 ON i.item_id=m7.resource_id AND m7.resource_type_id=2 AND m7.metadata_field_id=' . $aFieldIds['id_digitization'];
 
        $query .= ' WHERE i.in_archive=\'t\'';
        
        //if we don't want a result with 20.000 items
        if ($nSelectionLimit != 0) {
            $query .= ' LIMIT ' . $nSelectionLimit;
        }
        
        try {
            $result = pg_query($this->dbconn, $query);
            while ($row = pg_fetch_assoc($result)) {
                $aExportItems[] = $row;
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not get items for the object reader: ' . $e->getMessage();
            return $aDebug;
        }
        
        return $aExportItems;
    }

	public function getWithdrawnItems()
	{
		$aResult = array();
		
		$query = "select * from item where withdrawn='t'";
	    try {
            $result = pg_query($this->dbconn, $query);
            while ($row = pg_fetch_assoc($result)) {
                $aResult[] = $row;
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aResult['error'] = 'could not get manifestation items: ' . $e->getMessage();
         }
		 
		 return $aResult;
	}	

	 /**
     * Find all items that lack the given metadata field
     * Items for community 36 (Igitur Journals) do not get field 158 (urlfulltext)
     * Items with closed access do not get field 158
     * 
     * @param int $nMetadataFieldId
     * @param int $nFullDump
     * @return array 
     */
    public function findMissingMetadata($nMetadataFieldId, $nFullDump)
    {
       $aItems = array();
       
       $query = "SELECT i.item_id, m.text_value";
       
       if ($nMetadataFieldId == 158) {
           $query .= ", m2.text_value ";
           $query .= " FROM item i ";
           $query .= "INNER JOIN (collection co
            INNER JOIN (community2collection cu2co
                INNER JOIN community cu ON cu2co.community_id = cu.community_id)
                    ON co.collection_id = cu2co.collection_id)
                ON i.owning_collection = co.collection_id";
           if ($nFullDump != 1) {
                $query .= " INNER JOIN dspace2omega d2o ON i.last_modified >= d2o.last_run ";
           }
           $query .= " LEFT JOIN metadatavalue m ON i.item_id=m.resource_id AND m.resource_type_id=2 AND m.metadata_field_id=158
               LEFT JOIN metadatavalue m2 ON i.item_id=m2.resource_id AND m2.resource_type_id=2 AND m2.metadata_field_id=161 
            WHERE i.in_archive='t' AND i.withdrawn='f' AND m.text_value IS NULL AND cu.community_id!=36
            AND m2.text_value != 'Closed Access'";
       }
       else {
           $query .= " FROM item i ";
           if ($nFullDump != 1) {
                $query .= " INNER JOIN dspace2omega d2o ON i.last_modified >= d2o.last_run ";
           }
           $query .= " LEFT JOIN metadatavalue m ON i.item_id=m.resource_id AND m.resource_type_id=2 AND m.metadata_field_id=$nMetadataFieldId
                    WHERE i.in_archive='t' AND i.withdrawn='f' AND m.text_value IS NULL";
       }
       
       
       try {
           $result = pg_query($this->dbconn, $query);
           while ($row = pg_fetch_assoc($result)) {
               $aItems[] = $row;
            }
          
            pg_free_result($result);
       }
       catch (Exception $e) {
           $aItems['error'] = 'search for missing metadata failed: ' . $e->getMessage();
       }
 
       return $aItems;
    }
	
	
	/**
     * Update the last_modified field for the given item
     * 
     * @param int $nItemId
     * @param string $sNewDate
     * @return array
     */
    public function updateLastModified($nItemId, $sNewDate)
    {
        $aDebug = array();
        
        $query = "update item 
            set last_modified = to_timestamp('$sNewDate', 'YYYY-MM-DD HH24:MI:SS') 
                where item_id=" . $nItemId;
        
        try {
            $aDebug['result'] = pg_query($this->dbconn, $query);
        }
        catch (Exception $e) {
            $aDebug['error'] = $e->getMessage();
        }
        
        return $aDebug;
    }
}

