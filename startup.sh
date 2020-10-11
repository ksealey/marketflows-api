#!/usr/bin/bash
php artisan config:cache
php artisan migrate --force
service supervisor start
supervisorctl reread
supervisorctl update
supervisorctl start laravel-worker:*
apachectl -D FOREGROUND