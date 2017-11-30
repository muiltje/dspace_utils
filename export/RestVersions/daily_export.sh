#!/bin/csh

cd /home/dspace/utils/newexport

#the next line gives a full dump
#php newexport.php -v -f -e --expdef=1

#the next line contains all expdefs that we will need for production
#3=scripties; 7=digobjects; 

php newexport.php  -v --expdef=3,7
#php derivatives.php
php repec_export.php
php exportcheck.php

