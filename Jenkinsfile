pipeline {
    environment {
        AWS_ACCESS_KEY_ID      = credentials('AWS_ACCESS_KEY_ID')
        AWS_SECRET_ACCESS_KEY  The = credentials('AWS_SECRET_ACCESS_KEY')
        AWS_DEFAULT_REGION     = 'us-east-1'
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
                sh './scripts/start-test-db.sh'
                sh './vendor/bin/phpunit'
            }
        }

        stage('Deploy Image') {
            when {
                branch 'master'  
            }
            steps {
                sh './scripts/deploy-image.sh production'
            }
        }
    }
    post {
       
        success {
            cleanWs()
        }
    }
}