#!/usr/bin/bash

# List all instances in ASG
EC2_IPS=$(aws ec2 describe-instances --filters "Name=tag:aws:autoscaling:groupName,Values=marketflows-api" --query="Reservations[*].Instances[*].PublicIpAddress" --output text)

# Boot a container with the new code to run migration
for ip in $EC2_IPS
    do
        echo "Deploying to $ip";
done


