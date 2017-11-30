#!/bin/csh
# file :  /home/dspace/utils/newimport/daily_pure.sh
# date :  7 oct 2014
# auth :  Marina Muilwijk
# expl :  run scripts voor nabewerking van items uit Pure
# mod  :  
#         

#run after Pure sync, i.e. after 01:00
#if desired, run more frequently, if Pure sends updates more frequenlty

cd ~/utils/newimport
php author_split.php
cd ~/utils/resourcepolicies
daily_resource.sh

