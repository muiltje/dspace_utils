#!/bin/csh
# file :  /home/dspace/utils/newimport/daily_import.sh
# date :  13 juni 2013
# auth :  Marina Muilwijk
# expl :  run scripts voor importeren van scrol, sabine en igitur in dspace
# mod  :  
#         MM 15-1-2014: bijzcoll import moved to daily_bijz_import.sh, so that we can start this one earlier
#         MM 31-1-2014: added author_split
#         MM 21-3-2014: moved author_split to its own .sh, so that it can start later


cd ~/utils/newimport
php auxvendor.php
php scrol_import.php -v -i
php sabine_import.php -v -i
cd ~/utils/resourcepolicies
daily_resource.sh

