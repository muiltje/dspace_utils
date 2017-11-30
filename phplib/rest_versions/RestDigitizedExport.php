<?php
require_once 'RestItem.php';
require_once 'RestItemMetadata.php';


/**
 * Functions for the export of digitized items
 *
 * @author muilw101
 */
class RestDigitizedExport {
    private $oItem;
    private $oItemMetadata;
    private $aMetadataFields = array();

    /**
     * Construct and get metadata fields
     *
     * @param array $aMetadataFields
     */
    function __construct($aMetadataFields = NULL) {
        $this->oItem = new Item();
        $this->oItemMetadata = new ItemMetadata();
        if ($aMetadataFields != NULL) {
            $this->aMetadataFields = $aMetadataFields;
        }
    }

    /**
     * Get recent digitized items, update their fulltext link and get their handles
     *
     * @param string $sReaderUrl
     * @return array
     */
    public function processRecentItems($sReaderUrl)
    {
        $aDebug = array();
        $sHandleString = '';

        $aRecentItems = $this->getRecentItems();

        if (isset($aRecentItems['error'])) {
            $aDebug['error'] = $aRecentItems['error'];
            return $aDebug;
        }
        else {
            foreach ($aRecentItems as $aItem) {
                $aDebug['items'][] = $aItem;

                $aUpdateResult = $this->updateFulltextLink($aItem, $sReaderUrl);
                //$aDebug['sql'] = $aUpdateResult['sql'];
                if (isset($aUpdateResult['error'])) {
                    $aDebug['error'] = $aUpdateResult['error'];
                    return $aDebug;
                }
                else {
                    $sHandle = $aUpdateResult['handle'];
                    $sHandleString .= $sHandle . ',';
                }
            }
        }
        $sFixedHandleString = preg_replace('/,$/', '', $sHandleString);
        $aDebug['handles'] = $sFixedHandleString;

        return $aDebug;
    }

    /**
     * Export data of new and modified digitized items to the resolver
     *
     * @param string $sResolverExportPath
     * @param string $sResolverUrl
     * @return array
     */
    public function exportResolverData($sResolverExportPath, $sResolverUrl, $sMode, $nMaxItems)
    {
        $aDebug = array();

        $aExportItems = $this->getExportItems($nMaxItems);
        if (isset($aExportItems['error'])) {
            $aDebug = $aExportItems['error'];
            return $aDebug;
        }
        else {
            $aWrite = $this->writeExportData($aExportItems, $sResolverExportPath, $sMode);
            if (isset($aWrite['error'])) {
                $aDebug['error'] =  $aWrite['error'];
                return $aDebug;
            }
            else {
                $sExportFile = $aWrite['exportfile'];

                $aExportResult = $this->sendExportData($sExportFile, $sResolverUrl);
                if (isset($aExportResult['error'])) {
                    $aDebug['error'] = $aExportResult['error'];
                    return $aDebug;
                }
                else {
                    $aDebug['exportresult'] = $aExportResult;
                    $aRebuildResult = $this->sendRebuildCache($sResolverUrl);
                    if (isset($aRebuildResult['error'])) {
                        $aDebug['error'] = $aRebuildResult['error'];
                        return $aDebug;
                    }
                    else {
                        $aDebug['success'] = 'success';
                    }
                }


                $aDebug['file'] = $sExportFile;
            }
        }

        return $aDebug;
    }


    /**
     * Generate derivatives for digitized objects
     *
     * @param string $sManifestationStore
     * @param int $bIsFullDump
     * @param string $sHandles
     * @return array
     */
    public function generateDerivatives($sManifestationStore, $bIsFullDump, $sHandles)
    {
       //http://manifestation-store.library.uu.nl/newitem/1874-287839
        //http://dspace-admin.library.uu.nl/admin/dsadmin/manifstore/getiteminfo.php?handle=1874-287839

        if ($bIsFullDump == 0) {
            if ($sHandles != '') {
                $aHandles = explode(',', $sHandles);
                foreach ($aHandles as $sOneHandle) {
                    $sNewItemUrl = $sManifestationStore . '/newitem/' . $sOneHandle;
                    $aDebug['new'][] = $this->launchBuild($sNewItemUrl);
                    $sBuildUrl = $sManifestationStore . '/build?handle=' . $sOneHandle;
                    $aDebug['build'][] = $this->launchBuild($sBuildUrl);
                }
            }
            else {
                $aDebug['error'] = 'no full dump, but no handles';
            }
        }
        else {
            $sBuildUrl = $sManifestationStore . '/build';
            $aDebug['genresult'] = $this->launchBuild($sBuildUrl);
        }

        return $aDebug;
    }

