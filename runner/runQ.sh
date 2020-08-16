#!/bin/bash

#
# our docker entrypoint script to run apache & our queueWorker script on loop
#

chown -R www-data:www-data /var/www/html
chmod +x /var/www/html/*php

source /etc/apache2/envvars
apache2 -k start &
while true
do
  php /var/www/html/queueWorker.php
  sleep 5
done


