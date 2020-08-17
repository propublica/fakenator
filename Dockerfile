# See here for available PHP versions:
# https://hub.docker.com/_/php/
FROM php:7.3-apache

RUN docker-php-ext-install -j$(nproc) mysqli mbstring exif
RUN a2enmod rewrite

# Inside the docker container, stuff inside /var/www/html
# is automatically served by Apache+PHP
WORKDIR /var/www

# Ensure that our code is owned by the right user, to avoid
# permissions errors when Apache+PHP tries to access /var/www/html
RUN usermod -u 1000 www-data \
    && usermod -G staff www-data \
    && mkdir -p /var/www/html \
    && chmod -R 774 /var/www/html

# Copy the code from our local repo into the Docker container
ADD src /var/www/html/
ADD db/createTables.sql /var/www/

ENV DBHOST=db \
	DBUSR=root \
	DBPASS=docker \
	DBSCHEMA=cache \
	DBPORT=3306

EXPOSE 80
ADD ["entrypoint.sh","/"]
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
