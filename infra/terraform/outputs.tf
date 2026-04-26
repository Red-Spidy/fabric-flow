output "app_public_ip" {
  description = "Public IP of the application server"
  value       = aws_eip.app.public_ip
}

output "app_public_dns" {
  description = "Public DNS of the application server"
  value       = aws_eip.app.public_dns
}

output "jenkins_public_ip" {
  description = "Public IP of the Jenkins CI server"
  value       = aws_eip.jenkins.public_ip
}

output "jenkins_url" {
  description = "Jenkins dashboard URL"
  value       = "http://${aws_eip.jenkins.public_ip}:8080"
}

output "app_url" {
  description = "FabricFlow application URL"
  value       = "http://${aws_eip.app.public_ip}/dashboard.html"
}

output "s3_log_bucket" {
  description = "S3 bucket name for logs"
  value       = aws_s3_bucket.logs.bucket
}

output "cloudwatch_dashboard_url" {
  description = "AWS CloudWatch Dashboard URL"
  value       = "https://console.aws.amazon.com/cloudwatch/home?region=${var.aws_region}#dashboards:name=${aws_cloudwatch_dashboard.main.dashboard_name}"
}

output "sns_alert_topic_arn" {
  description = "SNS Topic ARN for alerts"
  value       = aws_sns_topic.alerts.arn
}
