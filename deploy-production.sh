#!/usr/bin/bash
export DEPLOY_CMD_1="sudo aws ecr get-login-password --region us-east-1 | sudo docker login  --username AWS  --password-stdin 212127452432.dkr.ecr.us-east-1.amazonaws.com"
export DEPLOY_CMD_2="sudo docker pull 212127452432.dkr.ecr.us-east-1.amazonaws.com/marketflows-api:production"
export DEPLOY_CMD_3="sudo docker stop mf_api_web && sudo docker rm -f mf_api_web && sudo docker run -d --name=mf_api_web -p 80:80 --restart=unless-stopped 212127452432.dkr.ecr.us-east-1.amazonaws.com/marketflows-api:production"

# Collect SSH ket path
read -p 'Enter SSH Key Path: ' SSH_KEY_PATH

# List all instances in ASG
EC2_IPS=$(aws ec2 describe-instances --filters "Name=tag:aws:autoscaling:groupName,Values=marketflows-api" --query="Reservations[*].Instances[*].PublicIpAddress" --output text)
for ip in $EC2_IPS
    do
        echo "Deploying to $ip";
        ssh -i $SSH_KEY_PATH ubuntu@$ip "$DEPLOY_CMD_1 && $DEPLOY_CMD_2 && $DEPLOY_CMD_3"
done