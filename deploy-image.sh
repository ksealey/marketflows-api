#!/usr/bin/bash
export PROD_REPO_URL=212127452432.dkr.ecr.us-east-1.amazonaws.com
if [ -z "$1" ] 
    then
        echo "Tag required"
        exit
fi

aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin $PROD_REPO_URL
docker build -t marketflows-api . --no-cache
docker tag marketflows-api:$1 $PROD_REPO_URL/marketflows-api:$1
docker push $PROD_REPO_URL/marketflows-api:$1


