# docker-compose up -d && docker logs -f scribe_app_1
FROM ubuntu:jammy
ARG DEBIAN_FRONTEND=noninteractive
ENV TZ=Etc/UTC

# APT
RUN apt-get -qq update && apt-get install -qq \
    make \
    curl \
    php \
    php-curl \
    php-xml \
    php-sqlite3 \
    php-bcmath \
    php-curl \
    php-gd \
    php-imagick \
    php-intl \
    php-mbstring \
    php-pdo \
    php-zip \
    php-soap \
    php-pcov \
    git \
    p7zip-full

# Composer Install
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer
