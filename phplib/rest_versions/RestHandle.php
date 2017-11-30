<?php
require_once 'RestConnector.php';
require_once 'DatabaseConnector.php';


/**
 * Functions for handles
 *
 * @author muilw101
 */
class RestHandle {
    private $oRest;
	private $dbconn;
    
    public function __construct() {
        $this->oRest = new RestConnector();
		$oDC = new DatabaseConnector();
		$this->dbconn = $oDC->getConnection();
    }
    
    
    /**
     * Get the handle for the given item
     * 
     * @param int $nItemId
     * @return array
     */
    public function getHandle($nItemId)
    {
		$sService = 'items/' . $nItemId;
		$aFound = $this->oRest->doRequest($sService, 'GET', '');
		$sResponse = $aFound['response'];
		$aItemData = json_decode($sResponse, true);
		$sHandle = $aItemData['handle'];
		$sHandleId = substr($sHandle, 5);
		
		$aHandleData = array(
			'handle' => $sHandle,
			'handleid' => $sHandleId,
			);
				
		return $aHandleData;
    }
    
    /**
     * Get the item_id for a given handle
     * 
     * @param string $sHandle
     * @return array
     */
    public function getItemId($sHandle)
    {
		$sService = 'handle/' . $sHandle;
		$aFound = $this->oRest->doRequest($sService, 'GET', '');
		$sResponse = $aFound['response'];
		$aItemData = json_decode($sResponse, true);
		$nItemId = $aItemData['id'];
		
		$aResults['itemid'] = $nItemId;
		return $aResults;
		
    }
	
	/**
	 * 
	 * @param type $nItemId
	 * @return string
	 */
	public function getHandleDb($nItemId)
	{
		$aResults = array();
        
        $sql = "SELECT * FROM handle WHERE resource_type_id=2
            AND resource_id=$nItemId";
        
        try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
                $aResults['handleid'] = $row['handle_id']; //this is the number
                $aResults['handle'] = $row['handle']; // this is the version with prefix
               }
        }
        catch (Exception $e) {
            $aResults['error'] = 'could not find handle for ' . $nItemId . ': ' . $e->getMessage();
        }
        
        return $aResults;

	}
	
	/**
	 * Get the item id for a given handle from the database
	 * Use this when the REST version proves too unstable
	 * 
	 * @param type $sHandle
	 * @return string
	 */
	public function getItemIdDb($sHandle)
    {
        $aResults = array();
        
        $sql = "SELECT resource_id from handle where handle='". $sHandle . "'
            AND resource_type_id=2";
        
        $aResults['sql'] = $sql;
        
        try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
                $aResults['itemid'] = $row['resource_id'];
            }
        }
        catch (Exception $e) {
            $aResults['error'] = 'could not find itemid for ' . $sHandle . ': ' . $e->getMessage();
        }
        
        return $aResults;
    }
}

