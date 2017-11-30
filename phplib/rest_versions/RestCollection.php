<?php
require_once 'RestConnector.php';


/**
 * Description of Collection
 *
 * @author muilw101
 */
class RestCollection {
     private $oRest;
   
    
    public function __construct() {
        $this->oRest = new RestConnector();
    }
    
	/**
	 * @todo: there seems to be something wrong with /rest/collections
	 * since it gives no response for most collection ids
	 * 
	 * @param type $nCollectionId
	 * @return type
	 */
	public function getCollectionData($nCollectionId) 
	{
		$sService = 'collections/' . $nCollectionId;
		
		$sCollectionResponse = $this->oRest->doRequest($sService, 'GET', '');
		$aCollectionData = json_decode($sCollectionResponse['response'], true);
		return $aCollectionData;
	}
    
	/**
	 * This uses a default limit of 100 items.
	 * So if you want to use it for collections that are larger than that
	 * (and most are), you must first get the total number of items
	 * (numberItems) and page based on that
	 * @param type $nCollectionId
	 * @return type
	 */
	public function getCollectionItems($nCollectionId)
	{
		$sService = 'collections/' . $nCollectionId . '?expand=items';
		$sCollectionResponse = $this->oRest->doRequest($sService, 'GET', '');
		$aCollectionData = json_decode($sCollectionResponse['response'], true);
		$aItems = $aCollectionData['items'];
		
		return $aItems;
	}
}
