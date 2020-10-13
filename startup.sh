#!/bin/bash
php artisan config:cache
service supervisor start
supervisorctl reread
supervisorctl update
supervisorctl start laravel-worker:*
exec apachectl -D FOREGROUND