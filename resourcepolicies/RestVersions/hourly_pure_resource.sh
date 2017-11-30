#!/bin/csh
# file :  /home/dspace/utils/resourcepolicies/hourly_pure_resource.sh
# date :  14 september 2014
# auth :  Marina Muilwijk
# expl :  add resource policies and rights metadata
#			NB: this does not lift embargos, that is done in the daily job


# set logfile
cd ~/utils/resourcepolicies
php addResourcePolicies.php 
php addRightsMetadata.php 

