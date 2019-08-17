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
  php7.3-cli \
  php7.3

# Install Apache
RUN apt-get install -y \
  apache2 

# Install PHP modules
RUN apt-get install -y \
  php-apcu \
  php-xdebug \
  php7.3-gd \
  php7.3-json \
  php7.3-ldap \
  php7.3-mbstring \
  php7.3-mysql \
  php7.3-pgsql \
  php7.3-sqlite3 \
  php7.3-xml \
  php7.3-xsl \
  php7.3-zip \
  php7.3-pdo \
  php7.3-curl \
  php7.3-gmp \
  php-cassandra

# Install handy utils
RUN apt-get install -y \
  vim \
  composer \
  git 

# Copy apache config 
COPY apache/config/vhost /etc/apache2/sites-available/000-default.conf

# Copy php.ini file
COPY php/config/php.ini /etc/php/7.3/apache2/php.ini
COPY php/config/php.ini /etc/php/7.3/cli/php.ini

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
