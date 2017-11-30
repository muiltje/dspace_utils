<?php
require_once 'DatabaseConnector.php';
require_once 'RestConnector.php';
/**
 * Functions to do with Bitstreams
 *
 * @author muilw101
 */
class RestBitstream {
    private $dbconn;
    private $sAssetStoreBase;
	private $oRest;
    
    public function __construct() {
        $oDC = new DatabaseConnector();
        $this->dbconn = $oDC->getConnection();
		$this->oRest = new RestConnector();
	    
        $inifile = '/home/dspace/utils/phplib/config/dspace.ini';
        $this->aIniArray = parse_ini_file($inifile, true);

        $sHostname = strtolower(php_uname('n'));
        if (preg_match('/grieg/', $sHostname)) {
            $this->sHost = 'grieg';
        }
		elseif (preg_match('/elgar/', $sHostname)) {
			$this->sHost = 'elgar';
		}
		elseif (preg_match('/bizet/', $sHostname)) {
			$this->sHost = 'bizet';
		}
		else {
            $this->sHost = 'elgar';
        }
        $this->sAssetStoreBase = $this->aIniArray[$this->sHost]['assetstorebase'];
		

    }
    
    /**
	 * Get all bitstreams for this items from the REST API
	 * NB: if you use /bitstream you may get fewer bitstreams than with
	 * ?expand=bitstreams
	 * 
	 * @param int $nItemId
	 * @return array
	 */
	public function getItemBitstreamsRest($nItemId)
	{
		$aBitstreamData = array();
		
		$sService = 'items/' . $nItemId . '?expand=bitstreams';
		
		$aData = $this->oRest->doRequest($sService, 'GET', '');
		if (isset($aData['response'])) {
			$sBitstreams = $aData['response'];
			$aBitstreamData = json_decode($sBitstreams, true);
		}
		return $aBitstreamData;
	}
	


    /**
     * Get all the bitstreams that belong to the given bundle
	 * 
     * @param int $nBundleId
     * @return array
     */
    public function getBundleBitstreams($nBundleId)
    {
        $aBitstreams = array();
    
        $query = 'select * from bundle2bitstream where bundle_id=' . $nBundleId;
        
        try {
            $result = pg_query($this->dbconn, $query);
    
            while ($row=  pg_fetch_assoc($result)) {
                $aBitstreams[] = $row;
            }
    
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aBitstreams['error'] = 'could not find bitstreams for bundle ' . $nBundleId;
        }
        
        return $aBitstreams;
    }
	

	  /**
     * Get the full path to a bitstream
     * 
     * @param string $sInternalId
     * @param string $sStoreNumber
     * @return string
     */
    public function getBitstreamPath($sInternalId, $sStoreNumber)
    {
        $nDigitsPerLevel = 2;
        $nDirectoryLevels = 3;
    
        $sPath = $this->sAssetStoreBase;
        
        if ($sStoreNumber > 0) {
            $sPath .= $sStoreNumber;
        }
        $sPath .= '/';
    
        //derive subdirs from internal id
        for ($i=0; $i<$nDirectoryLevels; $i++) {
            $nextstart = $nDigitsPerLevel * $i;
            $sPath .= substr($sInternalId, $nextstart, $nDigitsPerLevel) . '/';
        }
        $sPath .= $sInternalId;
    
        return $sPath;
    }
	
	/**
	 * Get all bitstream data for a given bitstream from the database
	 * 
	 * @param int $nBitstreamId
	 * @return arraye
	 */
	public function getBitstreamData($nBitstreamId)
    {
        $aBitstreamData = $this->findBitstreamBasic($nBitstreamId);
		$aNameData = $this->findBitstreamName($nBitstreamId);
		$aBitstreamData['name'] = $aNameData['text_value'];
        
		return $aBitstreamData;
     }
	
	/**
	 * Get bitstream data from the REST API. This will only give you public information,
	 * not the "internal" stuff about where the file is stored
	 * 
	 * @param int $nBitstreamId
	 * @return string
	 */
	public function getBitstreamDataRest($nBitstreamId)
	{
		$aBitstreamData = array();
		
		$sService = 'bitstreams/' . $nBitstreamId . '?expand=all';
		$aData = $this->oRest->doRequest($sService, 'GET', '');
		if (isset($aData['response'])) {
			$sBitstreamData = $aData['response'];
			$aBitstreamData = json_decode($sBitstreamData, true);
		}
		return $aBitstreamData;
	}
	
	/**
	 * The REST API will give you most info about a bitstream,
	 * but not the data about storage on the file system. So use this function
	 * if you need that "internal" data
	 * 
	 * @param int $nBitstreamId
	 * @return array
	 */
	public function getBitstreamInternal($nBitstreamId)
	{
		$aAllInternalBitstreamData = $this->findBitstreamBasic($nBitstreamId);
		return $aAllInternalBitstreamData;
	}
	
	
	public function deleteBitstreamRest($nBitstreamId)
	{
		$sService = 'bitstreams/' . $nBitstreamId;
		$sResult = $this->oRest->doRequest($sService, 'DELETE', '');
		
		return $sResult;

	}
	
	
	/**
	 * Use this when you need the internal_id
	 * @param int $nBitstreamId
	 * @return array
	 */
	private function findBitstreamBasic($nBitstreamId)
	{
	    $query = "select * from bitstream where bitstream_id=" . $nBitstreamId;
                
        try {
            $result = pg_query($this->dbconn, $query);
            while ($row=  pg_fetch_assoc($result)) {
                $aBitstreamData = $row;
            }
             pg_free_result($result);
        }
        catch (Exception $e) {
            $aBitstreamData['error'] = $e->getMessage();
        }
        
        return $aBitstreamData;
	}

	/**
	 * Find the name of the given bitstream. 
	 * You can use this if you have found the bitstream id in the database
	 * and also need to know the name.
	 * 
	 * @param int $nBitstreamId
	 * @return array
	 */
	private function findBitstreamName($nBitstreamId)
	{
		$query = "select * from metadatavalue where resource_id=" . $nBitstreamId
				. " and resource_type_id=0 and metadata_field_id=143";
		
		try {
            $result = pg_query($this->dbconn, $query);
            while ($row=  pg_fetch_assoc($result)) {
                $aBitstreamNameData = $row;
            }
             pg_free_result($result);
        }
        catch (Exception $e) {
            $aBitstreamNameData['error'] = $e->getMessage();
        }
        
        return $aBitstreamNameData;

		
	}
	
	/*
	private function parseSequence($sMetadataResponse)
	{
		$aSequence = array();
		$aMetadataFields = $sMetadataResponse['metadata'];
		foreach ($aMetadataFields as $aOneField) {
			$sKey = $aOneField['key'];
			if ($sKey == 'dc.sequence') {
				$sSequenceValue = $aOneField['value'];
				$aSequence = explode('+', $sSequenceValue);
			}
		}
		return $aSequence;
	}
	 * 
	 */
	
}

