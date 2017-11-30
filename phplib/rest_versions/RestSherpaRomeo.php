<?php
/**
 * This class deals with SherpaRomeo things
 * 
 * It gets data through the SherpaRomeo API,
 * parses that data and returns the results of that parsing
 * 
 * SR element that we need are
 * publisher name
 * preprints_prearchiving
 * preprints_prerestrictions
 * postprints_postarchiving
 * postprints_postrestrictions
 * condition
 * paidaccessname (if any)
 * paidaccessurl (if any)
 * copyrightlinks (if any)
 */


class RestSherpaRomeo {
    
   
    private $sSherpaBaseUrl = '';
    private $aArchivingStates = array('can', 'cannot', 'restricted','unclear', 'unknown');
    
    function __construct() {
        $this->sSherpaBaseUrl = 'http://www.sherpa.ac.uk/romeo/api29.php';
    }
    
    public function checkSherpaRomeo($sISSN)
    {
        $aData = array();

        //get SR record
        $sSherpaResponse = $this->getSherpaRomeoRecord($sISSN);
        //$aData['sherparesponse'] = $sSherpaResponse;
        
        //parse SR record
        $aPublisherData = $this->parseSherpaRomeoResult($sSherpaResponse);
        $aData['publisherdata'] = $aPublisherData;
        
        //determine output string
        //we may need to do some stripping, with strings like <num>12</num> <period units="month">months</period> embargo
        $aData['outputtext'] = $this->makeOutPutText($aPublisherData);
        
        return $aData;
    }
    
    
    private function getSherpaRomeoRecord($sIssn)
    {
        $sSRUrl = $this->sSherpaBaseUrl . '?qtype=exact&issn=' . $sIssn;
        
        $sSRResponse = file_get_contents($sSRUrl);
        
        return $sSRResponse;
    }
    
    private function parseSherpaRomeoResult($sResultString)
    {
        $aParsedResults = array();
        
        $sXML = new SimpleXMLElement($sResultString);
        
        //$oPublisherData = $sXML->xpath("//publisher");
        //$aParsedResults['object'] = $oPublisherData;
        
        $aNameField = $sXML->xpath("//publisher/name");
        $aParsedResults['publishername'] = (string) $aNameField[0];
        $aUrlField = $sXML->xpath("//publisher/homeurl");
        $sPublisherUrl = '';
        if (!empty($aUrlField)) {
            $sPublisherUrl = (string) $aUrlField[0];
        }
        $aParsedResults['publisherurl'] = $sPublisherUrl;
        
        $aPreArchivingFields = $sXML->xpath("//publisher/preprints/prearchiving");
        $aParsedResults['prearchiving'] = (string) $aPreArchivingFields[0];
        
        $aPreRestrictions = $sXML->xpath("//publisher/preprints/prerestrictions/prerestriction");
        $aParsedResults['prerestrictions'] = array();
        if (!empty($aPreRestrictions)) {
            //$aParsedResults['prerestrictions'] = $aPreRestrictions;
            foreach ($aPreRestrictions as $oOnePreRes) {
                $aParsedResults['prerestrictions'][] = (string) $oOnePreRes;
            }
        }
        
        $aPostArchivingFields = $sXML->xpath("//publisher/postprints/postarchiving");
        $aParsedResults['postarchiving'] = (string) $aPostArchivingFields[0];
        
        $aPostRestrictions = $sXML->xpath("//publisher/postprints/postrestrictions/postrestriction");
        $aParsedResults['postrestrictions'] = array();
        if (!empty($aPostRestrictions)) {
            //$aParsedResults['postrestrictions'] = $aPostRestrictions;
            foreach ($aPostRestrictions as $oOnePostRes) {
                $aParsedResults['postrestrictions'][] = (string) $oOnePostRes;
            }
        }
		
		$aPdfArchiving = $sXML->xpath("//publisher/pdfversion/pdfarchiving");
		$aParsedResults['pdfarchiving'] = (string) $aPdfArchiving[0];
		$aPdfRestrictions = $sXML->xpath("//publisher/pdfversion/pdfrestrictions");
		if (!empty($aPdfRestrictions)) {
			foreach ($aPdfRestrictions as $aOnePdfRes) {
				$aParsedResults['pdfrestrictions'][] = (string) $aOnePdfRes;
			}
		}
		
        
        $aConditions = $sXML->xpath("//publisher/conditions/condition");
        $aParsedResults['conditions'] = array();
        if (!empty($aConditions)) {
            foreach ($aConditions as $oOneCondition) {
                $aParsedResults['conditions'] = (string) $oOneCondition;
            }
        }
        
        $aPaidAccess = array();
        $aPaidAccessURLField = $sXML->xpath("//publisher/paidaccess/paidaccessurl");
        if (!empty($aPaidAccessURLField)) {
            $aPaidAccess['url'] = (string) $aPaidAccessURLField[0];
        }
        $aPaidAccessNameField = $sXML->xpath("//publisher/paidaccess/paidaccessname");
        if (!empty($aPaidAccessNameField)) {
            $aPaidAccess['name'] = (string) $aPaidAccessNameField[0];
        }
        $aPaidAccessNotesField = $sXML->xpath("//publisher/paidaccess/paidaccessnotes");
        if(!empty($aPaidAccessNotesField)) {
            $aPaidAccess['notes'] = (string) $aPaidAccessNotesField[0];
        }        
        $aParsedResults['paidaccess'] = $aPaidAccess;
        
        $aCopyrightLinks = $sXML->xpath("//publisher/copyrightlinks/copyrightlink");
        $aParsedResults['copyrightlinks'] = array();
        if (!empty($aCopyrightLinks)) {
            foreach ($aCopyrightLinks as $oOneLink) {
                $aParsedResults['copyrightlinks'][] = (array) $oOneLink;;
            }
        }
        
        //and to make it fun, we could add
        $aParsedResults['romeocolour'] = '';
        $aRomeoColourField = $sXML->xpath("//publisher/romeocolour");
        if (count($aRomeoColourField) > 0) {
            $aParsedResults['romeocolour'] = (string) $aRomeoColourField[0];
        }
        
        return $aParsedResults;
    }
    
