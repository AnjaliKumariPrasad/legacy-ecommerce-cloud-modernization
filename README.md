# Legacy PHP Application to AWS — Cloud Modernization & DevOps Automation

> A hands-on migration of a legacy PHP/MySQL e-commerce application to AWS, with Docker containerization, Infrastructure as Code, configuration automation, monitoring, and CI/CD.

---

## 📌 Project Overview

This project takes a legacy PHP/MySQL e-commerce application that was originally designed to run in a local environment and moves it to AWS.

The goal was not simply to "put the application on a server." The project introduces a repeatable DevOps workflow in which:

- AWS infrastructure is provisioned with **Terraform**
- EC2 configuration is automated with **Ansible**
- The application is containerized with **Docker**
- Docker images are stored in **Amazon ECR**
- Application data is migrated to **Amazon RDS for MySQL**
- S3 infrastructure is provisioned for object storage and validated through IAM-based EC2 access
- Infrastructure is monitored with **Amazon CloudWatch**
- Alarm notifications are delivered through **Amazon SNS**
- Build and deployment are automated with **Jenkins**
- Source code is maintained in **GitHub**

The result is a reproducible cloud deployment rather than a server that has to be configured manually every time.

---

# 🏗️ Architecture

```text
                         DEVELOPER
                             |
                             | git push
                             v
                          GitHub
                             |
                             | Poll SCM
                             v
                          Jenkins
                    CI/CD Pipeline
                             |
          +------------------+------------------+
          |                  |                  |
          v                  v                  v
    Docker Build      AWS Authentication    Git Commit SHA
          |                  |                  |
          +------------------+------------------+
                             |
                             v
                       Amazon ECR
                       Docker Image
                             |
                             | SSH
                             v
                    +-------------------+
                    |       EC2         |
                    |                   |
                    | Docker Container  |
                    |  Bilal Store App  |
                    +---------+---------+
                              |
                    +---------+---------+
                    |                   |
                    v                   v
              Amazon RDS             Amazon S3
              MySQL Database       Object Storage
                    |
                    |
              Application Data

                 EC2 / RDS Metrics
                         |
                         v
                    CloudWatch
                         |
                         v
                       SNS
                         |
                         v
                     Email Alert
```

### Infrastructure automation layer

```text
Terraform
   |
   +--> VPC / Networking
   +--> Security Groups
   +--> EC2
   +--> RDS
   +--> ECR
   +--> IAM
   +--> S3
   +--> CloudWatch

Ansible
   |
   +--> Configure EC2
   +--> Install Docker dependencies
   +--> Configure application
   +--> Deploy container

Jenkins
   |
   +--> Build
   +--> Authenticate
   +--> Push to ECR
   +--> Deploy to EC2
```

---

# 🔄 End-to-End Deployment Flow

A code change follows this path:

```text
Developer changes code
        |
        v
git push
        |
        v
GitHub
        |
        v
Jenkins detects repository change
        |
        v
Checkout source code
        |
        v
Build Docker image
        |
        v
Authenticate to AWS
        |
        v
Tag image with Git commit SHA
        |
        v
Push image to Amazon ECR
        |
        v
SSH to EC2
        |
        v
EC2 authenticates to ECR using IAM role
        |
        v
Pull exact image
        |
        v
Stop old container
        |
        v
Start new container
        |
        v
Application connects to RDS
```

This separates **source control, image management, infrastructure, configuration, and deployment**.

---

# 🎯 Why This Architecture?

The architecture was kept intentionally practical rather than adding services only to make the diagram larger.

Each major component has a specific responsibility:

| Component | Responsibility |
|---|---|
| GitHub | Source code management |
| Jenkins | CI/CD automation |
| Docker | Application containerization |
| ECR | Private Docker image registry |
| EC2 | Runs the application container |
| RDS | Managed MySQL database |
| S3 | Object storage infrastructure |
| IAM | Authentication and authorization |
| Terraform | Infrastructure provisioning |
| Ansible | Server/application configuration |
| CloudWatch | Monitoring |
| SNS | Alarm notifications |

---

# 🧱 Application Migration

## Before Migration

The legacy application was designed around a local deployment model:

```text
PHP + Apache
     |
     v
Local MySQL
     |
     v
Local filesystem
```

This creates problems when trying to move the application between environments because the application, database, and server configuration are closely coupled to the local machine.

## After Migration

The application is separated into cloud-managed components:

```text
PHP Application
      |
      v
Docker Container
      |
      +----------> Amazon RDS
      |
      +----------> AWS services through IAM
```

The application is therefore no longer dependent on a local MySQL server.

