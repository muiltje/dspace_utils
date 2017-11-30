#!/bin/csh
# file :  /home/dspace/utils/newimport/bnb_import.sh
# date :  11 oktober 2013
# auth :  Marina Muilwijk
# expl :  run scripts voor importeren van bnb (metamorfoze) boeken in dspace
# notes :  alleen als er een bnb klus is; dus niet standaard aan zetten!
#       :  grote job, dus laat hem lekker 's ochtends vroeg lopen, voordat
#          de normale bijzcoll import start


cd ~/utils/newimport
php bijzcoll_import.php -v -M -i -m 50G

