#!/bin/sh
sleep 5
php /var/www/html/artisan queue:work redis --queue=default,ai --sleep=3 --tries=3 --max-time=3600
