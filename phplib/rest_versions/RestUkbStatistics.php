<?php

/**
 * Generate UKB statistiscs
 *
 * @author muilw101
 */
require_once 'DatabaseConnector.php';


class RestUkbStatistics {
    private $dbconn;
    
    
    public function __construct() {
        $oDC = new DatabaseConnector();
        $this->dbconn = $oDC->getConnection();
        
    }
        
    /**
     * Get the basics: fields, collections, communities
     * 
     * @return array
     */
    public function initStatistics()
    {
        $aInitResults = array();
        
        $aFieldResults = $this->findFieldIds();
        if (isset($aFieldResults['error'])) {
            $aInitResults['errors'][] = 'could not find fieldids';
        }
        else {
            $aInitResults['fields'] = $aFieldResults;
        }
        
		$aCollectionResults = $this->findCollections();
        if (isset($aCollectionResults['error'])) {
            $aInitResults['errors'][] = $aCollectionResults['error'];
        }
        else {
            $aInitResults['collections'] = $aCollectionResults['collections'];
        }

        
        $aCommunityResults = $this->findCommunities();
        if (isset($aCommunityResults['error'])) {
            $aInitResults['errors'][] = $aCommunityResults['error'];
        }
        else {
            $aInitResults['communities'] = $aCommunityResults['communities'];
        }
        
        return $aInitResults;
    }
    
    /**
     * Update (or rather, drop and recreate) the statistics table
     * 
     * @param array $aFieldIds 
     * @return array
     */
    public function updateStatsTable($aFieldIds)
    {
        $this->dropStatsTable();
         $aCreate = $this->createStatsTable($aFieldIds);
        
        return $aCreate;
    }
    
    /**
     * Get statistics per collection
     * 
     * @param int $nFirstYear
     * @param int $nThisYear
     * @return array
     */
    public function getCollectionStats($nFirstYear, $nThisYear)
    {
        $aStats = array();
        
        for ($i = $nThisYear; $i >= $nFirstYear; $i--) {
            $aStats[$i] = $this->findCollectionStats($i);
        }
        
        return $aStats;
    }
    
    /**
     * Get statistics per type content
     * 
     * @param int $nFirstYear
     * @param int $nThisYear
     * @return array
     */
    public function getTypeContentStats($nFirstYear, $nThisYear)
    {
        $aStats = array();
        for ($i = $nThisYear; $i >= $nFirstYear; $i--) {
            $aStats[$i] = $this->findTypeContentStats($i);
        }
        
        return $aStats;
    }
    
    /**
     * Get statistics per type content per community
     * 
     * @param int $nFirstYear
     * @param int $nThisYear
     * @return array
     */
    public function getTypeContentPerCommunity($nFirstYear, $nThisYear)
    {
        $nLastYear = $nThisYear-1;
        $nYearBefore = $nThisYear-2;
        $nYearsAgo = $nThisYear-3;
        
        $aFirstTotal = $this->findTypeContentPerCommunity($nLastYear, 1);
        $aFirstMapped = $this->findMappedItems($nLastYear, 1);
        $aSecondTotal = $this->findTypeContentPerCommunity($nYearBefore, 1);
        $aSecondMapped = $this->findMappedItems($nYearBefore, 1);
        $aThirdTotal = $this->findTypeContentPerCommunity($nYearsAgo, 1);
        $aThirdMapped = $this->findMappedItems($nYearsAgo, 1);
        
        $aStatsGroup = array();
        for ($i = $nThisYear; $i >= $nFirstYear; $i--) {
            $aStatsGroup[$i]['normal'] = $this->findTypeContentPerCommunity($i, 0);
            $aStatsGroup[$i]['mapped'] = $this->findMappedItems($i, 0);
        }
        
        $aStats = array(
            $nLastYear => array('normal'=> $aFirstTotal, 'mapped' => $aFirstMapped),
            $nYearBefore => array('normal' => $aSecondTotal, 'mapped' => $aSecondMapped),
            $nYearsAgo => array('normal' => $aThirdTotal, 'mapped' => $aThirdMapped),
            'peryear' => $aStatsGroup,
        );
        
        return $aStats;
    }
    
