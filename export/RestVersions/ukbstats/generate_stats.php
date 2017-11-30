<?php

/*
 * This script runs every night, gets some data and outputs them in csv files
 */
require_once 'init.php';
require_once CLASSES . 'UkbStatistics.php';

$nFirstYear = 2005;
//$nMaxYears = 16;
$bVerbose = 0;
$bDoTableUpdate = 0;

$oUStats = new UkbStatistics();

//Get script params
$sOpts = "vu";
$aOptions = getopt($sOpts);

if (isset($aOptions['v'])) {
    $bVerbose = 1;
}
if (isset($aOptions['u'])) {
    $bDoTableUpdate = 1;
}

wlog('======= STARTING STATS ========');


$nThisYear = date("Y");
$aFieldIds = array();
$aCollections = array();
$aCommunities = array();

//get the statistics data
$aInitResults = $oUStats->initStatistics();

if (isset($aInitResults['errors'])) {
    foreach ($aInitResults['errors'] as $sError) {
        wlog($sError, 'INF');
    }
    $aFieldIds['date_issued'] = 27;
    $aFieldIds['type_content'] = 146;
}
else {
    foreach ($aInitResults['fields'] as $aField) {
        $sFieldName = $aField['element'] . '_' . $aField['qualifier'];
        $sValue = $aField['metadata_field_id'];
        $aFieldIds[$sFieldName] = $sValue;
    }
    $aCollections = $aInitResults['collections'];
    $aCommunities = $aInitResults['communities'];
}


//recreate the stats table if dotableupdate has been set
if ($bDoTableUpdate == 1) {
    $oUStats->updateStatsTable($aFieldIds);
}


$aCollectionIds = parseCollectionIds($aCollections);
$aCollectionStats = $oUStats->getCollectionStats($nFirstYear, $nThisYear);
$aParsedCollectionStats = parseCollectionStats($aCollectionStats, $aCollectionIds);

$sCollStatsText = makeCollectionStatsText($aParsedCollectionStats, $nFirstYear, $nThisYear);
$sCollWrite = writeStats($sCollStatsText, 'collection.csv');
//$sLine = 'written stats for collections ' . $sCollWrite;
//wlog($sLine, 'INF');


$aTypeContentStats = $oUStats->getTypeContentStats($nFirstYear, $nThisYear);
$aParsedTypeContentStats = parseTypeContentStats($aTypeContentStats);
$sTCStatsText = makeTypeContentStatsText($aParsedTypeContentStats, $nFirstYear, $nThisYear);
$sTCWrite = writeStats($sTCStatsText, 'typecontent.csv');
//$sLine = 'written stats for type content: ' . $sTCWrite;
//wlog($sLine, 'INF');

/**
* 1. for each year: get each community, and for each community get the number for each type_content
 *      the result looks like communityname, number of articles, number of chapters etc 
 *      for a limited number of type_contents (the rest are "other"
 * 2. for firstyear until 2 years ago (i.e. 2005 through 2010) get the totals of these numbers added up
 * 3. for firstyear until 1 years ago (i.e. 2005 through 2011) get the totals of these numbers added up
 * 4. for firstyear until this year (i.e. 2005 through 2012) get the totals of these numbers added up
 * For all of these cases: count "real" and mapped items separately
*/
$aTypePerCommunityStats = $oUStats->getTypeContentPerCommunity($nFirstYear, $nThisYear);
$aParsedTPCStats = parseTypePerCommunityStats($aTypePerCommunityStats, $nThisYear);
$sTPCStatsText = makeTypeCommunityStatsText($aParsedTPCStats);
$sTPCWrite = writeStats($sTPCStatsText, 'tcpercomm.csv');
//$sLine = 'written stats for type per community: ' . $sTPCWrite;
//wlog($sLine, 'INF');


//wlog('======= STATS DONE ========');

//=========================================================
/**
 * Parse the data about collections
 * 
 * @param array $aCollections
 * @return array
 */
