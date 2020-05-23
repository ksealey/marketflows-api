#!/usr/bin/bash
export PROD_REPO_URL=212127452432.dkr.ecr.us-east-1.amazonaws.com
export DEPLOY_CMD_1="sudo aws ecr get-login-password --region us-east-1 | sudo docker login  --username AWS  --password-stdin $PROD_REPO_URL"
export DEPLOY_CMD_2="sudo docker pull $PROD_REPO_URL/marketflows-api:production"
export DEPLOY_CMD_3="sudo docker stop mf_api_web && sudo docker rm -f mf_api_web && sudo docker run -d --name=mf_api_web -p 80:80 --restart=unless-stopped $PROD_REPO_URL/marketflows-api:production"

# Collect SSH key path
read -p 'Enter SSH Key Path: ' SSH_KEY_PATH
read -p 'Enter Master DB Username: ' DB_USERNAME
read -p 'Enter Master DB Password: ' DB_PASSWORD

# List all instances in ASG
EC2_IPS=$(aws ec2 describe-instances --filters "Name=tag:aws:autoscaling:groupName,Values=marketflows-api" --query="Reservations[*].Instances[*].PublicIpAddress" --output text)

# Log into docker
aws ecr get-login-password --region us-east-1 | docker login  --username AWS  --password-stdin $PROD_REPO_URL

# Get the latest image
docker pull $PROD_REPO_URL/marketflows-api:production

# Boot a container with the new code to run migration
if docker run -e DB_USERNAME=$DB_USERNAME -e DB_PASSWORD=$DB_PASSWORD $PROD_REPO_URL/marketflows-api:production sh -c "cd /var/www/app && php artisan migrate"; then
    for ip in $EC2_IPS
        do
            echo "Deploying to $ip";
            ssh -i $SSH_KEY_PATH ubuntu@$ip "$DEPLOY_CMD_1 && $DEPLOY_CMD_2 && $DEPLOY_CMD_3"
    done
fi

