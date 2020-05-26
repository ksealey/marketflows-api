FROM marketflows/ubuntu18-php7:latest

# Copy app files
COPY . /var/www/app
COPY .env.prod /var/www/app/.env
COPY laravel-cron /etc/cron.d/laravel-cron
COPY laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf 
COPY laravel-websockets.conf /etc/supervisor/conf.d/laravel-websockets.conf 

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
    apt-get install -y supervisor&& \
    service supervisor start && \
    supervisorctl reread && \
    supervisorctl update && \
    supervisorctl start laravel-worker:* && \
    supervisorctl start laravel-websockets:* && \
    service supervisor stop

CMD service supervisor start && apachectl -D FOREGROUND