function parseCollectionIds($aCollections) {
    $aCollectionIds = array();
    
    foreach ($aCollections as $aOneColl) {
        $sName = $aOneColl['text_value'];
        $nId = $aOneColl['resource_id'];
        $aCollectionIds[$nId] = $sName;
    }
    
    return $aCollectionIds;
}

/**
 * Parse the data we found for the stats per collection
 * 
 * @param array $aCollectionStats
 * @param array $aCollectionIds
 * @return array
 */
function parseCollectionStats($aCollectionStats, $aCollectionIds) 
{
    $aParsedStats = array();
	
	 foreach ($aCollectionStats as $nYear => $aOneYear) {
        $aStats = $aOneYear['stats'];
        foreach ($aStats as $aCollStats) {
            $sCommunityName = $aCollStats['community'];
            $nCollectionId = $aCollStats['cid'];
            $nTotalItems = $aCollStats['total'];
            $sCollectionName = $aCollectionIds[$nCollectionId];
            //echo 'collectionid ' . $nCollectionId . ' gives ' . $sCollectionName . "\n";
            //$aParsedStats[$sCommunityName][$nCollectionId][$nYear] = $nTotalItems;
            $aParsedStats[$sCommunityName][$sCollectionName][$nYear] = $nTotalItems;
        }
    }
	//print_r($aParsedStats);
    
   
    return $aParsedStats;
}

/**
 * Parse the data we found for the statistics per type.content
 * 
 * @param array $aTypeContentStats
 * @return array
 */
function parseTypeContentStats($aTypeContentStats)
{
    $aParsedStats = array();
    
    foreach ($aTypeContentStats as $nYear => $aOneYear) {
        $aStats = $aOneYear['stats'];
        foreach ($aStats as $aOneType) {
            $sTypeName = $aOneType['type_content'];
            $nTotal = $aOneType['total'];
            $aParsedStats[$sTypeName][$nYear] = $nTotal;
        }
    }
    
    return $aParsedStats;
}

/**
 * Parse the data we found for the stats per type per community
 * 
 * @param array $aTypePerCommunityStats
 * @param int $nThisYear
 * @return array
 */
function parseTypePerCommunityStats($aTypePerCommunityStats, $nThisYear) 
{
    $aParsedStats = array();
    
    $nLastYear = $nThisYear-1;
    $nYearBefore = $nThisYear-2;
    $nYearsAgo = $nThisYear-3;
    
    //the totals over all years
    $aUntilLastYear = $aTypePerCommunityStats[$nLastYear];
    $aUntilYearBefore = $aTypePerCommunityStats[$nYearBefore];
    $aUntilYearsAgo = $aTypePerCommunityStats[$nYearsAgo];
    
    //take an "until" year, get the stats (total and mapped) for each community, for each type content
    $aUntilLastYearParsed = parseStatGroup($aUntilLastYear);
    $aUntilYearBeforeParsed = parseStatGroup($aUntilYearBefore);
    $aUntilYearsAgoParsed = parseStatGroup($aUntilYearsAgo);
    
    $sLastYearName = 'tm' . $nLastYear;
    $sYearBeforeName = 'tm' . $nYearBefore;
    $sYearsAgoName = 'tm' . $nYearsAgo;
    $aParsedStats[$sLastYearName] = $aUntilLastYearParsed;
    $aParsedStats[$sYearBeforeName] = $aUntilYearBeforeParsed;
    $aParsedStats[$sYearsAgoName] = $aUntilYearsAgoParsed;
    
    //numbers per year
    $aPerYear = $aTypePerCommunityStats['peryear'];
    foreach ($aPerYear as $nYear => $aOneYearStats) {
        $aYearParsed = parseStatGroup($aOneYearStats);
        $aParsedStats[$nYear] = $aYearParsed;
    }
    
    return $aParsedStats;
    
}

/**
 * Parse a group (one or more years) within the stats we found for type per community
 * 
 * @param array $aStats
 * @return array
 */
