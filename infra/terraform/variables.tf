variable "aws_region" {
  description = "AWS region to deploy resources"
  type        = string
  default     = "us-east-1"
}

variable "project_name" {
  description = "Project prefix for all resources"
  type        = string
  default     = "fabricflow"
}

variable "environment" {
  description = "Deployment environment"
  type        = string
  default     = "production"
}

variable "ec2_instance_type" {
  description = "EC2 instance type for application server"
  type        = string
  default     = "t3.medium"
}

variable "jenkins_instance_type" {
  description = "EC2 instance type for Jenkins CI server"
  type        = string
  default     = "t3.medium"
}

variable "key_pair_name" {
  description = "Name of the existing EC2 key pair for SSH access"
  type        = string
  default     = "vockey" # AWS Academy default key pair
}

variable "allowed_cidr" {
  description = "CIDR allowed to SSH into instances (your IP)"
  type        = string
  default     = "0.0.0.0/0"  # ⚠️ Restrict this to your IP in production
}

variable "db_password" {
  description = "MySQL root password"
  type        = string
  sensitive   = true
  default     = "FabricFlow@S3cur3!"
}

variable "app_key" {
  description = "Laravel APP_KEY (base64:...)"
  type        = string
  sensitive   = true
  default     = "base64:3uJb7aY/R7Fv6tA8mXvQe3Pq9hL7WbH1mY6nB3d2vF8="
}

variable "s3_log_bucket" {
  description = "S3 bucket name for logs and backups"
  type        = string
  default     = "fabricflow-logs-prod"
}

variable "alert_email" {
  description = "Email to receive CloudWatch delay alerts"
  type        = string
  default     = "admin@fabricflow.io"
}
