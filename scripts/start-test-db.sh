#!/usr/bin/bash
docker stop test_db
docker rm test_db
docker run -d -p 3333:3306 --name=test_db \
-e MYSQL_DATABASE=marketflows \
-e MYSQL_USER=root \
-e MYSQL_PASSWORD=root \
-e MYSQL_ROOT_PASSWORD=root \
mysql:5.7.22

sleep 10