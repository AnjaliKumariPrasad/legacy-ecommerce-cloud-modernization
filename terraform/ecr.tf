resource "aws_ecr_repository" "bilal_store" {
  name                 = "bilal-store"
  image_tag_mutability = "MUTABLE"

  encryption_configuration {
    encryption_type = "AES256"
  }

  lifecycle {
    prevent_destroy = true
  }
}