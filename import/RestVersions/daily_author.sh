#!/bin/csh
# file :  /home/dspace/utils/newimport/daily_author.sh
# date :  21 maart 2014
# auth :  Marina Muilwijk
# expl :  split author names in name - DAI
# mod  :  
#         MM 15-1-2014: bijzcoll import moved to daily_bijz_import.sh, so that we can start this one earlier
#         MM 31-1-2014: added author_split
#         MM 21-3-2014: moved author_split to its own .sh, so that it can start later


cd ~/utils/newimport
php author_split.php