    /**
     * Send htm data to the manifestation store
     *
     * @param string $sHtmFile
     * @param string $sBookId
     * @param string $sManifestationUrl
     * @return array
     */
    public function sendHtm($sHtmFile, $sBookId, $sManifestationUrl)
    {
        $aSendResult = array();

        $aTempResult = $this->makeOcrData($sHtmFile, $sBookId);
        if (isset($aTempResult['error'])){
            $aSendResult['error'] = $aTempResult['error'];
            return $aSendResult;
        }
        else {
            $sTempFile = $aTempResult['tempfile'];
            $aPostResult = $this->sendOcrData($sTempFile, $sManifestationUrl);
            if(isset($aPostResult['error'])) {
                $aSendResult['error'] = $aPostResult['error'];
            }
            else {
                $aSendResult['success'] = 'success';
                $aSendResult['postres'] = $aPostResult;
            }
        }



        return $aSendResult;
    }


    /**
     * Get items for the manifestation store that have recently been added or modified.
     *
     * @return array
     */
    private function getRecentItems()
    {
        $aDebug = array();

        $aRecentItems = $this->oItem->getManifestationItems();

        if (isset($aRecentItems['error'])) {
            $aDebug['error'] = $aRecentItems['error'];
            return $aDebug;
        }

        return $aRecentItems;
    }

    /**
     * Update the full text link for an item
     *
     * @param array $aItem
     * @param string $sReaderUrl
     * @return array
     */
    private function updateFulltextLink($aItem, $sReaderUrl)
    {
        $aResults = array();

        $sReaderLink = '';
        if (isset($aItem['text_value']) && $aItem['text_value'] != '') {
            $sReaderLink = $aItem['text_value'];
        }

        $sHandle = $aItem['handle'];
        $sFixedHandle = preg_replace('/\//', '-', $sHandle);
        $aResults['handle'] = $sFixedHandle;

        $nItemId = $aItem['item_id'];

        $sNewReaderLink = $sReaderUrl . $sFixedHandle;

        if ($sReaderLink == '') {
            //$aAdd = $this->oItemMetadata->addMetadataValue($nItemId, $nMetadataFieldId, $sNewReaderLink);
			$aAdd = $this->oItemMetadata->addMetadataValueRest($nItemId, 'dc.identifier.urlfulltext', $sNewReaderLink, 'en');
            //$aResults['sql'] = $aAdd['sql'];
            if (isset($aAdd['error'])) {
                $aResults['error'] = $aAdd['error'];
             }
        }
        else {
            //$aUpdate = $this->oItemMetadata->updateMetadataValue($nItemId, $nMetadataFieldId, $sNewReaderLink);
			$aUpdate = $this->oItemMetadata->updateMetadataValueRest($nItemId, 'dc.identifier.urlfulltext', $sNewReaderLink, 'en');
            if (isset($aUpdate['error'])) {
                $aResults['error'] = $aUpdate['error'];
            }
        }

        return $aResults;
    }


    /**
     * Get items that should be sent to the resolver
     *
     * @return array
     */
    private function getExportItems($nMaxItems)
    {
        $aDebug = array();

        $aExportData = $this->oItem->getObjectReaderExportItems($nMaxItems);
        if (isset($aExportData['error'])) {
            $aDebug['error'] = $aExportData['error'];
            return $aDebug;
        }

        return $aExportData;
    }

