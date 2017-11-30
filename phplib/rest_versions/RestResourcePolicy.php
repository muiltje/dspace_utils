<?php
/**
 * Functions for resource policies
 *
 * @author muilw101
 */
require_once 'DatabaseConnector.php';
require_once 'RestConnector.php';

class RestResourcePolicy {
    private $dbconn;
    private $oRest;
    
    public function __construct() {
        $oDC = new DatabaseConnector();
        $this->dbconn = $oDC->getConnection();
        $this->oRest = new RestConnector();
    }
    
	/**
	 * Get all resource policies for this bitstream
	 * @param type $nBitstreamId
	 * @return type
	 */
    public function getBitstreamPolicies($nBitstreamId)
	{
		$aPolicyData = array();
		$sService = 'bitstreams/' . $nBitstreamId . '/policy';
		$aData = $this->oRest->doRequest($sService, 'GET', '');
		if (isset($aData['response'])) {
		$sPolicyData = $aData['response'];
			$aPolicyData = json_decode($sPolicyData, true);
	}
		return $aPolicyData;
	}
 
	/**
	 * Add a bitstream resource policy
	 * @param type $aPolicyData
	 * @return type
	 */
	public function addBitstreamPolicy($aPolicyData) {
		$aAddResult = array();
		
		$nBitstreamId = $aPolicyData['resourceId'];
		$sService = 'bitstreams/' . $nBitstreamId . '/policy';
		$sPolicy = json_encode($aPolicyData);
		$aResult = $this->oRest->doRequest($sService, 'POST', $sPolicy);
		if (isset($aResult['response'])) {
			$aAddResult = json_decode($aResult['response'], true);
		}
		
		return $aAddResult;
	}
	
	/**
	 * Delete a bitstream resource policy
	 * @param type $nBitstreamId
	 * @param type $nPolicyId
	 * @return type
	 */
	public function deleteBitstreamPolicy($nBitstreamId, $nPolicyId)
	{
		$aDeleteResult = array();
		
		$sService = 'bitstreams/' . $nBitstreamId . '/policy/' . $nPolicyId;
		$aResult = $this->oRest->doRequest($sService, 'DELETE', '');
		if (isset($aResult['response'])) {
			$aDeleteResult = json_decode($aResult['response'], true);
		}
		
		return $aDeleteResult;
	}
	
	/**
	 * Update an existing resource policy
	 * The REST API doesn't (yet) support updating policies,
	 * so we'll do that with the database
	 * 
	 * @param string $sStartDate
	 * @param int $nPolicyId
	 * @return string
	 */
	public function updateBitstreamPolicy($sStartDate, $nPolicyId)
	{
        $sql = "update resourcepolicy set start_date='$sStartDate' where policy_id=$nPolicyId";
        
        try {
            $updateresult = pg_query($this->dbconn, $sql);
            pg_free_result($updateresult);
        }
        catch (Exception $e) {
            return 'n: ' . $e->getMessage();
        }
        
        return 'y';
	}
	
	    /**
     * Get all resources with a resource policy that starts in the past
     * 
     * @param int $nResourceTypeId 
     * @return array
     */
    public function getPastEmbargoedResources($nResourceTypeId = NULL)
    {
        $aPastEmbargoed = array();
        
        $query = "select * from resourcepolicy where start_date <= current_date";
		if (isset($nResourceTypeId)) {
			$query .= " and resource_type_id=" . $nResourceTypeId;
		}
        
        try {
            $result = pg_query($this->dbconn, $query);
            while ($row = pg_fetch_assoc($result)) {
                $policyid = $row['policy_id'];
				$resourceid = $row['resource_id'];
            
                $aPastEmbargoed[] = array('policyid' => $policyid, 'resourceid' => $resourceid);
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aPastEmbargoed['error'] = 'could not get past embargoed resources: ' . $e->getMessage();
        }
        
        return $aPastEmbargoed;
    }
	
	/**
     * Get all resource policies for a given resource type and resource id
	 * from the database
     * 
     * @param int $nResourceType
     * @param int $nResourceId
     * @return array
     */
    public function getResourcePolicies($nResourceType, $nResourceId)
    {
        $aPolicies = array();
    
        $query = "select * from resourcepolicy where resource_type_id=$nResourceType 
            and resource_id=$nResourceId";
    
        try {
            $result = pg_query($this->dbconn, $query);
    
            while ($row=  pg_fetch_assoc($result)) {
                $aPolicies[] = $row;
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aPolicies['error'] = 'could not get resource policies for ' . $nResourceId . ': ' . $e->getMessage();
        }
        
        return $aPolicies;
    }
	
	
	/**
     * Delete a resource policy directly from the database
	 * Use this function if you want to delete policies for items or bundles
     * 
     * @param int $nPolicyId
     * @return string
     */
    public function deleteResourcePolicyDb($nPolicyId)
    {
        $sql = "delete from resourcepolicy where policy_id=$nPolicyId";
        
        try {
            $deleteresult = pg_query($this->dbconn, $sql);
            pg_free_result($deleteresult);
        }
        catch (Exception $e) {
            return 'n: ' . $e->getMessage();
        }
        return 'y';

    }
}