    /**
     * Find the field ids for the date.issued and the type.content
     * 
     * @return array
     */
    private function findFieldIds()
    {
        $aDebug = array();
        
        $sql = "SELECT metadata_field_id,element,qualifier
                 FROM metadatafieldregistry 
                WHERE 
                 (element='date' AND qualifier='issued') OR 
                 (element='type' AND qualifier='content')";
        
        try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
               $aDebug[] = $row;
            }
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not find fieldids: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
    /**
     * Drop the existing statistics table
     * 
     * @return array
     */
    private function dropStatsTable()
    {
        $aDebug = array();
        
        $sql = "DROP TABLE IF EXISTS ubuaux_ukbstats";
        
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not drop ukbstats table: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
    
    /**
     * Create a new statistics table
     * 
     * @param array $aFieldIds
     * @return array
     */
    private function createStatsTable($aFieldIds)
    {
        $aDebug = array();
        
        $sql = "CREATE TABLE ubuaux_ukbstats AS 
                    (SELECT 
                        i.item_id AS id,
                        m2.text_value AS type_content,
                        SUBSTR(m1.text_value,1,4) AS year,
                        i.owning_collection AS cid
                    FROM item i
                    INNER JOIN metadatavalue m1 ON i.item_id=m1.resource_id
						AND m1.resource_type_id=2
                        AND m1.metadata_field_id=" . $aFieldIds['date_issued'] . "
                    LEFT JOIN metadatavalue m2 ON i.item_id=m2.resource_id
						AND m2.resource_type_id=2 
                        AND m2.metadata_field_id=" . $aFieldIds['type_content'] . "
                    WHERE i.withdrawn='f'";
         $sql .= "UNION ALL
                    SELECT i.item_id AS id,
                        m2.text_value AS type_content,
                        SUBSTR(m1.text_value,1,4) AS year,
                        i.collection_id AS cid
                    FROM workflowitem i
                    INNER JOIN metadatavalue m1 ON i.item_id=m1.resource_id 
						AND m1.resource_type_id=2
                        AND m1.metadata_field_id=" . $aFieldIds['date_issued'] . "
                    LEFT JOIN metadatavalue m2 ON i.item_id=m2.resource_id
						AND m2.resource_type_id=2
                        AND m2.metadata_field_id=" . $aFieldIds['type_content'] . ")";
        
        //$aDebug['sql'] = $sql;
        
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not create new ukbstats table: ' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
    /**
     * Find the numbers per collection
     * 
     * @param int $nYear
     * @return array
     */
    private function findCollectionStats($nYear)
    {
		$aCollStats = array();
		
		 $sql = "SELECT
                    m.text_value AS community,
                    s.cid AS cid,
                    COUNT(*) as total 
                 FROM
                    collection c,
                    community2collection c2c,
                    metadatavalue m,
                    ubuaux_ukbstats s
                 WHERE 
                    s.cid=c.collection_id AND 
                    c.collection_id=c2c.collection_id AND 
                    c2c.community_id=m.resource_id 
					AND m.resource_type_id=4 AND m.metadata_field_id=143 AND
                    s.year='" . $nYear . "' 
                 GROUP BY community,cid";
        
		 try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
                $aStats[] = $row;
            }
            $aCollStats['stats'] = $aStats;
        }
        catch (Exception $e) {
            $aCollStats['error'] = 'could not find collection stats for year ' . $nYear . ': ' . $e->getMessage();
        }
                
        return $aCollStats;

		
	}
    