function parseStatGroup($aStats) {
    $aParsed = array();
    
    $aTotalStats = $aStats['normal']['stats'];
    foreach ($aTotalStats as $aOneCommStats) {
        $sCommunityName = $aOneCommStats['community'];
        $nTypeContent = $aOneCommStats['typecontent'];
        $nTotal = $aOneCommStats['total'];
        $aParsed[$sCommunityName][$nTypeContent]['normal'] = $nTotal;
    }
    $aMappedStats = $aStats['mapped']['stats'];
    foreach ($aMappedStats as $aOneMapStats) {
        $sCommunityName = $aOneMapStats['community'];
        $nTypeContent = $aOneMapStats['typecontent'];
        $nTotal = $aOneMapStats['total'];
        $aParsed[$sCommunityName][$nTypeContent]['mapped'] = $nTotal;
    }
     
    return $aParsed;
}

/**
 * Turn the collection stats data into a string that can be written to a .csv file
  * @param type $aParsedCollectionStats
 * @param type $nFirstYear
 * @param type $nThisYear
 * @return string 
 */
function makeCollectionStatsText($aParsedCollectionStats, $nFirstYear, $nThisYear)
{
    $nGrandTotal = 0;
    $aYearTotals = array();
    for ($i = $nThisYear; $i >= $nFirstYear; $i--) {
        $aYearTotals[$i] = 0;
    }
    
    $sText = 'peildatum ' . date("d") . '-' . date("m") . '-' . date("Y") . "\n";
    wlog($sText, 'INF');
	
    $sText .= 'community;collection';
    for ($i = $nThisYear; $i >= $nFirstYear; $i--) {
        $sText .= ';' . $i;
    }
    $sText .= "\n";
    
    ksort($aParsedCollectionStats);
    foreach ($aParsedCollectionStats as $sCommunityName => $aCommunityStats) {
        $count = 0;
        
        ksort($aCommunityStats);
        foreach ($aCommunityStats as $sCollectionName => $aCollectionStats) {
            if ($count == 0) {
                $sText .= $sCommunityName . ';' . $sCollectionName;
		//wlog($sText, 'INF');
            }
            else {
                $sText .= ' ;' . $sCollectionName;
            }
            
            for ($i = $nThisYear; $i >= $nFirstYear; $i--) {
                if (isset($aCollectionStats[$i])) {
                    $sText .= ';' . $aCollectionStats[$i];
                    //wlog($sText, 'INF');
                    $aYearTotals[$i] += $aCollectionStats[$i];
                }
                else {
                    $sText .= ';0';
                }
            }
            $sText .= "\n";
            $count++;
        }
    }
    
    $sText .= 'totaal; ;';
    foreach ($aYearTotals as $nTotal) {
        $sText .= $nTotal . ';';
        $nGrandTotal += $nTotal;
    }
    $sText .= $nGrandTotal . "\n";
    //wlog($sText, 'INF');
    
    return $sText;
}

/**
 *Turn the type content stats data into a string that can be written to a .csv file
 * @param type $aParsedTypeContentStats
 * @param type $nFirstYear
 * @param type $nThisYear
 * @return string 
 */
function makeTypeContentStatsText($aParsedTypeContentStats, $nFirstYear, $nThisYear)
{
    $nGrandTotal = 0;
    $aYearTotals = array();
    for ($i = $nThisYear; $i >= $nFirstYear; $i--) {
        $aYearTotals[$i] = 0;
    }
    
    $sText = 'peildatum ' . date("d") . '-' . date("m") . '-' . date("Y") . "\n";
    $sText .= 'type content';
    for ($i = $nThisYear; $i >= $nFirstYear; $i--) {
        $sText .= ';' . $i;
    }
    $sText .= "\n";
    
    ksort($aParsedTypeContentStats);
    foreach ($aParsedTypeContentStats as $sTypeContent=>$aNumbers) {
        $sText .= $sTypeContent;
        for ($i = $nThisYear; $i >= $nFirstYear; $i--) {
            if (isset($aNumbers[$i])) {
                $sText .= ';' . $aNumbers[$i];
                $aYearTotals[$i] += $aNumbers[$i];
            }
            else {
                $sText .= ';0';
            }
        }
        
        $sText .= "\n";
    }
    
    $sText .= 'totaal;';
    foreach ($aYearTotals as $nTotal) {
        $sText .= $nTotal . ';';
        $nGrandTotal += $nTotal;
    }
    $sText .= $nGrandTotal . "\n";
    
    return $sText;
}

