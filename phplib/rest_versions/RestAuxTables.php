<?php
require_once 'DatabaseConnector.php';
/**
 * Various functions for ubuaux tables
 *
 * @author muilw101
 */

class RestAuxTables {
    private $dbconn;
    
    
    public function __construct() {

        
        $oDC = new DatabaseConnector();
        $this->dbconn = $oDC->getConnection();
        
    }

    
    /**
     * Find all entries in the Printing on Demand table
     * 
     * @return array 
     */
    public function findPodData() 
    {
        $aPodData = array();
        
        $query = "SELECT * FROM ubuaux_pod";
        
        try {
            $result = pg_query($this->dbconn, $query);
    
            while ($row = pg_fetch_assoc($result)) {
                $aPodData[] = $row;
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aPodData['error'] = 'could not count pod data: ' . $e->getMessage();
            
        }
        
        return $aPodData;
    }


    /**
     * See if one particular item should have printing on demand
     * @param type $sHandle
     */
    public function findItemPod($sHandle) 
    {
        $aItemPodData = array('handle' => $sHandle);
        $aAuxPodData = $this->checkItemPod($aItemPodData);
        
        return $aAuxPodData;
    }    
    
    
    
    /**
     * Update de timestamp of the last time the export ran
     * 
     * @param string $sTimeStamp
     * @return array 
     */
    public function updateLastRunTimeStamp($sTimeStamp)
    {
        $aDebug = array();
        
        $query = "UPDATE dspace2omega SET last_run='$sTimeStamp'";
        
        try {
            pg_query($this->dbconn, $query);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not update last run timestamp' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
    /**
     * Find the repositories for a given item
     * 
     * NB: do not use owning_collection, use collection2item, 
     * because an item can be mapped to several collections and it should get the
     * value of all of them
     * 
     * @param int $nItemId
     * @return array 
     */
    public function findRepository($nItemId)
    {
        $aDebug = array();
        
        $query = "SELECT r.repository_name
            FROM item i, collection2item c2i, ubuaux_repository r,ubuaux_collection2repository c2r
            WHERE i.item_id=" . $nItemId . " AND i.item_id=c2i.item_id AND 
                c2i.collection_id=c2r.collection_id AND c2r.repository_id=r.repository_id";
        
        try {
            $result = pg_query($this->dbconn, $query);
            while ($row = pg_fetch_assoc($result)) {
                $aDebug['repositories'][] = $row['repository_name'];
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not find repository data: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
    /**
     * Find all export definitions
     * @return array
     */
    public function findExportDefinitions()
    {
        $aDebug = array();
        
        $query = "select * from ubuaux_exportdef";
        
        try {
            $result = pg_query($this->dbconn, $query);
            while ($row = pg_fetch_assoc($result)) {
                $aDebug['expdefs'][] = $row;
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not find export definition data: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
    /**
     * Check if the item is present in the given vendor table
     * 
     * @param string $sVendor
     * @param string $sIdentifier
     * @param string $sMdStatus
     * @return array 
     */
    public function checkVendorItem($sTableName, $sVendor, $sIdentifier, $sMdStatus='')
    {
        $aVendorItemData = array();
        
        $query = '';
        
        if (isset($sMdStatus) && $sMdStatus != '') {
            $query = "SELECT DISTINCT v.item_id, v.in_archive, v.withdrawn,
                v.mdstatus, v.last_modified
                FROM " . $sTableName . " as v
                WHERE v.vendor='" . $sVendor . "' 
                    AND v.identifier_other='" . $sIdentifier . "' 
                    AND v.mdstatus='" . $sMdStatus . "'";
        }
        else {
            $query = "SELECT DISTINCT v.item_id, v.in_archive, v.withdrawn,
                v.mdstatus, v.last_modified
                FROM item i, " . $sTableName . " as v
                WHERE v.vendor='" . $sVendor . "' 
                    AND v.identifier_other='" . $sIdentifier . "'
                    AND v.withdrawn='f'";
        }
        
        $aVendorItemData['query'] = $query;
        
        try {
            $result = pg_query($this->dbconn, $query);
            while ($row=pg_fetch_assoc($result)) {
                $aVendorItemData['data'] = $row;
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aVendorItemData['error'] = 'could not fetch vendor data: ' . $e->getMessage();
        }
        
        return $aVendorItemData;
     }
    
    /**
     * Get the collection within the Scrol community, based on the faculty name
	 * 
     * @param string $sFaculty
     * @return array
     */
    public function getScrolCollection($sFaculty)
    {
        $aCollectionData = array();
        
        $sql = "SELECT m.resource_id as collection_id, s.subj_discipline as subj_discipline
			FROM metadatavalue m, ubuaux_scrol s
			WHERE s.scrol_faculty='" . $sFaculty . "' AND s.coll_name=m.text_value "
				. "AND m.resource_type_id=3 AND m.metadata_field_id=143";
        
        try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
                //$aCollectionData = $row;
				$aCollectionData['collection_id'] = $row['collection_id'];
				$aCollectionData['subj_discipline'] = $row['subj_discipline'];
            }
            pg_free_result($result);
        }
        catch (Exception $e)
        {
            $aCollectionData['error'] = ' could not find collection id for ' . $sFaculty . ': ' . $e->getMessage();
        }
        
        return $aCollectionData;
    }
    
    /**
     * Recreate the whole vendors table.
     * 
     * @return array 
     */
    public function makeVendorsTable()
    {
        $sVendorTable = 'ubuaux_vendor_recs';
        $aResults = array();
        
        $aDropTemp = $this->dropTempTable($sVendorTable);
        
        if (isset($aDropTemp['error'])) {
            $aResults['error'] = $aDropTemp['error'];
            return $aResults;
        }
        else {
            $aDropTmpIdx = $this->dropTempIndex($sVendorTable);
            if (isset($aDropTmpIdx['error'])) {
                $aResults['error'] = $aDropTmpIdx['error'];
                return $aResults;
            }
            else {
                //$aTempCreate = $this->createTemp($sVendorTable);
                $aTempSelect = $this->getItemsToTemp($sVendorTable);
                if (isset($aTempSelect['error'])) {
                    $aResults['error'] = $aTempSelect['error'];
                    return $aResults;
                }
                else {
                    $aMetadata = $this->getTempMetadata();
                    if (isset($aMetadata['error'])) {
                        $aResults['error'] = $aMetadata['error'];
                        return $aResults;
                    }
                    else {
                        $aTempData = $aMetadata['data'];
                        $aAddResults = $this->addTempMetadata($sVendorTable, $aTempData);
                        if (isset($aAddResults['error'])) {
                            $aResults['error'] = $aAddResults['error'];
                            return $aAddResults;
                        }
                        else {
                            $aIdxResults = $this->makeTempIndex($sVendorTable);
                            if (isset($aIdxResults['error'])) {
                                $aResults['error'] = $aIdxResults['error'];
                                return $aResults;
                            }
                            else {
                                $aDropTable = $this->dropVendorTable($sVendorTable);
                                $aDropIndex = $this->dropVendorIndex($sVendorTable);
                                if (isset($aDropTable['error']) || isset($aDropIndex['error'])) {
                                    $aResults['error'] = 'could not drop existing table or index';
                                    return $aResults;
                                }
                                else {
                                    $aAlterTable = $this->renameTemp($sVendorTable);
                                    $aAlterIndex = $this->renameIndex($sVendorTable);
                                    if (isset($aAlterTable['error']) || isset($aAlterIndex['error'])) {
                                        $aResults['error'] = 'could not rename table or index';
                                        return $aResults;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        $aResults['result'] = 'success';
        return $aResults;
    }
    
    /**
     * Update the existing vendors table with recent items from the given collection
     * 
     * @param int $nCollectionId
     * @return array 
     */
    public function updateVendorsTable($nCollectionId)
    {
        $sVendorTable = 'ubuaux_vendor_recs';
        $aResult = $this->addRecentToVendorTable($sVendorTable, $nCollectionId);
        
        return $aResult;
    }
     
    /**
     * Make a tempory table with items from the given vendor.
     * 
     * @param string $sVendor
     * @return array
     */
    public function makeOneVendorTable($sVendor)
    {
        $aResult = $this->createVendorTemp($sVendor);
        
        return $aResult;
    }
    
    
    /**
     * Check the embargos that are stored in the embargo aux table.
     * NB: we use this aux table and not the regular tables, because we
     * only want to know about a particular set of embargos. 
     * The aux table is maintained by the syncIgdiss function
     * 
     * @return array
     */    
    public function checkEmbargoAlert($sPubType)
    {
        $aData = array();
        
        $aFindData = $this->findEmbargoAlert($sPubType);
        if (isset($aFindData['error'])) {
            $aData['error'] = $aFindData['error'];
        }
        else {
            $aData = $aFindData['data'];
        }
        
        return $aData;
    }
    
    /**
     * Delete all records from the given table
     * Wrapper around resetAuxTable
     * 
     * @param string $sTableName
     * @return array
     */
    public function resetTable($sTableName)
    {
        $aDebug = $this->resetAuxTable($sTableName);
        
        return $aDebug;
    }
    
    /**
     * Find embargos that end two months from now
     * Wrapper for findFutureEmbargoData
     * 
     * @return array
     */
    public function findFutureEmbargoAlert()
    {
        $aData = array();
        
        $aFindData = $this->findFutureEmbargoData();
        if (isset($aFindData['error'])) {
            $aData['error'] = $aFindData['error'];
        }
        else {
            $aData = $aFindData['data'];
        }
        
        return $aData;
    }
    
    
    /**
     * Make sure email addresses in embargo table are up-to-date
     * 
     * @param array $aSyncData
     * @return array
     */
    public function syncEmbargoTable($aSyncData, $sPubType)
    {
        $aDebug = array();
        
        foreach ($aSyncData as $sHandle => $aEmailData) {
            $sExistingEmail = $aEmailData['existingemail'];
            $sNewEmail = $aEmailData['newemail'];
            
            if ($sExistingEmail == '' && $sNewEmail != '') {
                //$aDebug['inserts'][] = $sHandle;
                $aInsert = $this->insertEmbargoEmail($sHandle, $sNewEmail, $sPubType);
                if (isset($aInsert['error'])) {
                    $aDebug['errors'][] = $aInsert['error'];
                }
            }
            elseif ($sNewEmail != '') {
                //$aDebug['updates'][] = $sHandle;
                $aUpdate = $this->updateEmbargoEmail($sHandle, $sNewEmail, $sPubType);
                if (isset($aUpdate['error'])) {
                    $aDebug['errors'][] = $aUpdate['error'];
                }
            }
        }
        
        return $aDebug;
    }
 
    
    public function syncPodItem($aItemPodData) 
    {
        $aDebug = array();
        
        $aPodPresence = $this->checkItemPod($aItemPodData);
        
        if (empty($aPodPresence)) {
             $aDebug['add'][] = $this->addPodItem($aItemPodData);
        }
        else {
            //$aDebug['found'][] = $aItemPodData['handle'];
            //we could update here, but we don't really use the price,
            //which is the only thing we can update
            //$aDebug['update'] = $this->updatePodItem($aItemPodData);
        }
        
        //return $aPodPresence;
        return $aDebug;
    }    
    
    public function checkAuthorNotification($sHandle)
    {
        $aCheckResult = $this->getAuthorNotification($sHandle);
        
        return $aCheckResult;
    }
    
    public function saveAuthorNotification($sHandle)
    {
        $aSaveResult = $this->addAuthorNotification($sHandle);
        
        return $aSaveResult;
    }
    
    /**
     * Drop the temp version of a table if it exists
     * 
     * @param string $sVendorTable
     * @return array
     */
    private function dropTempTable($sVendorTable)
    {
        $aDebug = array();
        
        $sql = "DROP TABLE IF EXISTS " . $sVendorTable . "_tmp";
        
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not drop temp table: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
    
    /**
     * Drop the index to the temp version of the table
     * 
     * @param string $sVendorTable
     * @return array
     */
    private function dropTempIndex($sVendorTable)
    {
        $aDebug = array();
        
        $sql = "DROP INDEX IF EXISTS " . $sVendorTable . "_tmp_idx";
        
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not drop temp index: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
    
    /**
     * Select items into a temporaty table
     * 
     * @param string $sVendorTable
     * @return array
     */
    private function getItemsToTemp($sVendorTable)
    {
        $aDebug = array();
        $aData = array();
        
         $sql = "SELECT DISTINCT 
                    m1.text_value as vendor,
                    m2.text_value as identifier_other,
                    i.item_id,
                    i.owning_collection,
                    h.handle,
                    'a' as mdstatus,
                    i.in_archive,
                    i.withdrawn,
                    i.last_modified
                INTO TABLE " . $sVendorTable . "_tmp
                FROM
                    metadatavalue m1,
                    metadatavalue m2,
                    item i,
                    handle h,
                    collection c
                WHERE
                    i.owning_collection = c.collection_id AND 
                    i.item_id=m1.resource_id AND m1.metadata_field_id=151 AND m1.resource_type_id=2 AND 
                    i.item_id=m2.resource_id AND m2.metadata_field_id=71 AND m2.resource_type_id=2 AND
                    i.in_archive='t' AND
                    i.withdrawn='f' AND
                    i.item_id=h.resource_id AND
                    h.resource_type_id=2
                ";
        
        try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
                $aData = $row;
            }
            pg_free_result($result);
            $aDebug['data'] = $aData;
            
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not retrieve items for temp table: ' . $e->getMessage();
        }
        
        return $aDebug;
     
    }
        
    /**
     * Get metadata to use for the temporary table
     * 
     * @return array
     */
    private function getTempMetadata()
    {
        $aDebug = array();
        $aData = array();
        
        $sql = "SELECT DISTINCT 
                    m1.text_value as vendor,
                    m2.text_value as identifier_other,
                    i.item_id,
                    'workflow' as owning_collection
                FROM
                    metadatavalue m1,
                    metadatavalue m2,
                    workflowitem i
                WHERE
                    i.item_id=m1.resource_id AND m1.metadata_field_id=151 AND m1.resource_type_id=2 AND
                    i.item_id=m2.resource_id AND m2.metadata_field_id=71 AND m2.resource_type_id=2
                ";
        
        try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
                $aData[] = $row;
            }
            pg_free_result($result);
            $aDebug['data'] = $aData;
            
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not retrieve additional metadata for temp table: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
        
    
    /**
     * Add metadata to the temporary table
     * 
     * @param string $sVendorTable
     * @param array $aMetadata
     * @return array
     */
    private function addTempMetadata($sVendorTable, $aMetadata)
    {
        $aDebug = array();
        
        foreach ($aMetadata as $aOneItem) {
            $sql = "INSERT INTO " . $sVendorTable . "_tmp VALUES
                    ('" . $aOneItem['vendor'] . "',
                     '" . $aOneItem['identifier_other'] . "', 
                     '" . $aOneItem['item_id'] . "',
                     0,
                     'workflow',
                     'm')";
            $aDebug['sql'][] = $sql;
            
            try {
                pg_query($this->dbconn, $sql);
                $aDebug['success'][] = 'success';
            }
            catch (Exception $e) {
                $aDebug['error'][] = 'could not insert data for ' . $aOneItem['item_id'] . ': ' . $e->getMessage();
            }
        }
        
        return $aDebug;
    }
        
    /**
     * Create an inex on the temporary table
     * 
     * @param string $sVendorTable
     * @return array
     */
    private function makeTempIndex($sVendorTable)
    {
        $aDebug = array();
        
        $sql = "CREATE INDEX " . $sVendorTable . "_tmp_idx 
            ON " . $sVendorTable . "_tmp (vendor, identifier_other)";
        
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not create temp index: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
        
    /**
     * Drop the old vendor records table
     * 
     * @param string $sVendorTable
     * @return array
     */
    private function dropVendorTable($sVendorTable)
    {
        $aDebug = array();
        
        $sql = "DROP TABLE IF EXISTS " . $sVendorTable;
        
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not drop vendor table: ' . $e->getMessage();
        }
        
        return $aDebug;

    }
        
    
    /**
     * Drop the index of the old vendor records table
     * 
     * @param string $sVendorTable
     * @return array
     */
    private function dropVendorIndex($sVendorTable)
    {
        $aDebug = array();
        
        $sql = "DROP INDEX IF EXISTS " . $sVendorTable . "_idx";
        
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not drop vendor table index: ' . $e->getMessage();
        }
        
        return $aDebug;
    
    }
        
    
    /**
     * Rename the new table
     * 
     * @param string $sVendorTable
     * @return array
     */
    private function renameTemp($sVendorTable)
    {
        $aDebug = array();
        
        $sql = "ALTER TABLE " . $sVendorTable . "_tmp RENAME TO " . $sVendorTable;
        
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not rename temp vendor table! ' . $e->getMessage();
        }
        
        return $aDebug;
        
    }
    
    /**
     * Rename the index of the new table
     * 
     * @param string $sVendorTable
     * @return array
     */
    private function renameIndex($sVendorTable)
    {
        $aDebug = array();
        
        $sql = "ALTER INDEX " . $sVendorTable . "_tmp_idx RENAME TO " . $sVendorTable . "_idx";
        
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not rename temp index' . $e->getMessage();
        }
        
        return $aDebug;
        
    }
 
    /**
     * Add recent items to the vendor records table
     * 
     * @param string $sVendorsTable
     * @param int $nCollectionId
     * @return array
     */
    private function addRecentToVendorTable($sVendorsTable, $nCollectionId)
    {
        $aDebug = array();
        
        $sql = "INSERT INTO " . $sVendorsTable . 
                    " SELECT DISTINCT 
                        m1.text_value AS vendor,
                        m2.text_value AS identifier_other,
                        i.item_id,
                        i.owning_collection,
                        h.handle,
                        'a' AS mdstatus,
                        i.in_archive,
                        i.withdrawn,
                        i.last_modified
                    FROM
                        item i
                    INNER JOIN metadatavalue m1 ON i.item_id=m1.resource_id AND m1.resource_type_id=2 AND m1.metadata_field_id=151
                    INNER JOIN metadatavalue m2 ON i.item_id=m2.resource_id AND m2.resource_type_id=2 AND m2.metadata_field_id=71
                    INNER JOIN handle h ON i.item_id=h.resource_id AND h.resource_type_id=2
                    LEFT JOIN ubuaux_vendor_recs v ON i.item_id=v.item_id
                    INNER JOIN collection c ON i.owning_collection=c.collection_id AND c.collection_id=" . $nCollectionId .
                    "WHERE i.last_modified > (SELECT v.last_modified FROM ubuaux_vendor_recs v 
                        WHERE v.last_modified IS NOT NULL ORDER BY last_modified DESC LIMIT 1) 
                        AND v.item_id IS NULL";
        
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not add records for ' . $nCollectionId . ' to ' . $sVendorsTable . ': ' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
    /**
     * Create a temporary vendor records table for one vendor
     * 
     * @param string $sVendor
     * @return array
     */
    private function createVendorTemp($sVendor)
    {
        $aDebug = array();
        
        $sql = "SELECT 
                m1.text_value as vendor, 
                m2.text_value as identifier_other, 
                i.item_id as item_id,
                i.last_modified as last_modified,
                h.handle as handle,
                'a' as mdstatus,
                i.in_archive as in_archive,
                i.withdrawn as withdrawn
            INTO TEMPORARY TABLE tmp_" . $sVendor . 
            " FROM
                item i, metadatavalue m1, metadatavalue m2, handle h
             WHERE
                i.item_id=h.resource_id AND i.in_archive='t' AND i.withdrawn='f'
                AND i.item_id=m1.resource_id AND m1.resource_type_id=2 AND m1.metadata_field_id=151 
                AND m1.text_value='" . $sVendor . "'
                AND i.item_id=m2.resource_id AND m2.resource_type_id=2 AND m2.metadata_field_id=71
            ";
        
        $aDebug['sql'] = $sql;
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
            
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not retrieve items for temp table: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
    /**
     * Find items with an embargo that ends two months from now
     * 
     * @return array 
     */
    private function findEmbargoAlert($sPubType)
    {
        $aData = array();
        
        $sql = "SELECT DATE((DATE(m1.text_value) - INTERVAL '2 MONTH')), 
                    m2.text_value as title , e.*, i.item_id, m1.text_value as embargodate 
                FROM item i, handle h, ubuaux_embargo_alert e, metadatavalue m1, metadatavalue m2
                WHERE 
                 m1.resource_id=i.item_id AND m1.resource_type_id=2 AND i.in_archive='t' AND i.withdrawn='f' AND 
                 h.handle=e.handle AND h.resource_id=i.item_id AND
                 m1.metadata_field_id=193 AND LENGTH(m1.text_value)=10 AND 
                 m1.text_value < '2050-01-01' AND 
                 m2.resource_id=m1.resource_id AND m2.resource_type_id=2 AND m2.metadata_field_id=143 AND
                 e.pubtype='$sPubType' 
               ORDER by m1.text_value";
        
        try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
                $aData['data'][] = $row;
            }
            pg_free_result($result);
        }
        catch (Exception $e)
        {
            $aData['error'] = 'could not find embargo alert data: '  . $e->getMessage();
        }
        
        return $aData;
    }
    
    /**
     * Delete all records from the given table
     * 
     * @param type $sTableName
     * @return array
     */
    private function resetAuxTable($sTableName)
    {
        $aDebug = array();
        
        $sql = "DELETE FROM " . $sTableName;
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not delete ' . $sTableName . ': ' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
    /**
     * Find all items with an embargo that ends more than two months from now
     * 
     * @return array
     */
    private function findFutureEmbargoData()
    {
        $aData = array();
        
        $sTwoMonths =  date('Y-m-d', strtotime("+2 month"));
        //$sTwoMonths =  date('Y-m-d', strtotime("-1 month"));
        
        /*
        $sql = "SELECT h.handle, e.email, m.text_value 
                FROM handle h 
                INNER JOIN item i ON i.item_id=h.resource_id AND i.in_archive='t' AND i.withdrawn='f'
                INNER JOIN metadatavalue m ON h.resource_id=m.item_id AND m.metadata_field_id=193 AND 
                    LENGTH(m.text_value)=10 AND m.text_value < '2050-01-01' 
                        AND m.text_value > '$sTwoMonths' 
                 LEFT JOIN ubuaux_embargo_alert e ON h.handle=e.handle
                 WHERE h.resource_type_id=2";
         * 
         */
        $sql = "SELECT h.handle, e.email, e.pubtype, m.text_value, i.item_id  
                FROM handle h 
                INNER JOIN item i ON i.item_id=h.resource_id AND i.in_archive='t' AND i.withdrawn='f'
                INNER JOIN metadatavalue m ON h.resource_id=m.resource_id AND m.resource_type_id=2 AND m.metadata_field_id=193 AND 
                    LENGTH(m.text_value)=10 AND m.text_value < '2050-01-01' 
                        AND m.text_value > '$sTwoMonths' 
                 LEFT JOIN ubuaux_embargo_alert e ON h.handle=e.handle
                 WHERE h.resource_type_id=2";

        try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
                $aData['data'][] = $row;
            }
            pg_free_result($result);
        }
        catch (Exception $e)
        {
            $aData['error'] = 'could not find embargo data: '  . $e->getMessage();
        }
        
        return $aData;
    }
    
    /**
     * Insert a record into the embargo alert aux table
     * The record contains the handle of the item with the embargo
     * and the email address to which the alert should be sent.
     * 
     * @param string $sHandle
     * @param string $sEmail
     * @param string $sPubType
     * @return array
     */
    private function insertEmbargoEmail($sHandle, $sEmail, $sPubType)
    {
        $aDebug = array();
        
        $sql = "INSERT INTO ubuaux_embargo_alert VALUES('" . $sHandle . "', '" . $sEmail . "', '" . $sPubType . "')";
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
            
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not insert into ubuaux_embargo_alert: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
    /**
     * Update the email address to which an embargo alert should be sent.
     * 
     * @param type $sHandle
     * @param type $sEmail
     * @return array
     */
    private function updateEmbargoEmail($sHandle, $sEmail)
    {
        $aDebug = array();
        
        $sql = "UPDATE ubuaux_embargo_alert SET email='" . $sEmail . "' WHERE handle='" . $sHandle . "'";
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not update ubuaux_embargo_alert: ' . $e->getMessage();
        }
      
         return $aDebug;
    }

    
    private function checkItemPod($aItemPodData)
    {
        $aItemCheckData = array();
        
        $sHandle = $aItemPodData['handle'];
        $query = "SELECT * from ubuaux_pod where handle='" . $sHandle . "'";
        try {
            $result = pg_query($this->dbconn, $query);
    
            while ($row = pg_fetch_assoc($result)) {
                $aItemCheckData = $row;
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aItemCheckData['error'] = 'could not count pod data: ' . $e->getMessage();
            
        }     
        
        return $aItemCheckData;
    }
    

    private function addPodItem($aItemPodData)
    {
        $aDebug = array();
        
        $query = "INSERT into ubuaux_pod (item_id, handle, price) values (
            '" . $aItemPodData['itemid'] . "', '" . $aItemPodData['handle'] . "',
            '" . $aItemPodData['price'] . "')";
        //$aDebug['sql'] = $query;
        
        try {
            pg_query($this->dbconn, $query);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not add to ubuaux_pod: ' . $e->getMessage();
        }
        
        
        return $aDebug;   
        
     }  
    
     private function getAuthorNotification($sHandle)
     {
         $aCheckData = array();
         
         $query = "SELECT * from ubuaux_notify_author where handle='" . $sHandle . "'";
         try {
            $result = pg_query($this->dbconn, $query);
    
            while ($row = pg_fetch_assoc($result)) {
                $aCheckData = $row;
            }
            pg_free_result($result);            
         } 
         catch (Exception $ex) {
             $aCheckData['error'] = 'could not check author notification' . $ex->getMessage();
         }
         
         return $aCheckData;
     }
     
    private function addAuthorNotification($sHandle)
    {
        $aDebug = array();
        
        $query = "INSERT into ubuaux_notify_author (handle) values ('" . $sHandle . "')";
        
        try {
            pg_query($this->dbconn, $query);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not add to ubuaux_notify_author: ' . $e->getMessage();
        }
        
        
        return $aDebug;          
    }    
}

?>
