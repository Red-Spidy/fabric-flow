#!/bin/bash
# ════════════════════════════════════════════════════════════════════
#  APP SERVER BOOTSTRAP  (runs as root on first EC2 launch)
#  Installs Docker, clones repo, starts the stack, installs CW agent
# ════════════════════════════════════════════════════════════════════
set -euxo pipefail
LOG=/var/log/fabricflow-bootstrap.log
exec >> $LOG 2>&1

echo "=== FabricFlow App Bootstrap Starting $(date) ==="

# ─ System updates ─────────────────────────────────────────────────
dnf update -y
dnf install -y git curl unzip jq

# ─ Docker ─────────────────────────────────────────────────────────
dnf install -y docker
systemctl enable --now docker
usermod -aG docker ec2-user

# Docker Compose plugin
mkdir -p /usr/local/lib/docker/cli-plugins
curl -SL "https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64" \
     -o /usr/local/lib/docker/cli-plugins/docker-compose
chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

# ─ AWS CloudWatch Agent ────────────────────────────────────────────
dnf install -y amazon-cloudwatch-agent
cat > /opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json <<'CWEOF'
{
  "logs": {
    "logs_collected": {
      "files": {
        "collect_list": [
          {
            "file_path": "/var/log/fabricflow-bootstrap.log",
            "log_group_name": "/fabricflow/bootstrap",
            "log_stream_name": "{instance_id}"
          },
          {
            "file_path": "/opt/fabricflow/api/storage/logs/laravel-*.log",
            "log_group_name": "/fabricflow/laravel",
            "log_stream_name": "{instance_id}"
          }
        ]
      }
    }
  },
  "metrics": {
    "namespace": "FabricFlow/App",
    "metrics_collected": {
      "cpu":    { "measurement": ["cpu_usage_idle","cpu_usage_user"] },
      "mem":    { "measurement": ["mem_used_percent"] },
      "disk":   { "measurement": ["disk_used_percent"], "resources": ["*"] }
    }
  }
}
CWEOF
/opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl \
  -a fetch-config -m ec2 \
  -c file:/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json -s

# ─ Clone repo ─────────────────────────────────────────────────────
mkdir -p /opt/fabricflow
cd /opt/fabricflow

# The repo will be pulled here by Jenkins on first deploy.
# For initial bootstrap, create a minimal .env
cat > /opt/fabricflow/api/.env <<EOF
APP_NAME=FabricFlow
APP_ENV=production
APP_KEY=${app_key}
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=mysql
DATABASE_URL=mysql://root:${db_password}@database/fleetbase

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_URL=tcp://cache

LOG_CHANNEL=daily

AWS_DEFAULT_REGION=${aws_region}
AWS_S3_LOG_BUCKET=${s3_bucket}

MAIL_MAILER=ses
MAIL_FROM_ADDRESS="no-reply@fabricflow.com"
EOF

chmod 600 /opt/fabricflow/api/.env
chown -R ec2-user:ec2-user /opt/fabricflow

echo "=== Bootstrap Complete $(date) ==="
