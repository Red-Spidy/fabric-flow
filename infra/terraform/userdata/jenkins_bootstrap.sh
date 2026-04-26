#!/bin/bash
# ════════════════════════════════════════════════════════════════════
#  JENKINS BOOTSTRAP  (runs as root on first EC2 launch)
# ════════════════════════════════════════════════════════════════════
set -euxo pipefail
LOG=/var/log/jenkins-bootstrap.log
exec >> $LOG 2>&1

echo "=== Jenkins Bootstrap Starting $(date) ==="

# ─ Java 17 (required by Jenkins) ──────────────────────────────────
dnf install -y java-17-amazon-corretto-headless

# ─ Jenkins LTS ────────────────────────────────────────────────────
wget -O /etc/yum.repos.d/jenkins.repo https://pkg.jenkins.io/redhat-stable/jenkins.repo
rpm --import https://pkg.jenkins.io/redhat-stable/jenkins.io-2023.key
dnf install -y jenkins
systemctl enable --now jenkins

# ─ Docker (Jenkins builds Docker images) ──────────────────────────
dnf install -y docker git curl unzip
systemctl enable --now docker
usermod -aG docker jenkins

# ─ Docker Compose ─────────────────────────────────────────────────
mkdir -p /usr/local/lib/docker/cli-plugins
curl -SL "https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64" \
     -o /usr/local/lib/docker/cli-plugins/docker-compose
chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

# ─ AWS CLI v2 ─────────────────────────────────────────────────────
curl -s "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o /tmp/awscli.zip
unzip -q /tmp/awscli.zip -d /tmp
/tmp/aws/install
rm -rf /tmp/aws /tmp/awscli.zip

echo "=== Jenkins Bootstrap Complete $(date) ==="
echo "Jenkins initial password: $(cat /var/lib/jenkins/secrets/initialAdminPassword 2>/dev/null || echo 'Not ready yet')"
