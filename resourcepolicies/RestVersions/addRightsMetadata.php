<?php
require_once 'init.php';

require_once (CLASSES . 'Item.php');
require_once (CLASSES . 'ItemMetadata.php');

wlog('==== Add rights metadata start ====', 'INF');

/**
 * Find items that were modified in the last day
 * Check if they have a date.embargo that is later than today
 * If so, and it's set for 2050, set accessrights to Closed
 * If so, but between now and 2050, set accessrights to embargoed
 */
$oItem = new Item();
$oItemMetadata = new ItemMetadata();

$sLastModifiedDate = date('Y-m-d', strtotime("-2 day"));

$aModifiedItems = $oItem->getModifiedItems($sLastModifiedDate);

foreach ($aModifiedItems as $aOneItem) {
    if ($aOneItem['in_archive'] == 't' && $aOneItem['withdrawn'] == 'f') {
        $nItemId = $aOneItem['item_id'];
        
        //is there a rights.accessrights field
        $aAccessRights = $oItemMetadata->getMetadataValue($nItemId, 161);
		  
        if (isset($aAccessRights['error'])) {
            wlog($aAccessRights['error'], 'INF');
        }
        else {
            $sAccessRights = '';
            if (isset($aAccessRights['values'])) {
                $sAccessRights = $aAccessRights['values'][0];
            }
            if ($sAccessRights == '') {
                //if it doesn't exist, add it; this rarely happens, but just to make sure
                //set it to Open Access; 
                //if that's not correct, it will be overwritten in the next step
                //$oItemMetadata->addMetadataValue($nItemId, 161, 'Open Access (free)');
				$aAddResponse = $oItemMetadata->addMetadataValueRest($nItemId, 'dc.rights.accessrights', 'Open Access (free)', 'en');
				if ($aAddResponse['response'] != '') {
					$sMessage = 'Something went wrong making ' . $nItemId . ' Open Access.';
					wlog($sMessage, 'ERROR');
				}
            }
        
            //if the access rights have been set to restricted or closed, do nothing
            // (these values are added by a separate process and we must trust they are correct)
            if (preg_match('/Closed/', $sAccessRights)) {
                $sLine = $nItemId . ' has Closed Access';
                wlog($sLine, 'INF');
            }
            elseif (preg_match('/Restricted/', $sAccessRights)) {
                $sLine = $nItemId . ' has Restricted Access';
                wlog($sLine, 'INF');
            }
            else {
                $aDateEmbargo = $oItemMetadata->getMetadataValue($nItemId, 193);
                
                if (isset($aDateEmbargo['error'])) {
                    wlog($aDateEmbargo['error'], 'INF');
                }
                else {
                    if (isset($aDateEmbargo['values']) && count($aDateEmbargo['values']) > 0) {
                        $sDateEmbargo = $aDateEmbargo['values'][0];
                        
                        if ($sDateEmbargo != '') {
                             //is it set for 2050
                            if (preg_match('/^2050/', $sDateEmbargo)) {
                                //set accessrights to closed access
                                //echo "setting no open \n";
                                //$oItemMetadata->updateMetadataValue($nItemId, 161, 'Closed Access');
								$aUpdateResults = $oItemMetadata->updateMetadataValueRest($nItemId, 'dc.rights.accessrights', 'Closed Access', 'en');
								if ($aUpdateResults['response'] != '') {
									$sMessage = 'Something went wrong making ' . $nItemId . ' Closed Access';
									wlog($sMessage, 'ERROR');
								}
								else {
	                                $sLine = 'setting ' . $nItemId . ' to closed';
									wlog($sLine, 'INF');
									$sToday = date('Y-m-d H:i:s');
									$oItem->updateLastModified($nItemId, $sToday);
								}
                            }
                            else {
                                $sFixedDateEmbargo = '';
                                if (preg_match('/^20\d\d-\d\d-\d\d$/', $sDateEmbargo)) { 
                                    $sFixedDateEmbargo = $sDateEmbargo;
                                }
                                elseif (preg_match('/^20\d\d-\d\d$/', $sDateEmbargo)) {
                                    $sFixedDateEmbargo = $sDateEmbargo . '-01';
                                }
                                elseif (preg_match('/^20\d\d$/', $sDateEmbargo)) {
                                    $sFixedDateEmbargo = $sDateEmbargo . '-01-01';
                                }
                                else {
                                    $sFixedDateEmbargo = '666'; //set it to an invalid format
                                }
                                
                                $bValid = checkEmbargoDate($sFixedDateEmbargo);
                                if (!$bValid) {
                                    //set accessrights to No Open Access and send mail to DV
                                    $line = 'Invalid embargo data for item ' . $nItemId . '; setting it to closed';
                                    wlog($line, 'INF');
                                    $aUpdateResults = $oItemMetadata->updateMetadataValueRest($nItemId, 'dc.rights.accessrights', 'Closed Access', 'en');
                                    if ($aUpdateResults['response'] != '') {
										$sMessage = 'Something went wrong making ' . $nItemId . ' Closed Access.';
										wlog($sMessage, 'ERROR');
									}
									else {
										$sLine = 'setting ' . $nItemId . ' to closed';
										wlog($sLine. 'INF');
										$sToday = date('Y-m-d H:i:s');
										$oItem->updateLastModified($nItemId, $sToday);
									}
                                }
                                else {
                                    if (strtotime($sFixedDateEmbargo) > strtotime("now")) {
                                        //set accessrights
                                        //echo "setting embargoed \n";
                                        $aUpdateResults = $oItemMetadata->updateMetadataValueRest($nItemId, 'dc.rights.accessrights', 'Embargoed Access', 'en');
										if ($aUpdateResults['response'] != '') {
											$sMessage = 'Something went wrong putting ' . $nItemId . ' under embargo.';
											foreach ($sUpdateResult as $key=>$value) {
												$sMessage .= $key . ' = ' . $value . ' ';
											}
											wlog($sMessage, 'ERROR');
										}
										else {
	                                        $sLine = 'setting item ' . $nItemId . ' to embargoed';
		                                    wlog($sLine);
			                                $sToday = date('Y-m-d H:i:s');
				                            $oItem->updateLastModified($nItemId, $sToday);
										}

                                        //note: the lifting of embargoes is done in liftEmbargoMetadata, 
                                        //so we don't have to worry about items with $sDateEmbargo < now here
                                    }
                                }
                            }
                        } //end of "if sDateEmbargo != ''    
                    } //end of "if isset values && count values > 0"
                } // end of "aDateEmbargo['error']
            } //end of "if not closed or restricted"
        } //end of "fetch accessrights field"
    } //end of "if in archive and not withdrawn"
}

