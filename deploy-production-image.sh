#!/usr/bin/bash
export PROD_REPO_URL=212127452432.dkr.ecr.us-east-1.amazonaws.com

# Pull and checkout code from master
git checkout production && git pull

# Get the latest image
docker pull marketflows/ubuntu18-php7:latest

# Build production version of the image with baked-in code
if docker build -t marketflows-api:production . --no-cache; then
    # Log into production docker 
    aws ecr get-login-password --region us-east-1 | docker login  --username AWS  --password-stdin $PROD_REPO_URL
    
    # Create an copy of the current prod image to use as rollback
    docker pull $PROD_REPO_URL/marketflows-api:production
    docker tag $PROD_REPO_URL/marketflows-api:production $PROD_REPO_URL/marketflows-api:production-rollback
    docker push $PROD_REPO_URL/marketflows-api:production-rollback

    # Tag the new image
    docker tag marketflows-api:production $PROD_REPO_URL/marketflows-api:production

    # Push new image with production tag
    docker push $PROD_REPO_URL/marketflows-api:production
fi