    /**
     * Write the data for the resolver export to a file
     *
     * @param array $aExportData
     * @param string $sResolverExportPath
     * @return array
     */
    private function writeExportData($aExportData, $sResolverExportPath, $sMode)
    {
        $aPossibleRights = array(
        'Closed Access' => 0,
        'Open Access (free)' => 1,
        'Restricted Access' => 2,
        ''
      );

        $aDebug = array();

        $nLastItemId = 0;

        //csv file: RESOLVER_EXPORT_PATH . ymd . csv
        $sExportFile = $sResolverExportPath . date('Ymd') . '_bc.csv';

        try {
            $fh = fopen($sExportFile, 'w');
            fwrite($fh, 'provider=bc&data=');

            foreach ($aExportData as $aItem) {
                $nItemId = $aItem['item_id'];
                $sFoundHandle = $aItem['handle'];
                $sHandle = preg_replace('/\//', '-', $sFoundHandle);
                $sWithdrawn = $aItem['withdrawn'];

                if ($sWithdrawn == 'f') {
                    $sAleph = $aItem['aleph'];
                    $sElement = $aItem['element'];
                    $sQualifier = $aItem['qualifier'];
                    $sDateIssued = $aItem['dateissued'];
                    $sTitle = $aItem['title'];
                    $sVolume = $aItem['volume'];
                    $sStartPage = $aItem['startpage'];
                    $sAccessRights = $aItem['accessrights'];
                    $sDigitizationId = $aItem['digi_id'];
                    $nAccessRightsCode = 0;

                    $sSourceIdentifier = $sElement;
                    if ($sQualifier != '') {
                        $sSourceIdentifier .= '_' . $sQualifier;
                    }

                    $sShowTitle = $this->makeSafeString($sTitle);

                    if ($sVolume != '') {
                        $sShowTitle .= ', Volume: ' . $sVolume;
                    }
                    if ($sStartPage != '') {
                        $sShowTitle .= ', Page: ' . $sStartPage;
                    }

                    if (array_key_exists($sAccessRights, $aPossibleRights)) {
                        $nAccessRightsCode = $aPossibleRights[$sAccessRights];
                    }

                    $sCsvText = '';
                    //now write to the csv file
                    if ($nItemId != $nLastItemId) {
                        $sCsvText .= '"' . $sHandle .'","' . $nItemId . '","id","","",' . $nAccessRightsCode . "\n";
                    }
                    $sCsvText .= '"' . $sHandle . '","' . $sAleph . '","' . $sSourceIdentifier . '"';
                    $sCsvText .= ',"' . $sShowTitle . '","' . $sDateIssued . '",' . $nAccessRightsCode . "\n";

                    $sCsvText .= '"' . $sHandle . '","' . $sDigitizationId . '","identifier_digitization"';
                    $sCsvText .= ',"' . $sShowTitle . '","' . $sDateIssued . '",' . $nAccessRightsCode . "\n";

                    fwrite($fh, $sCsvText);
                }
                else { //if the item is withdrawn
                    if ($nItemId != $nLastItemId) {
                        //remove item from manifestation store
                        //NB: TEST VERY CAREFULLY
                        //since the test-manifestationstore-site is connected to the production
                        //manifestationstore, testing this would effectively remove an item
                        //from the production reader site
                        if ($sMode == 'doremove') {
                            //$remove = $this->doManifestation($sHandle, 'remove');
                        }
                    }
                }
                 //echo 'last_item_id is ' . $nLastItemId . ' and item is ' . $nItemId . "\n";
                $nLastItemId = $nItemId;
            }//end of for each

            fclose($fh);

            $aDebug['success'] = 'success';
            $aDebug['exportfile'] = $sExportFile;
        }//end of try
        catch (Exception $e) {
            $aDebug['error'] = 'could not write to export file: ' . $e->getTraceAsString();
        }

        return $aDebug;
    }

