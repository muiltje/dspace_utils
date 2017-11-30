#!/bin/csh
# file :  /home/dspace/utils/newexport/daily_pure_meta_recon.sh
# date :  7 oct 2014
# auth :  Marina Muilwijk
# expl :  reconstruction of older metadata that were overwritten by Pure
#           add ddid, urnnbn, accessrights if missing
#           
# mod  :  
#  

#run before the regular daily_export, i.e. before 19:30;
#could also run right after newimport/daily_pure or in the early morning

cd /home/dspace/utils/newexport

#php metadata_reconstruction.php
#php pod_restore.php
php save_pure_basics.php