    private function makeOutPutText($aRomeoData)
    {
        $sOutputText = '';
        $sPublisherName = $aRomeoData['publishername'];
        if ($aRomeoData['publisherurl'] != '') {
            $sOutputText .= '<a href="' . $aRomeoData['publisherurl'] . '">';
            $sOutputText .= $sPublisherName;
            $sOutputText .= '</a>';
            $sOutputText .= '<br>';
        }
        else {
            $sOutputText .= $sPublisherName . '<br>';
        }
        $sOutputText .= "\n";
        
        $sPreArchiving = $this->parseStatus($aRomeoData['prearchiving']);
        $sOutputText .= 'Preprints: ' . $sPreArchiving . '<br>';
        $sOutputText .= "\n";
        $aPreRestrictions = $aRomeoData['prerestrictions'];
        $sOutputText .= 'Restrictions: ';
        if (!empty($aPreRestrictions)) {
            foreach ($aPreRestrictions as $sOnePreRes) {
                $sOutputText .= $sOnePreRes . '<br>';
            }
        }
        else {
            $sOutputText .= 'unclear <br>';
        }
        $sOutputText .= "\n";
        
        $sPostArchiving = $this->parseStatus($aRomeoData['postarchiving']);
        $sOutputText .= 'Postprints: ' . $sPostArchiving . '<br>';
        $sOutputText .= "\n";
        $aPostRestrictions = $aRomeoData['postrestrictions'];
        $sOutputText .= 'Restrictions: ';
        if (!empty($aPostRestrictions)) {
            foreach ($aPostRestrictions as $sOnePostRes) {
                $sOutputText .= $sOnePostRes . '<br>';
            }
        }
        else {
            $sOutputText .= 'unclear <br>';
        }
        $sOutputText .= "\n";
        
        $sOutputText .= 'Conditions: ' . $aRomeoData['conditions'] . '<br>';
        $sOutputText .= "\n";
        
        $aPaidAccess = $aRomeoData['paidaccess'];
        if (!empty($aPaidAccess)) {
            $sOutputText .= 'Paid access: <br>';
            $sOutputText .= '<ul>';
            if (isset($aPaidAccess['url'])) {
                $sOutputText .= '<a href="' . $aPaidAccess['url'] . '">';
                $sOutputText .= $aPaidAccess['name'];
                $sOutputText .= '</a>';
            }
            else {
                $sOutputText .= $aPaidAccess['name'] . '';
            }
            $sOutputText .= '</ul>';
            $sOutputText .= "\n";
        }
        
        $aCopyrightLinks = $aRomeoData['copyrightlinks'];
        if (!empty($aCopyrightLinks)) {
            $sOutputText .= 'Copyright: ';
            $sOutputText .= '<ul>';
            foreach ($aCopyrightLinks as $aCRLink) {
                $sOutputText .= '<li>';
                $sOutputText .= '<a href="' . $aCRLink['copyrightlinkurl'] . '">';
                $sOutputText .= $aCRLink['copyrightlinktext'];
                $sOutputText .= '</a></li>';
            }
            $sOutputText .= '</ul>';
            $sOutputText .= "\n";
        }
        
        return $sOutputText;
    }
    
    private function parseStatus($sArchiving)
    {
        $sStatus = '';
        if (in_array($sArchiving, $this->aArchivingStates)) {
            $sStatus = $sArchiving;
        }
        
        return $sStatus;
    }
}

