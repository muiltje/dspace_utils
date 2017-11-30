#!/bin/sh

cd /home/dspace/utils/newexport/postExport
php postDspaceProcess.php

#temp for Guido
php scrolMail.php

#echo Export parsed and mail sent
