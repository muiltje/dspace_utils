#!/bin/csh
# file :  /home/dspace/utils/newimport/hourly_pure.sh
# date :  7 oct 2014
# auth :  Marina Muilwijk
# expl :  run scripts voor nabewerking van items uit Pure
# mod  :  
#         

#run frequently, to catch metadata updates during the day

#check current urn
cd ~/utils/newexport
php monitor.php
#split authors with DAI into name and DAI
cd ~/utils/newimport
php author_split.php
#set the correct resource policies
cd ~/utils/resourcepolicies
hourly_pure_resource.sh
#set urnnbn and urlfulltext
cd ~/utils/newexport
php enrichment.php
php pod_restore.php
#check current urn again
php monitor.php