---

# 🐳 Dockerization

The legacy PHP application was containerized using Docker.

The application image uses:

```dockerfile
FROM php:8.2-apache
```

Required PHP database extensions are installed:

```text
pdo
pdo_mysql
```

Apache rewrite support is enabled.

The Docker image also uses a Composer build stage to install PHP dependencies.

## Docker Build Design

```text
composer.json
composer.lock
       |
       v
Composer build stage
       |
       v
vendor/
       |
       v
PHP 8.2 + Apache
       |
       v
Final application image
```

The AWS SDK for PHP was added as a Composer dependency.

### Why multi-stage Docker build?

The dependency installation is separated from the final runtime image. This keeps the build process organized and allows Composer dependencies to be generated during the Docker build.

---

# ☁️ AWS Infrastructure with Terraform

Terraform is responsible for provisioning the AWS infrastructure.

The configuration is organized by responsibility:

```text
app-infrastucture/
└── terraform/
    ├── provider.tf
    ├── variables.tf
    ├── vpc.tf
    ├── ec2.tf
    ├── rds.tf
    ├── iam.tf
    ├── ecr.tf
    ├── s3.tf
    ├── cloudwatch.tf
    └── outputs.tf
```

## Terraform responsibilities

Terraform manages:

- VPC/networking
- Subnets
- Internet connectivity
- Security groups
- EC2
- RDS MySQL
- ECR repository
- IAM role and policies
- S3 bucket
- CloudWatch alarms
- Terraform outputs

## Typical workflow

```bash
terraform fmt
terraform validate
terraform plan
terraform apply
```

### Why Terraform?

The infrastructure becomes:

- Version-controlled
- Repeatable
- Reviewable
- Reproducible
- Easier to modify safely

Instead of creating resources manually in the AWS console, the desired infrastructure is described as code.

---

# 🖥️ Amazon EC2

EC2 is the compute layer where the application container runs.

The EC2 instance is configured with:

- Ubuntu
- Docker
- AWS CLI
- Required Docker/Ansible dependencies
- Application environment configuration
- IAM role

The application runs as:

```text
EC2
└── Docker
    └── bilal-store-app
```

---

# 🗄️ Database Migration to Amazon RDS

The legacy MySQL database was migrated to Amazon RDS for MySQL.

The target database is:

```text
bilal_store
```

Verified tables include:

```text
activity_log
categories
order_items
orders
products
settings
users
```

The migrated data was verified using SQL queries.

Example verification:

```text
users     = 1
products  = 3
orders    = 0
```

The PHP application's database configuration was changed to use the RDS endpoint instead of the local MySQL container.

## Important Database Lesson

An application database connection has multiple independent failure layers:

```text
Network reachability
       |
       v
Correct hostname
       |
       v
Correct port
       |
       v
Database exists
       |
       v
Correct username/password
       |
       v
Required permissions
```

During this project, both network/port configuration and database authentication had to be diagnosed separately.

---

# 🔐 AWS IAM

IAM controls which AWS resources each component can access.

Two identities were intentionally separated.

## EC2 IAM Role

EC2 uses an IAM role rather than storing permanent AWS credentials on the server.

Conceptually:

```text
EC2
 |
 v
IAM Role
 |
 +--> ECR pull
 |
 +--> S3 access
```

## Jenkins AWS Identity

Jenkins uses a dedicated AWS identity:

```text
jenkins-ecr-user
```

Its credentials are stored inside Jenkins rather than being committed to source control.

The Jenkins credential is referenced through:

```text
aws-ecr
```

---

# 📦 Amazon ECR

Amazon ECR is the private Docker image registry.

The image flow is:

```text
Docker Build
     |
     v
Tag image
     |
     v
Amazon ECR
     |
     v
EC2 pulls image
```

Jenkins logs into ECR, tags the image, and pushes it.

EC2 then authenticates to ECR using its IAM role.

This avoids storing permanent AWS credentials on the EC2 server.

---

# 🪣 Amazon S3

An S3 bucket was provisioned with Terraform.

Example bucket:

```text
legacy-app-uploads-016617991046
```

The bucket configuration includes:

- Block Public Access
- Bucket Owner Enforced
- Server-side encryption using SSE-S3
- IAM-controlled access

The EC2 IAM role was granted:

```text
s3:ListBucket
s3:GetObject
s3:PutObject
s3:DeleteObject
```

## S3 Validation

The EC2-to-S3 path was tested by:

1. Creating a test file on EC2
2. Uploading it to S3
3. Inspecting the object
4. Downloading it
5. Deleting it

This verified:

