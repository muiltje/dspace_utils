#!/bin/csh
#make a new OAI index

cd /home/dspace/dspace/bin

dspace oai import -c
dspace oai clean-cache

