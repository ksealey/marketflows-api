FROM ubuntu:latest

# Set to non-interactive
ENV DEBIAN_FRONTEND=noninteractive

# Update Ubuntu repos
RUN apt-get update && apt-get upgrade -y

# Add php repo
RUN apt-get install -y software-properties-common
RUN add-apt-repository ppa:ondrej/php
RUN apt-get update

# Install PHP 7
RUN apt-get install -y \
  php7.1-cli \
  php7.1

# Install Apache
RUN apt-get install -y \
  apache2 

# Install PHP modules
RUN apt-get install -y \
  php-apcu \
  php-xdebug \
  php7.1-gd \
  php7.1-json \
  php7.1-ldap \
  php7.1-mbstring \
  php7.1-mysql \
  php7.1-pgsql \
  php7.1-sqlite3 \
  php7.1-xml \
  php7.1-xsl \
  php7.1-zip \
  php7.1-pdo \
  php7.1-curl \
  php7.1-gmp \
  php-cassandra

# Install handy utils
RUN apt-get install -y \
  vim \
  composer \
  git 

# Copy apache config 
COPY apache/config/vhost /etc/apache2/sites-available/000-default.conf

# Copy php.ini file
COPY php/config/php.ini /etc/php/7.1/apache2/php.ini
COPY php/config/php.ini /etc/php/7.1/cli/php.ini

# Enable Mod rewrite
RUN a2enmod rewrite
# Enable headers
RUN a2enmod headers

# Expose http
EXPOSE 80

# Expose XDebug
EXPOSE 9000

# Kickstart apache
CMD ["/usr/sbin/apachectl", "-D", "FOREGROUND"]
