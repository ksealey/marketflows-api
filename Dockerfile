FROM 212127452432.dkr.ecr.us-east-1.amazonaws.com/marketflows-api:production

COPY . /var/www/app

WORKDIR /var/www/app
RUN apt-get update -y && \
    apt-get upgrade -y && \
    apt-get install -y curl && \
    touch .env && \
    echo "APP_KEY=base64:Tno2b+BBVHHbJQ7iX5x6kk+QadbbF1h7tPLi5pLH06U=" > .env && \
    composer install && \
    chown -R www-data:www-data /var/www/app && \
    chmod -R ug+wr /var/www/app