```text
EC2
 |
 v
IAM Role
 |
 v
S3
```

### Scope of S3 integration

The S3 infrastructure and IAM access are implemented and tested.

The legacy PHP upload handlers were **not** migrated to native S3 uploads.

Therefore the project accurately claims:

> Provisioned and validated private S3 object storage and IAM-based EC2 access.

It does **not** claim:

> The PHP application currently stores all uploads in S3.

---

# ⚙️ Ansible

Ansible is used for configuration management and deployment on EC2.

```text
app-infrastucture/
└── ansible/
    ├── inventory.ini
    ├── playbook.yml
    ├── deploy.yml
    └── secrets.yml
```

## Ansible responsibilities

- Connect to EC2 over SSH
- Install required dependencies
- Configure Docker-related components
- Authenticate with ECR
- Pull the Docker image
- Create the application directory
- Create the environment file
- Run the application container

## Terraform vs Ansible

A key design distinction is:

```text
Terraform
    |
    v
Creates infrastructure

Ansible
    |
    v
Configures the server
and deploys the application
```

Terraform answers:

> What infrastructure should exist?

Ansible answers:

> How should this server and application be configured?

---

# 🔒 Ansible Vault

Sensitive variables are stored in:

```text
secrets.yml
```

and protected with Ansible Vault.

The encrypted file begins with:

```text
$ANSIBLE_VAULT;1.1;AES256
```

Sensitive values must never be committed in plaintext.

Examples of protected variables include:

```text
db_host
db_port
db_name
db_user
db_password
aws_region
s3_bucket
```

---

# 📊 CloudWatch Monitoring

CloudWatch is used to monitor the AWS infrastructure.

## Dashboard

The project dashboard contains:

```text
EC2
├── CPUUtilization
├── NetworkIn
└── NetworkOut

RDS
├── CPUUtilization
└── DatabaseConnections
```

## Alarms

Configured monitoring includes:

```text
EC2 high CPU
RDS low free storage
EC2 status check failure
```

Example project thresholds:

```text
EC2 CPU >= 80%
RDS FreeStorageSpace < 2 GiB
```

These are project/demo thresholds and would need to be tuned for a real production workload.

---

# 📣 SNS Notifications

CloudWatch alarms can publish to an SNS topic.

The project uses the following flow:

```text
CloudWatch
    |
    v
Alarm state changes
    |
    v
SNS Topic
    |
    v
Email notification
```

This demonstrates the difference between:

```text
Monitoring
+
Detection
+
Notification
```

---

# 🚀 Jenkins CI/CD

Jenkins is used to automate the application lifecycle.

The pipeline contains five main stages:

```text
1. Checkout
2. Build Docker Image
3. Test AWS Authentication
4. Push Image to ECR
5. Deploy to EC2
```

---

## Stage 1 — Checkout

Jenkins checks out the source code from:

```text
GitHub
  |
  v
main
```

---

## Stage 2 — Build Docker Image

Jenkins runs:

```bash
docker build -t bilal-store:latest ./bilal-store
```

The Dockerfile builds the application image and installs required Composer dependencies.

---

## Stage 3 — Test AWS Authentication

Jenkins uses its AWS credential:

```text
aws-ecr
```

and runs:

```bash
aws sts get-caller-identity --region ap-south-1
```

This confirms that Jenkins can authenticate to AWS before attempting the ECR push.

---

## Stage 4 — Push Image to ECR

Jenkins:

```text
AWS authentication
       |
       v
ECR login
       |
       v
Create image tag
       |
       v
Push image
```

The image tag is based on the Git commit:

```bash
IMAGE_TAG=$(git rev-parse --short HEAD)
```

Example:

```text
bilal-store:12a7910
```

This is better than relying only on:

```text
bilal-store:latest
```

because the deployed image can be traced back to a specific Git revision.

---

## Stage 5 — Deploy to EC2

Jenkins connects to EC2 using:

```text
jenkins-connection
```

which is an SSH private-key credential.

On EC2:

```text
Login to ECR
     |
     v
docker pull
     |
     v
stop old container
     |
     v
remove old container
     |
     v
start new container
```

The deployment uses the exact Git-tagged image.

---

# 🔑 Jenkins Credential Separation

The project uses different credential types for different jobs.

### AWS credential

```text
ID: aws-ecr
Type: Username with password
Purpose: AWS authentication + ECR push
```

### EC2 SSH credential

```text
ID: jenkins-connection
Type: SSH Username with private key
Username: ubuntu
Purpose: EC2 deployment
```

The pipeline mapping is:

