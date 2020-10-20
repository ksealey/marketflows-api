#!/usr/bin/bash
export PROD_REPO_URL=212127452432.dkr.ecr.us-east-1.amazonaws.com
export IMAGE=marketflows-api
if [ -z "$1" ] 
    then
        echo "Tag required"
        exit
fi

# Login to container service
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin $PROD_REPO_URL

# Build new image
docker build -t $IMAGE:$1 . --no-cache

# Run smoke tests
if sh smoke-test.sh $1; then
    docker tag $IMAGE:$1 $PROD_REPO_URL/$IMAGE:$1
    docker push $PROD_REPO_URL/$IMAGE:$1
else
    echo "Smoke test failed"
fi



