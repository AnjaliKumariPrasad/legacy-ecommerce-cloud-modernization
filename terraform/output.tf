output "s3_upload_bucket_name" {
  value = aws_s3_bucket.uploads.bucket
}

output "s3_upload_bucket_arn" {
  value = aws_s3_bucket.uploads.arn
}

output "rds_endpoint" {
  value = aws_db_instance.mysql.address
}

output "rds_port" {
  value = aws_db_instance.mysql.port
}

output "ec2_public_ip" {
  value = aws_instance.app.public_ip
}

output "ec2_private_ip" {
  value = aws_instance.app.private_ip
}

output "ecr_repository_url" {
  value = aws_ecr_repository.bilal_store.repository_url
}