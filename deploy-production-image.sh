#!/usr/bin/bash
export APP_PATH=/var/www/app
export RELEASE=$(git describe --abbrev=0 --tags | grep -o -i -E "[A-z0-9\-]+$") 
export REPO_URL=212127452432.dkr.ecr.us-east-1.amazonaws.com/marketflows-api

# Pull and checkout code from master
git checkout master && git pull

# Add code to base image
docker build ./ -t $REPO_URL:latest --no-cache

# Run tests
docker run -w $APP_PATH --env-file .env $REPO_URL:latest ./vendor/bin/phpunit
if [ $? -ne 0 ]; then
    echo "Test failed"
    exit
fi

docker tag $REPO_URL:latest $REPO_URL:$RELEASE

# Push image with production tag
$(aws ecr get-login --no-include-email)
docker push $REPO_URL:latest
docker push $REPO_URL:$RELEASE

