<?php
/**
 * Functions for PDFs
 *
 * @author muilw101
 */

require_once 'DatabaseConnector.php';

class RestPDF {
    private $dbconn;
    
    
    public function __construct() {
        
        $oDC = new DatabaseConnector();
        $this->dbconn = $oDC->getConnection();
        
    }
    
    /**
	 * Alternative: find bitstreams with REST GET /items/{item id}/bitstreams
	 * and check for pdf
	 * @param type $nItemId
	 * @return string
	 */
	public function findDissertationPdf($nItemId)
	{
		$aPdfData = array();
		
		$sql = "SELECT i.item_id, 
					bs.internal_id, 
					bs.store_number, 
					bs.sequence_id, 
					m.text_value as nrofpages  
				FROM item i 
					INNER JOIN (item2bundle i2bu 
						INNER JOIN (bundle2bitstream bu2bs
							INNER JOIN (bitstream bs 
								INNER JOIN bitstreamformatregistry bf ON bs.bitstream_format_id=bf.bitstream_format_id AND bf.mimetype LIKE '%%/pdf')
							ON bu2bs.bitstream_id=bs.bitstream_id)
						ON i2bu.bundle_id=bu2bs.bundle_id)
					ON i.item_id=i2bu.item_id
					LEFT JOIN metadatavalue m ON i.item_id=m.resource_id AND m.resource_type_id=2 AND m.metadata_field_id=264
			WHERE i.item_id=" . $nItemId;
		
		
		try {
            $result = pg_query($this->dbconn, $sql);
    
            while ($row = pg_fetch_assoc($result)) {
                $aPdfData[] = $row;
            }
         }
        catch (Exception $e) {
            $aPdfData['error'] = 'failed to find the pdfs: ' . $e->getMessage();
        }
   
		return $aPdfData;
	}
	
	
	
    
    /**    
     * Count the pages of a given PDF
     * 
     * @param string $sFilePath
     * @return string 
     */
    
    public function countPages($sFilePath)
    {
        //test if file exists
        $sPageCount = 0;
        
        //$sNow = date('Y-m-d H:i:s');
        
        if (file_exists($sFilePath)) {
            try {
                //echo $sNow . ': starting pdfinfo for ' . $sFilePath . "\n";
                $output = shell_exec("/usr/bin/pdfinfo " . $sFilePath);
        
                //output is a string, so we need to parse it to get the number of pages
                preg_match('/Pages:\s+(\d{0,4})/', $output, $pagematches);
        
                $sPageCount = $pagematches[1];
            }
            catch (Exception $e) {
                $sPageCount = $e->getMessage();
            }
        }
        
        return $sPageCount;
    }
    
}