/**
 * Turn the stats data for type per community into a string that can be written to a .csv file
 * @param type $aParsedTPCStats
 * @return string 
 */
function makeTypeCommunityStatsText($aParsedTPCStats)
{
    $sTPCStatsText = 'Peildatum: ' . date("d") . '-' . date("m") . '-' . date("Y");
    $sTPCStatsText .= 'Cijfers zijn exclusief de gemapte items. De aantallen gemapte items staat tussen haakjes' . "\n";
    
    foreach ($aParsedTPCStats as $sYearKey => $aOneYearGroup) {
        $aYearTotals = array();
        for ($i=0; $i<4; $i++) {
            $aYearTotals[$i] = 0;
        }
        ksort($aOneYearGroup);
        
        //$sTPCStatsText .= $sYearKey . ';artikelen;hoofdstukken;boeken;dissertaties;rapporten;overig;totaal' . "\n";
        //only 4 types per 18-03-2014
        $sTPCStatsText .= $sYearKey . ';artikelen;hoofdstukken/congresbijdragen;dissertaties;overig;totaal' . "\n";
        foreach ($aOneYearGroup as $sCommunityName => $aNumbers) {
            $sTPCStatsText .= $sCommunityName;
            $nTotalNormalItems = 0;
            $nTotalMappedItems = 0;
            
            for ($i=0; $i<4; $i++) {
                $nNormal = 0;
                $nMapped = 0;
                
                if (isset($aNumbers[$i])) {
                   if (isset($aNumbers[$i]['normal'])) {
                        $nNormal = $aNumbers[$i]['normal'];
                        $nTotalNormalItems += $nNormal;
                    }
                    if (isset($aNumbers[$i]['mapped'])) {
                        $nMapped = $aNumbers[$i]['mapped'];
                        $nTotalMappedItems += $nMapped;
                    }
                    $sTPCStatsText .= ';' . $nNormal;
                    if ($nMapped != 0) {
                        $sTPCStatsText .= ' (' . $nMapped . ')';
                    }
                }
                else {
                    $sTPCStatsText .= ';0';
                }
                $aYearTotals[$i] += $nNormal;
            }
            $sTPCStatsText .= ';' . $nTotalNormalItems;
            if ($nTotalMappedItems > 0) {
                $sTPCStatsText .= ' (' . $nTotalMappedItems . ')';
            }
            $sTPCStatsText .= "\n"; //end of one community
        }
        $nGrandTotal = 0;
        
        $sTPCStatsText .= 'totaal;';
        foreach ($aYearTotals as $nOneTotal) {
            $sTPCStatsText .= $nOneTotal . ';';
            $nGrandTotal += $nOneTotal;
        }
        $sTPCStatsText .= $nGrandTotal . "\n";
 
        
        $sTPCStatsText .= "\n"; //extra empty line after each year group
    }
    
    return $sTPCStatsText;
}

function writeStats($sStatsText, $sFileName)
{
    $sExportDir = EXPORTPATH;
    $sFile = $sExportDir . $sFileName;
	
	//$sDebugLine = 'text is ' . $sStatsText;
	//wlog($sDebugLine, 'INF');
    
    $fh = fopen($sFile, "w");
    fwrite($fh, $sStatsText);
    fclose($fh);
    
    return 'done';
}
?>
