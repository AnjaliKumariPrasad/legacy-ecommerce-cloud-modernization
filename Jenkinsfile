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

    }
}