    /**
     * Find the numbers per type content
     * 
     * @param int $nYear
     * @return array
     */
    private function findTypeContentStats($nYear)
    {
        $aDebug = array();
        $aStats = array();
        
        $sql = "SELECT s.type_content, count(*) as total
            FROM ubuaux_ukbstats s
            WHERE s.year='" . $nYear . "'
                GROUP BY s.type_content 
                ORDER BY s.type_content";
        
        try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
                $aStats[] = $row;
            }
            $aDebug['stats'] = $aStats;
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not find collection stats for year ' . $nYear . ': ' . $e->getMessage();
        }
                
        return $aDebug;
    }
    
    /**
     * Find the numbers per type content per community
     * 
     * @param int $nYear
     * @param int $bInclude
     * @return array
     */
    private function findTypeContentPerCommunity($nYear, $bInclude)
    {
        $aResult = array();
		
		$sql = "SELECT m.text_value as community,  
                CASE s.type_content
                   WHEN 'Article' THEN 0 
                   WHEN 'Article in proceedings' THEN 0
                   WHEN 'Part of book' THEN 1
                   WHEN 'Part of book or chapter of book' THEN 1
                   WHEN 'Conference lecture' THEN 1
                   WHEN 'Conference report' THEN 1
                   WHEN 'Dissertation' THEN 2
                   WHEN 'Book' THEN 3
                   WHEN 'Report' THEN 3
                   ELSE 3 
                 END AS typecontent,
                 COUNT(*) as total
                 FROM
                    collection c,
                    community2collection c2c,
                    metadatavalue m,
                    ubuaux_ukbstats s
                 WHERE";
        if ($bInclude == 1) {
             $sql .= " s.year <= '" . $nYear . "'";
        }
        else {
            $sql .= " s.year = '" . $nYear . "'";
        }
        $sql .= " AND s.cid=c.collection_id 
                AND c.collection_id=c2c.collection_id
                AND c2c.community_id=m.resource_id
				AND m.resource_type_id=4 AND m.metadata_field_id=143
                GROUP BY community,typecontent 
                ORDER BY community,typecontent";
		
		$aStats = array();
		try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
                $aStats[] = $row;
            }
            $aResult['stats'] = $aStats;
        }
        catch (Exception $e) {
            $aResult['error'] = 'could not find community stats: ' . $e->getMessage();
        }
        
        return $aResult;

    }
    
    /**
     * Find the numbers for items that have been mapped to a collection
     * 
     * @param int $nYear
     * @param int $bInclude
     * @return array
     */
    private function findMappedItems($nYear, $bInclude) 
    {
		$aResult = array();
        $aStats = array();
        
        $sql = "SELECT
                 community, 
                 typecontent,
                 COUNT(*) as total
               FROM
                 (SELECT DISTINCT
                   s.id,
                   m.text_value AS community, 
                   CASE s.type_content
                      WHEN 'Article' THEN 0
                      WHEN 'Article in proceedings' THEN 0
                      WHEN 'Part of book' THEN 1
                      WHEN 'Part of book or chapter of book' THEN 1
                      WHEN 'Conference lecture' THEN 1
                      WHEN 'Conference report' THEN 1
                      WHEN 'Dissertation' THEN 2
                      WHEN 'Book' THEN 3
                      WHEN 'Report' THEN 3
                      ELSE 3
                   END AS typecontent
                 FROM
                   collection c,
                   community2collection c2c,
                   metadatavalue m,
                   ubuaux_ukbstats s,
                   collection2item c2i
                 WHERE";
        if ($bInclude == 1) {
             $sql .= " s.year <= '" . $nYear . "'";
        }
        else {
            $sql .= " s.year = '" . $nYear . "'";
        }
        $sql .= "AND s.id=c2i.item_id AND
                 c2i.collection_id <> s.cid AND
                 c2i.collection_id=c.collection_id AND 
                 c.collection_id=c2c.collection_id AND 
                 c2c.community_id=m.resource_id
				 AND m.resource_type_id=4 and m.metadata_field_id=143				 
				) t 
				 GROUP BY community,typecontent 
                 ORDER BY community,typecontent";
        
        //$aDebug['sql'] = $sql;
        try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
                $aStats[] = $row;
            }
            $aResult['stats'] = $aStats;
        }
        catch (Exception $e) {
            $aResult['error'] = 'could not find mapped community stats: ' . $e->getMessage();
        }
        
        return $aResult;
    }


    /**
     * Find all collections and their communities
     * 
     * @return array
     */
    private function findCollections()
    {
		$aResult = array();
		
		$sql = "SELECT * from metadatavalue where resource_type_id=3 and metadata_field_id=143";
		try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
                $aResult['collections'][] = $row;
            }
        }
        catch (Exception $e) {
            $aResult['error'] = 'could not find communities: ' . $e->getMessage();
        }
        
        return $aResult;
	
    }
    
    /**
     * Find all communities
     * 
     * @return array
     */
    private function findCommunities()
    {
		$aResult = array();
		
		$sql = "SELECT * from metadatavalue where resource_type_id=4 and metadata_field_id=143";
		
		try {
            $result = pg_query($this->dbconn, $sql);
            while ($row=pg_fetch_assoc($result)) {
                $aResult['communities'][] = $row;
            }
        }
        catch (Exception $e) {
            $aResult['error'] = 'could not find communities: ' . $e->getMessage();
        }
        
        return $aResult;

    }
    
    
}

