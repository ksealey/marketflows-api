FROM 212127452432.dkr.ecr.us-east-1.amazonaws.com/marketflows-api:latest

COPY . /var/www/app
COPY .env.prod /var/www/app/.env

WORKDIR /var/www/app
RUN apt-get update -y && \
    apt-get upgrade -y && \
    apt-get install -y curl && \
    composer install && \
    chown -R www-data:www-data /var/www/app && \
    chmod -R ug+wr /var/www/app

CMD ["/usr/sbin/apachectl", "-D", "FOREGROUND"]