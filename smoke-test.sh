#!/usr/bin/bash
export PROD_REPO_URL=212127452432.dkr.ecr.us-east-1.amazonaws.com

if [ -z "$1" ] 
    then
        echo "Tag required"
        exit
fi

aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin $PROD_REPO_URL

docker run --name=temp_api -p 80:80 -itd $PROD_REPO_URL/marketflows-api:$1

# Give it 10 seconds to get it's life together
sleep 10

# Smoke it! (with assertions)
. smoke.sh
smoke_url_ok localhost
smoke_url_ok localhost/api
smoke_url_ok localhost/api/v1
smoke_url_ok localhost/web
smoke_url_ok localhost/web/v1

# Kill the running container
docker stop temp_api
docker rm temp_api