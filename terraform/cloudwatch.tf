resource "aws_cloudwatch_metric_alarm" "ec2_high_cpu" {
  alarm_name          = "${var.project_name}-ec2-high-cpu"
  alarm_description   = "Alarm when EC2 CPU utilization is high"
  comparison_operator = "GreaterThanOrEqualToThreshold"

  evaluation_periods = 1
  metric_name        = "CPUUtilization"
  namespace          = "AWS/EC2"
  period             = 300
  statistic          = "Average"
  threshold          = 80

  dimensions = {
    InstanceId = aws_instance.app.id
  }

  treat_missing_data = "notBreaching"

  alarm_actions = []
  ok_actions    = []
}

resource "aws_cloudwatch_metric_alarm" "rds_low_storage" {
  alarm_name          = "${var.project_name}-rds-low-storage"
  alarm_description   = "Alarm when RDS free storage becomes low"
  comparison_operator = "LessThanThreshold"

  evaluation_periods = 1
  metric_name        = "FreeStorageSpace"
  namespace          = "AWS/RDS"
  period             = 300
  statistic          = "Average"
  threshold          = 2 * 1024 * 1024 * 1024

  dimensions = {
    DBInstanceIdentifier = aws_db_instance.mysql.id
  }

  treat_missing_data = "notBreaching"

  alarm_actions = []
  ok_actions    = []
}