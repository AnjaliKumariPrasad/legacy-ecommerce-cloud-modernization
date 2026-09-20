data "aws_ami" "ubuntu" {
  most_recent = true

  filter {
    name   = "name"
    values = ["ubuntu/images/hvm-ssd-gp3/ubuntu-noble-24.04-amd64-server-*"]
  }

  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }

  owners = ["099720109477"]
}

resource "aws_instance" "app" {
  ami           = data.aws_ami.ubuntu.id
  instance_type = "t3.micro"

  key_name = aws_key_pair.ec2.key_name

  subnet_id = aws_subnet.public.id

  vpc_security_group_ids = [
    aws_security_group.ec2.id
  ]

  associate_public_ip_address = true

  iam_instance_profile = aws_iam_instance_profile.ec2.name

  root_block_device {
    volume_type = "gp3"
    volume_size = 8
  }

  tags = {
    Name = "${var.project_name}-ec2"
  }
}