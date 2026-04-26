# ── CloudWatch Log Groups ──────────────────────────────────────────────────────
resource "aws_cloudwatch_log_group" "laravel" {
  name              = "/fabricflow/laravel"
  retention_in_days = 30
  tags              = { Project = var.project_name }
}

resource "aws_cloudwatch_log_group" "nginx" {
  name              = "/fabricflow/nginx"
  retention_in_days = 14
  tags              = { Project = var.project_name }
}

resource "aws_cloudwatch_log_group" "delays" {
  name              = "/fabricflow/shipment-delays"
  retention_in_days = 90
  tags              = { Project = var.project_name }
}

# ── SNS Topic for Alert Emails ─────────────────────────────────────────────────
resource "aws_sns_topic" "alerts" {
  name = "${var.project_name}-alerts"
  tags = { Project = var.project_name }
}

resource "aws_sns_topic_subscription" "email" {
  topic_arn = aws_sns_topic.alerts.arn
  protocol  = "email"
  endpoint  = var.alert_email
}

# ── CloudWatch Alarms ──────────────────────────────────────────────────────────

# 1. High CPU on App server
resource "aws_cloudwatch_metric_alarm" "high_cpu" {
  alarm_name          = "${var.project_name}-high-cpu"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 2
  metric_name         = "CPUUtilization"
  namespace           = "AWS/EC2"
  period              = 300
  statistic           = "Average"
  threshold           = 80
  alarm_description   = "App server CPU > 80% for 10 minutes"
  alarm_actions       = [aws_sns_topic.alerts.arn]
  ok_actions          = [aws_sns_topic.alerts.arn]

  dimensions = { InstanceId = aws_instance.app.id }
}

# 2. Shipment delay count > 0 (custom metric from Laravel)
resource "aws_cloudwatch_metric_alarm" "delayed_shipments" {
  alarm_name          = "${var.project_name}-delayed-shipments"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 1
  metric_name         = "DelayedShipments"
  namespace           = "FabricFlow/Shipments"
  period              = 300
  statistic           = "Maximum"
  threshold           = 0
  treat_missing_data  = "notBreaching"
  alarm_description   = "One or more shipments have been flagged as delayed"
  alarm_actions       = [aws_sns_topic.alerts.arn]

  # No dimensions — application-wide metric
}

# 3. High Memory
resource "aws_cloudwatch_metric_alarm" "high_mem" {
  alarm_name          = "${var.project_name}-high-memory"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 2
  metric_name         = "mem_used_percent"
  namespace           = "FabricFlow/App"
  period              = 300
  statistic           = "Average"
  threshold           = 85
  alarm_description   = "Memory usage > 85%"
  alarm_actions       = [aws_sns_topic.alerts.arn]
}

# ── CloudWatch Dashboard ───────────────────────────────────────────────────────
resource "aws_cloudwatch_dashboard" "main" {
  dashboard_name = "${var.project_name}-dashboard"

  dashboard_body = jsonencode({
    widgets = [
      {
        type       = "metric"
        properties = {
          title   = "CPU Utilization"
          metrics = [["AWS/EC2", "CPUUtilization", "InstanceId", aws_instance.app.id]]
          period  = 300
          stat    = "Average"
          view    = "timeSeries"
        }
      },
      {
        type       = "metric"
        properties = {
          title   = "Delayed Shipments"
          metrics = [["FabricFlow/Shipments", "DelayedShipments"]]
          period  = 300
          stat    = "Maximum"
          view    = "timeSeries"
        }
      },
      {
        type       = "log"
        properties = {
          title   = "Recent Delay Alerts"
          query   = "fields @timestamp, @message | filter @logStream = 'delays' | sort @timestamp desc | limit 20"
          region  = var.aws_region
          logGroupNames = [aws_cloudwatch_log_group.delays.name]
          view    = "table"
        }
      }
    ]
  })
}
