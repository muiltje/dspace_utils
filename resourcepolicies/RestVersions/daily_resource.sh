#!/bin/csh
# file :  /home/dspace/utils/resourcepolicies/daily_resource.sh
# date :  16 juli 2013
# auth :  Marina Muilwijk
# expl :  run scripts voor opschonen embargo's en permissies
# history: 2017 removed liftEmbargoMetadata because there is now an Atmire job for that


# set logfile
cd ~/utils/resourcepolicies
php addResourcePolicies.php 
php addRightsMetadata.php 
#php liftEmbargoMetadata.php 
php dedoublePolicies.php 

#Atmire script for old embargos
cd ~/dspace/bin
dspace dsrun org.dspace.embargo.EmbargoExpiredMetadataUpdater
