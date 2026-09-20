resource "aws_db_subnet_group" "rds" {
  name = "${var.project_name}-rds-subnet-group"

  subnet_ids = [
    aws_subnet.private_1.id,
    aws_subnet.private_2.id
  ]

  tags = {
    Name = "${var.project_name}-rds-subnet-group"
  }
}

resource "aws_db_parameter_group" "mysql" {
  name        = "${var.project_name}-mysql-params"
  family      = "mysql8.0"
  description = "MySQL parameter group for legacy application"

  tags = {
    Name = "${var.project_name}-mysql-params"
  }
}

resource "aws_db_instance" "mysql" {
  identifier = "${var.project_name}-db"

  engine         = "mysql"
  engine_version = "8.0"

  instance_class        = "db.t3.micro"
  allocated_storage     = 20
  storage_type          = "gp3"
  storage_encrypted     = true
  max_allocated_storage = 50

  db_name  = "bilal_store"
  username = var.rds_username
  password = var.rds_password
  port     = 3306

  db_subnet_group_name   = aws_db_subnet_group.rds.name
  vpc_security_group_ids = [aws_security_group.rds.id]
  parameter_group_name   = aws_db_parameter_group.mysql.name

  publicly_accessible = false

  backup_retention_period = 7
  backup_window           = "18:00-19:00"

  maintenance_window = "sun:19:00-sun:20:00"

  multi_az = false

  deletion_protection = false
  skip_final_snapshot = true

  apply_immediately = true

  tags = {
    Name = "${var.project_name}-rds"
  }
}
