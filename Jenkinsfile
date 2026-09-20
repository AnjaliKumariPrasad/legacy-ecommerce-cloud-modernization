pipeline {

    agent any

    stages {

        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Build Docker Image') {
            steps {
                sh 'docker build -t bilal-store:latest ./bilal-store'
            }
        }

        stage('Test AWS Authentication') {
          steps {
              withCredentials([
                  usernamePassword(
                      credentialsId: 'aws-ecr',
                      usernameVariable: 'AWS_ACCESS_KEY_ID',
                      passwordVariable: 'AWS_SECRET_ACCESS_KEY'
                  )
              ]) {
                  sh 'aws sts get-caller-identity   --region ap-south-1'
              }
          }
        }

        stage('Push Image to ECR') {
          steps {
              withCredentials([
                  usernamePassword(
                      credentialsId: 'aws-ecr',
                      usernameVariable: 'AWS_ACCESS_KEY_ID',
                      passwordVariable: 'AWS_SECRET_ACCESS_KEY'
                  )
              ]) {
                  sh '''
                      ECR_REGISTRY=016617991046.dkr.ecr.ap-south-1.amazonaws.com
                      ECR_REPOSITORY=bilal-store

                      aws ecr get-login-password --region ap-south-1 |
                      docker login --username AWS --password-stdin $ECR_REGISTRY

                      docker tag bilal-store:latest $ECR_REGISTRY/$ECR_REPOSITORY:latest

                      docker push $ECR_REGISTRY/$ECR_REPOSITORY:latest
                  '''
              }
          }
        }

    }
}