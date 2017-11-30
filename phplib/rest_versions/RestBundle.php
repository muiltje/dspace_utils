<?php
require_once 'DatabaseConnector.php';
        
/**
 * Functions to do with bundles
 *
 * @author muilw101
 */
class RestBundle {
    private $dbconn;
    
    
    public function __construct() {
        $oDC = new DatabaseConnector();
        $this->dbconn = $oDC->getConnection();
        
    }
    
    
    /**
     * Get all the bundles for the given item
     * 
     * @param int $nItemId
     * @return array
     */
    public function getItemBundles($nItemId) {
        $aBundles = array();
    
        $query = 'select * from item2bundle where item_id=' . $nItemId;
        
        try {
            $result = pg_query($this->dbconn, $query);
             while ($row=  pg_fetch_assoc($result)) {
                $aBundles[] = $row;
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aBundles['error'] = 'could not find bundles for item ' .$nItemId;
        }
        
        return $aBundles;
    }
   
    /**
     * Get the item to which a given bundle belongs
     * 
     * @param int $nBundleId
     * @return int
     */
    public function getBundleItem($nBundleId)
    {
        $nItemId = 0;
        
        $query = "select * from item2bundle where bundle_id=" . $nBundleId;
        
        try {
            $result = pg_query($this->dbconn, $query);
        
            while ($row = pg_fetch_assoc($result)) {
                $nItemId = $row['item_id'];
            }
        
            pg_free_result($result);
        }
        catch (Exception $e) {
            $nItemId = 0;
        }
        return $nItemId;
        
    }
    
    public function getBundleDetails($nBundleId)
    {
        $aDetails = array();
        
        //$query = "select * from bundle where bundle_id=" . $nBundleId;
		$query = "select text_value as name from metadatavalue where "
				. "resource_id=" . $nBundleId . " and resource_type_id=1 "
				. "and metadata_field_id=143";
        
        try {
            $result = pg_query($this->dbconn, $query);
            
            while ($row = pg_fetch_assoc($result)) {
                $aDetails = $row;
            }
            
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aDetails['error'] = $e->getMessage();
        }
        
        return $aDetails;
    }    
    
}

?>