    /**
     * Send the data to the resolver with curl
     *
     * @param string $sExportFile
     * @param string $sResolverUrl
     * @return array
     */
    private function sendExportData($sExportFile, $sResolverUrl)
    {
        $aExportResult = array();

        $fh = fopen($sExportFile, "r");
        $data = fread($fh, filesize($sExportFile));

        $ch = curl_init();
        if (curl_errno($ch)<>0) {
            $aExportResult['error'] = curl_errno($ch);
            $aExportResult['response'] = '';
        }
        else {
            try {
                curl_setopt($ch, CURLOPT_URL, $sResolverUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           	curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

           	$aExportResult['response']=curl_exec($ch);
            }
            catch (Exception $e)
            {
                $aExportResult['error'] =  $e->getTraceAsString();
            }
        }

        return $aExportResult;
    }

    /**
     * Send a message to the resolver to rebuild its cache
     *
     * @param string $sResolverUrl
     * @return array
     */
    private function sendRebuildCache($sResolverUrl)
    {
        $aRebuildResult = array();

        $data = 'provider=bc&rebuild_cache=1';
        $ch = curl_init();
        if (curl_errno($ch)<>0) {
            $aRebuildResult['error'] = curl_errno($ch);
            $aRebuildResult['response'] = '';
        }
        else {
            try {
                curl_setopt($ch, CURLOPT_URL, $sResolverUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           	curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

           	$aRebuildResult['response']=curl_exec($ch);
             }
            catch (Exception $e)
            {
                $aRebuildResult['error'] =  $e->getTraceAsString();
            }
        }

        return $aRebuildResult;
    }

    /**
     * Have the manifestation store builder build all items
     * or a set of items, as determined by the buildurl
     *
     * @param string $sBuildUrl
     * @return array
     */
    private function launchBuild($sBuildUrl)
    {
        $aDebug = array();
        $sLogFile = "/opt/local/cachelogs/ubu_log/dsutils/ManifestationPipeLog.txt";

        $sCommand = "/usr/bin/curl " . $sBuildUrl;

        $fh = fopen($sLogFile, "a");

        try {
            $ph = popen($sCommand, "r");
            while(!feof($ph)) {
                $read = fread($ph, 2096);
                fwrite($fh, $read);
            }
            pclose($ph);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not open pipe: ' . $e->getTraceAsString();
        }

        fclose($fh);

        return $aDebug;
    }

    /**
     * Write out the contents of the given htm file
     * in a way that can be used by the manifestation store
     *
     * @param string $sHtmFile
     * @param string $sBookId
     * @return array
     */
    private function makeOcrData($sHtmFile, $sBookId)
    {
        $aDebug = array();

        $sTempFile = "/var/tmp/ocrtemp";
        $aDebug['tempfile'] = $sTempFile;

        try {
            $fh = fopen($sTempFile, "w");

            //htmfile has the form [sipdirectory]/[booknumber]/[4 digit pagename].htm
            $sPageName = substr($sHtmFile, -8, 4);
            $nPageNumber = (int) $sPageName;
            $sStartLine = 'bookid=' . $sBookId . '&page=' . $nPageNumber  . '&file=';
            fwrite($fh, $sStartLine);

            $sFoundOcrData = file_get_contents($sHtmFile);

            $sOcrData = preg_replace('/&/', '', $sFoundOcrData);

            fwrite($fh, $sOcrData);
            fclose($fh);

            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not write tempfile for ocr data: ' . $e->getTraceAsString();
        }

        return $aDebug;
    }

    /**
     * Send the data of an ocred htm file to the manifestation store
     *
     * @param string $sTempFile
     * @param string $sManifestationStore
     * @return array
     */
    private function sendOcrData($sTempFile, $sManifestationStore)
    {
        $aPostResult = array();

        $sOcrUrl = $sManifestationStore . '/storeocr';

        $fh = fopen($sTempFile, "r");
        //$data = fread($fh, filesize($sTempFile));
        $dataread = fread($fh, filesize($sTempFile));
	$data = '';
	if (get_magic_quotes_gpc()) {
	     $data = stripslashes($dataread);
	}
	else {
	    $data = $dataread;
        }

        $ch = curl_init();
        if (curl_errno($ch)<>0) {
           $aPostResult['error'] = curl_error($ch);
           $aPostResult['response'] = '';
       	}
       	else {
           try {
                curl_setopt($ch, CURLOPT_URL, $sOcrUrl);
           	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           	curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

            	$response=curl_exec($ch);
           	$rinfo=curl_getinfo($ch);
		$aPostResult['response'] = $response;
                $aPostResult['rinfo'] = $rinfo;
           }
           catch (Exception $e) {
               $aPostResult['error'] = 'could not post ocrdata: ' . $e->getTraceAsString();
           }

        }
        fclose($fh);
        unlink($sTempFile);

        return $aPostResult;
    }

        /**
     * Fix utf-8 encoding and escape ampersand and angle brackets
     * @param type $sValue
     * @return type
     */
    private function makeSafeString($sValue)
    {
        $oEncoding = new FixEncoding();
        $sProcessedString = $oEncoding->toUTF8($sValue);

        //only escape & if not followed by #x
        $sAmpValue = preg_replace('/&/', '%%26', $sProcessedString);
        $sLessValue = preg_replace('/</', '%%26lt;', $sAmpValue);
        $sMoreValue = preg_replace('/>/', '%%26gt;', $sLessValue);
        $sOneLineValue = preg_replace('/\n/', ' ', $sMoreValue);
        $sDoubleQuotedValue = preg_replace('/"/', '\"', $sOneLineValue);
        //$sEscQuotedValue = preg_replace('/\'/', '\\\'', $sDoubleQuotedValue);

        return $sDoubleQuotedValue;
        //return $sEscQuotedValue;

    }

}


