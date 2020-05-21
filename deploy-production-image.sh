#!/usr/bin/bash
export APP_PATH=/var/www/app
export REPO_URL=212127452432.dkr.ecr.us-east-1.amazonaws.com/marketflows-api

# Pull and checkout code from master
git checkout production && git pull]

# Get the latest image
docker pull 212127452432.dkr.ecr.us-east-1.amazonaws.com/marketflows-api:latest

# Build image with code from latest image
docker build ./ -t $REPO_URL:production --no-cache

# Push image with production tag
# Log into docker 
aws ecr get-login-password --region us-east-1 | docker login  --username AWS  --password-stdin 212127452432.dkr.ecr.us-east-1.amazonaws.com

docker push $REPO_URL:production
