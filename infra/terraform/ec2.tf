# Latest Amazon Linux 2023 AMI (always gets the newest stable)
data "aws_ami" "amazon_linux" {
  most_recent = true
  owners      = ["amazon"]

  filter {
    name   = "name"
    values = ["al2023-ami-*-x86_64"]
  }
  filter {
    name   = "state"
    values = ["available"]
  }
}

# ══════════════════════════════════════════════════════════════
#  APPLICATION SERVER
# ══════════════════════════════════════════════════════════════
resource "aws_instance" "app" {
  ami                    = data.aws_ami.amazon_linux.id
  instance_type          = var.ec2_instance_type
  subnet_id              = aws_subnet.public_a.id
  vpc_security_group_ids = [aws_security_group.app.id]
  iam_instance_profile   = aws_iam_instance_profile.ec2_profile.name
  key_name               = var.key_pair_name

  root_block_device {
    volume_size           = 30
    volume_type           = "gp3"
    delete_on_termination = true
  }

  # Bootstrap script runs once on first launch
  user_data = base64encode(templatefile("${path.module}/userdata/app_bootstrap.sh", {
    project_name = var.project_name
    app_key      = var.app_key
    db_password  = var.db_password
    s3_bucket    = var.s3_log_bucket
    aws_region   = var.aws_region
  }))

  tags = {
    Name        = "${var.project_name}-app"
    Environment = var.environment
    Role        = "application"
  }
}

# Elastic IP for stable public address
resource "aws_eip" "app" {
  instance = aws_instance.app.id
  domain   = "vpc"
  tags     = { Name = "${var.project_name}-app-eip" }
}

# ══════════════════════════════════════════════════════════════
#  JENKINS CI SERVER
# ══════════════════════════════════════════════════════════════
resource "aws_instance" "jenkins" {
  ami                    = data.aws_ami.amazon_linux.id
  instance_type          = var.jenkins_instance_type
  subnet_id              = aws_subnet.public_b.id
  vpc_security_group_ids = [aws_security_group.jenkins.id]
  iam_instance_profile   = aws_iam_instance_profile.ec2_profile.name
  key_name               = var.key_pair_name

  root_block_device {
    volume_size           = 30
    volume_type           = "gp3"
    delete_on_termination = true
  }

  user_data = base64encode(file("${path.module}/userdata/jenkins_bootstrap.sh"))

  tags = {
    Name        = "${var.project_name}-jenkins"
    Environment = var.environment
    Role        = "ci-cd"
  }
}

resource "aws_eip" "jenkins" {
  instance = aws_instance.jenkins.id
  domain   = "vpc"
  tags     = { Name = "${var.project_name}-jenkins-eip" }
}
