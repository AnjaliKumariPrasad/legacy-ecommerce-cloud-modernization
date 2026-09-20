resource "aws_key_pair" "ec2" {
  key_name   = "legacy-app-key"
  public_key = file(pathexpand("~/.ssh/legacy-app-key.pub"))
}