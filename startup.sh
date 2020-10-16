#!/bin/bash
php artisan cache:clear
php artisan config:cache
service supervisor start
supervisorctl reread
supervisorctl update
supervisorctl start laravel-worker:*
exec apachectl -D FOREGROUND