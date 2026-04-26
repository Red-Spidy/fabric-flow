#!/bin/bash
set -e

echo "🚀 Starting Deployment on EC2 Instance..."

# Navigate to the app directory (or create it)
APP_DIR="/opt/fabricflow"
sudo mkdir -p $APP_DIR
sudo chown -R $USER:$USER $APP_DIR
cd $APP_DIR

# Pull the latest docker-compose file (you might want to sync this via scp instead if it changes)
# For this script, we'll write a minimal docker-compose up
echo "🔄 Pulling latest Docker image..."
sudo docker pull devansh/fabricflow-api:latest

# Ensure Docker Compose is running the latest image
echo "🔄 Restarting containers..."

# Simple run command. If using docker-compose, replace with docker-compose down && docker-compose up -d
sudo docker stop fabricflow-api || true
sudo docker rm fabricflow-api || true

sudo docker run -d \
  --name fabricflow-api \
  --restart unless-stopped \
  -p 80:8000 \
  -e APP_ENV=production \
  devansh/fabricflow-api:latest

echo "✅ Deployment completed successfully!"
