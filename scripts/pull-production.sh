#!/usr/bin/bash
aws ecr get-login-password --region us-east-1 | docker login  --username AWS  --password-stdin 212127452432.dkr.ecr.us-east-1.amazonaws.com

docker pull 212127452432.dkr.ecr.us-east-1.amazonaws.com/marketflows-api:production
docker stop mf_api_web
docker rm mf_api_web
docker run -d --name=mf_api_web -p 80:80 --restart=unless-stopped 212127452432.dkr.ecr.us-east-1.amazonaws.com/marketflows-api:production
exit