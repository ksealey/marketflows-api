pipeline {
    environment {
        AWS_ACCESS_KEY_ID = credentials('AWS_ACCESS_KEY_ID')
        AWS_SECRET_ACCESS_KEY = credentials('AWS_SECRET_ACCESS_KEY')
        AWS_DEFAULT_REGION = 'us-east-1'
    }

    agent { 
        docker { 
            image 'marketflows/ubuntu18-php7:latest'
        } 
    }
    
    stages {
        stage('Build') {
            steps {
                sh 'composer install'
            }
        }
        
        stage('Test') {
            when {
                branch 'master'  
            }
            steps {
                sh 'echo "Testing..."'
            }
        }

        stage('Deploy to Staging') {
            when {
                branch 'master'  
            }
            steps {
                sh 'echo "Deploy Staging..."'
            }
        }
    }
    post {
       
        success {
            cleanWs()
        }
    }
}