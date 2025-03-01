FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    tesseract-ocr tesseract-ocr-eng \
    && docker-php-ext-install pdo_mysql

WORKDIR /var/www
COPY . .
RUN composer install
CMD ["php-fpm"]