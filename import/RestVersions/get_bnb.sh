#!/bin/csh
# file :  /home/dspace/utils/newimport/get_bnb.sh
# date :  25 feb 2014
# auth :  Marina Muilwijk
# expl :  haal de volgende set items met tifs op en zet ze klaar voor bnb_import.sh
# notes :  alleen als er een bnb klus is; dus niet standaard aan zetten!
#       :  grote job, die klaar moet zijn voor bnb_import.sh begint, 
#          dus we laten hem 's avonds starten


cd ~/utils/newimport/BnbImports
php placeFiles.php

