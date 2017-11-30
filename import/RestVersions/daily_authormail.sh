#!/bin/csh
# file :  /home/dspace/utils/newimport/daily_authormail.sh
# date :  7 oct 2014
# auth :  Marina Muilwijk
# expl :  stuur email naar auteurs van Pure items die verwerkt zijn in DSpace
# mod  :  
#         

#run after daily_export, i.e. around 22:00 

cd ~/utils/newimport
php send_author_mail.php


