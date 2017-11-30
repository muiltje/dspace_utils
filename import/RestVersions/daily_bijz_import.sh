#!/bin/csh
# file :  /home/dspace/utils/newimport/daily_bijz_import.sh
# date :  23 januari 2014
# auth :  Marina Muilwijk
# expl :  run scripts voor importeren van bijzcoll in dspace

cd ~/utils/newimport
php auxvendor.php
php bijzcoll_import.php -v -i
php bijzcoll_qc_import.php -v -i
php set_to_private.php
cd ~/utils/newimport/Checks
php character_check.php

