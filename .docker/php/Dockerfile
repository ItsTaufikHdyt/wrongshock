FROM php:8.2-fpm

# Set ARG UID dan GID dengan default value
ARG UID=1000
ARG GID=1000

# Update packages and install dependencies
RUN apt-get update \
  && apt-get install -y build-essential zlib1g-dev default-mysql-client curl gnupg procps vim git unzip libzip-dev libpq-dev \
  && docker-php-ext-install zip pdo_mysql pdo_pgsql pgsql

RUN apt-get install -y libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl

RUN apt-get update \
    && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Instalasi pcntl
RUN docker-php-ext-install pcntl

# Instalasi pcov
RUN pecl install pcov && docker-php-ext-enable pcov

# Node.js, NPM, Yarn
RUN curl -sL https://deb.nodesource.com/setup_20.x | bash -
RUN apt-get install -y nodejs
#RUN npm install npm@latest -g
RUN npm install yarn -g

# Instalasi Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"
RUN mv composer.phar /usr/local/bin/composer

# Set environment variables untuk Composer
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_HOME /composer
ENV PATH $PATH:/composer/vendor/bin
RUN composer config --global process-timeout 3600
RUN composer global require "laravel/installer"

WORKDIR /root
RUN git clone https://github.com/seebi/dircolors-solarized

# Create a non-root user with specified UID/GID if it doesn't match www-data
RUN if [ ${UID} != 33 ] || [ ${GID} != 33 ]; then \
    groupadd -g ${GID} appuser && \
    useradd -u ${UID} -g ${GID} -m -s /bin/bash appuser && \
    mkdir -p /home/appuser/.composer && \
    chown -R ${UID}:${GID} /home/appuser /composer; \
fi

# Expose ports
EXPOSE 5173
EXPOSE 8000   

# Set working directory and permissions
WORKDIR /var/www
# This ensures any existing files get proper ownership
RUN chown -R ${UID}:${GID} /var/www

# Switch to the correct user for subsequent operations
USER ${UID}:${GID}