wlog('==== Add rights metadata end ====', 'INF');


function checkEmbargoDate($sEmbargoDate)
{
    $sYear = substr($sEmbargoDate, 0, 4);
    $sMonth = substr($sEmbargoDate, 5, 2);
    $sDay = substr($sEmbargoDate, 8, 2);
    
    $bValid = checkdate($sMonth, $sDay, $sYear);
    
    return $bValid;
}


function sendMail($nItemId, $sDateEmbargo)
{
    $sEditLink = ADMINURL . '/item?itemID=' . $nItemId;

    $sFromAddress = DEVEMAIL;
    $sToAddress = ADMINEMAIL;
    
    $sCC = 'm.muilwijk@uu.nl';
    $sSubject = 'Foutieve embargodatum';
    
    $sMessage = "<html>\n";
    $sMessage .= "<body>\n";
    $sMessage .= 'Foutieve embargodatum <i>' . $sDateEmbargo . '</i>';
    $sMessage .= ' voor item <a href="' . $sEditLink . '">' . $nItemId . '</a>' . "\n";
    $sMessage .= '</body></html>' . "\n";
    
    $sHeaders = 'From:' . $sFromAddress . "\n";
    $sHeaders .= 'MIME-Version: 1.0' . "\n";
    $sHeaders .= 'Content-type: text/html; charset="utf-8"' . "\r\n";
    $sHeaders .= 'Cc:' . $sCC . "\r\n"; 
    
    mail($sToAddress, $sSubject, $sMessage, $sHeaders);
    
    return 1;
}
?>
