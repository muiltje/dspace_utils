<?php

require_once 'init.php';

$sManifestationUrl = MANIFESTATION_STORE . '/build?handle=';
//build?handle=1874-288785&material=infofile&force=1

$nExportId=7;

$oItem = new Item();

$aItems = $oItem->getInfoItems($nExportId);

echo count($aItems) . "\n";

foreach ($aItems as $aOneItem) {
    $sHandle = preg_replace('/\//', '-', $aOneItem['handle']);
        
    $sUrl = $sManifestationUrl . $sHandle . '&material=infofile&force=1';
    
    echo $sUrl . "\n";
    //launchBuild($sUrl);
    echo "done \n";
}


function launchBuild($sBuildUrl)
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


    
/*
 http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10131
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10132
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10133
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10136
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10137
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10138
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10139
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10140
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10141
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10142
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10143
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10144
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10145
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10232
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10244
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10245
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10246
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10247
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10248
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10249
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10250
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10251
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10252
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10253
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10254
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10255
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10256
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10257
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10258
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10259
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/10260
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/13293
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/20888
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/20889
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/31830
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/33113
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/33114
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/202163
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/217369
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/223250
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/227846
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/231192
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/237677
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/238491
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/250599
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/261573
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/275509
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/275695
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/284749
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/286658
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287186
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287187
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287188
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287192
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287622
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287623
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287624
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287625
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287626
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287627
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287734
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287735
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287736
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287737
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287738
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287739
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287740
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287741
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287742
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287743
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287744
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287745
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287746
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287747
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287748
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287749
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287750
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287793
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287794
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287795
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287796
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287815
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287816
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287817
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287839
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287943
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287944
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/287945
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288022
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288023
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288024
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288025
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288026
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288027
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288028
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288029
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288030
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288031
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288032
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288138
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288139
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288140
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288141
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288142
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288143
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288144
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288145
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288146
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288199
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288200
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288291
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288292
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288293
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288453
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288454
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288455
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288456
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288457
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288458
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288459
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288460
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288461
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288495
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288496
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288497
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288498
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288499
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288500
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288501
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288502
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288740
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288741
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288742
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288743
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288744
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288745
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288746
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288747
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288748
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288749
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288750
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288751
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288752
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288753
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288754
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288755
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288756
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288757
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288758
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288759
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288760
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288761
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288762
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288763
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288764
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288765
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288766
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288767
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288768
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288769
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288770
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288771
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288772
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288773
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288774
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288775
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288776
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288777
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288778
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288779
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288780
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288781
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288782
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288783
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288784
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288785
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288786
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288787
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288788
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288789
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288790
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288791
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288792
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288793
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288794
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288795
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288796
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288797
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288798
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288826
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288910
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288911
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/288934
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289187
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289188
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289189
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289254
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289255
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289256
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289257
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289258
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289599
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289600
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289601
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289602
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289603
http://manifestation-store.library.uu.nl/build?material=infofile&force=1&handle=1874/289675
 */    
    
?>