```text
Test AWS Authentication  -> aws-ecr

Push Image to ECR        -> aws-ecr

Deploy to EC2            -> jenkins-connection
```

This separation is important because an AWS credential and an SSH credential are not interchangeable.

---

# 🔁 Automatic Jenkins Trigger

Jenkins is running locally, so GitHub cannot directly call a localhost webhook.

Instead, the project uses:

```text
Poll SCM
H/5 * * * *
```

The tested flow is:

```text
git push
   |
   v
GitHub
   |
   v
Jenkins polling
   |
   v
Pipeline starts
```

This provides automatic CI/CD without exposing the local Jenkins server publicly.

---

# 🏷️ Image Versioning

The project originally used:

```text
latest
```

for the ECR image.

It was improved to use the Git commit SHA as the deployment tag.

Example:

```text
Git commit:
12a7910

ECR:
bilal-store:12a7910

EC2:
runs bilal-store:12a7910
```

This provides basic deployment traceability.

If a problem is discovered, the running image can be associated with the source-code revision that created it.

---

# ✅ What Is Complete?

| Area | Status |
|---|---|
| Legacy PHP application | ✅ |
| Docker containerization | ✅ |
| Docker/Composer dependency build | ✅ |
| Terraform infrastructure | ✅ |
| VPC / networking | ✅ |
| EC2 | ✅ |
| RDS MySQL | ✅ |
| Database migration | ✅ |
| IAM | ✅ |
| ECR | ✅ |
| Ansible configuration | ✅ |
| Ansible Vault | ✅ |
| S3 infrastructure | ✅ |
| EC2 → S3 access test | ✅ |
| CloudWatch dashboard | ✅ |
| CloudWatch alarms | ✅ |
| SNS notifications | ✅ |
| Jenkins Docker build | ✅ |
| Jenkins AWS authentication | ✅ |
| Jenkins ECR push | ✅ |
| Jenkins EC2 deployment | ✅ |
| Automatic Jenkins polling | ✅ |
| Git commit image tagging | ✅ |

## Not implemented

The PHP application's legacy filesystem upload logic was **not** converted to native S3 uploads.

So the accurate S3 claim is:

> S3 infrastructure was provisioned and EC2-to-S3 IAM access was validated.

---

# 🧪 Validation Checklist

Before presenting the project, verify:

### Application

```text
☐ Application loads from EC2 public IP
☐ Login works
☐ Product data is visible
☐ Database-backed functionality works
```

### Docker

```text
☐ Container is running
☐ Application image starts successfully
☐ AWS SDK dependency is present in the image
```

### ECR

```text
☐ Image exists in ECR
☐ Git commit tag exists
☐ EC2 can pull the image
```

### RDS

```text
☐ Database is reachable
☐ Tables exist
☐ Migrated records are present
```

### Ansible

```text
☐ Playbook works
☐ Vault file is encrypted
☐ EC2 configuration is repeatable
```

### CloudWatch

```text
☐ Dashboard works
☐ EC2 CPU alarm exists
☐ RDS storage alarm exists
☐ EC2 status-check alarm exists
☐ SNS subscription is confirmed
```

### Jenkins

```text
☐ Checkout works
☐ Docker build works
☐ AWS authentication works
☐ ECR push works
☐ EC2 deployment works
☐ Automatic polling works
```

---

📸 Screenshots

AWS Infrastructure

![AWS Infrastructure](screenshots/deployment.png)

RDS / Database Migration

![RDS Database](screenshots/rds.png)

ECR Image

![ECR Repository](screenshots/ecr.png)

S3

![S3 Bucket](screenshots/s3.png)

CloudWatch Dashboard

![CloudWatch Dashboard](screenshots/cloudwatch.png)

CloudWatch Alarms / SNS

![CloudWatch Alarms](screenshots/alarm.png)

Jenkins Successful Pipeline

![Jenkins Pipeline](screenshots/pipeline.png)


## Repository Structure

```text
legacy-ecommerce-cloud-modernization/
│
├── Jenkinsfile
├── screenshots/
│       ├── aws-infrastructure.png
│       ├── rds-database.png
│       ├── ecr-repository.png
│       ├── ec2-docker.png
│       ├── s3-bucket.png
│       ├── cloudwatch-dashboard.png
│       ├── cloudwatch-alarms.png
│       ├── jenkins-pipeline.png
│       └── jenkins-auto-trigger.png
│
├── bilal-store/
│   ├── Dockerfile
│   ├── composer.json
│   ├── composer.lock
│   ├── admin/
│   ├── assets/
│   ├── config/
│   └── includes/
│
└── app-infrastucture/
    ├── terraform/
    └── ansible/
