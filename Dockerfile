FROM marketflows/ubuntu18-php7:latest

# Copy required extension 
COPY build/php/pcntl.so /usr/lib/php/20190902

# Copy app files
COPY . /var/www/app
COPY build/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY build/laravel-cron /etc/cron.d/laravel-cron
COPY build/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf 
COPY build/laravel-websockets.conf /etc/supervisor/conf.d/laravel-websockets.conf 

# Install dependencies
WORKDIR /var/www/app
RUN composer install && \
    chown -R www-data:www-data /var/www/app && \
    chmod -R ug+wrx /var/www/app

# Setup cron
RUN chmod 0644 /etc/cron.d/laravel-cron && \
    crontab /etc/cron.d/laravel-cron

# Install and setup supervisor to manage queue workers
RUN apt-get update && \
    apt-get install -y supervisor

CMD /bin/bash /var/www/app/scripts/startup.sh


