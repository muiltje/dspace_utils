<?php
require_once 'DatabaseConnector.php';
/**
 * Various functions for ubuaux tables
 *
 * @author muilw101
 */

class RestMetadataAuxTable {
    private $dbconn;
    
    
    public function __construct() {
        $oDC = new DatabaseConnector();
        $this->dbconn = $oDC->getConnection();
        
    }

    /**
     * Get the metadata that are in the ubuaux_metadata table for the given handle
     * 
     * @param string $sHandle
     */
    public function getMetadataAuxValue($sHandle, $nMetadataFieldId)
    {
        $aDebug = array();
        
        $sql = "SELECT * from ubuaux_metadata where metadata_field_id=" . $nMetadataFieldId
                . " AND handle='" . $sHandle . "'";
        
        try {
            $result = pg_query($this->dbconn, $sql);
    
            while ($row = pg_fetch_assoc($result)) {
                $aDebug[] = $row;
            }
            pg_free_result($result);
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not get metadata value: ' . $e->getMessage();
        }    
        
        return $aDebug;
    }

    
    /**
     * Add new metadata to the ubuaux_metadata table
     * 
     * @param string $sHandle
     * @param array $aData
     */
    public function addMetadataAuxValues($aDataToSave)
    {
        $aDebug = array();
        
        $sHandle = $aDataToSave['handle'];
        $nMetadataFieldId = $aDataToSave['metadatafieldid'];
        $aData = $aDataToSave['data'];
        
        $sTextValue = $aData['text_value'];
        $sTextLang = $aData['text_lang'];
        $nPlace = 1;
        if (!empty($aData['place'])) {
            $nPlace = $aData['place'];
        }
        $sAuthority = $aData['authority'];
        $nConfidence = $aData['confidence'];
        
        $sql = "INSERT INTO ubuaux_metadata ("
                . "metadata_field_id,"
                . "handle,"
                . "text_value,"
                . "text_lang,"
                . "place,"
                . "authority,"
                . "confidence"
                . ") VALUES ("
                . $nMetadataFieldId . ","
                . "'" . $sHandle . "',"
                . "'" . pg_escape_string($sTextValue) . "',"
                . "'" . $sTextLang . "',"
                . $nPlace . ", "
                . "'" . pg_escape_string($sAuthority) . "',"
                . $nConfidence
                . ")";
        
        //$aDebug['sql'] = $sql;
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'add metadata value failed: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
  
    
    
}


