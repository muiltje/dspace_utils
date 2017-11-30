#!/bin/csh
#stuur mail dat embargo van proefschrift afloopt

cd /home/dspace/utils/newexport/embargoalert

php embargoalert.php -a -v -s
php studentthesis_embargo_alert.php -a -v -s

