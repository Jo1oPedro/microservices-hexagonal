ARG PHP_VERSION
FROM php:${PHP_VERSION} AS app

ARG APP_DIR=/var/www/app
ARG APP_ENV=local
ENV APP_ENV=${APP_ENV}

RUN apt-get update -y && apt-get install -y --no-install-recommends \
    git \
    apt-utils \
    nano \
    zlib1g-dev \
    libzip-dev \
    unzip \
    libpng-dev \
    libpq-dev \
    libxml2-dev \
    libbrotli-dev \
    libssl-dev \
    curl \
  && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
      sockets \
      mysqli \
      pdo \
      pdo_mysql \
      session \
      xml \
      zip \
      iconv \
      simplexml \
      pcntl \
      gd \
      fileinfo

RUN curl -sS https://getcomposer.org/installer | php \
    -- --install-dir=/usr/local/bin --filename=composer

WORKDIR $APP_DIR
RUN chown www-data:www-data $APP_DIR

COPY --chown=www-data:www-data . .

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN rm -rf vendor \
    && composer install --prefer-dist --no-scripts --no-progress --no-interaction \
    $(if [ "$APP_ENV" = "production" ]; then echo "--no-dev"; fi) \
    && composer dump-autoload --optimize