# Use a imagem base do PHP 8.3
FROM php:8.3-fpm

RUN apt-get update && \
    apt-get install -y libpq-dev nginx && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

RUN rm /etc/nginx/nginx.conf

RUN docker-php-ext-install pgsql

COPY ngphp.conf /etc/nginx/nginx.conf

COPY php.ini /usr/local/etc/php/conf.d/php.ini

WORKDIR /var/www/public/

RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
RUN rm /usr/local/etc/php/php.ini-development

# Define variáveis de ambiente
ENV PHP_HOME=/usr/local/8.3-fpm
ENV PATH=$PHP_HOME/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
ENV LANG=C.UTF-8
ENV PHP_VERSION=8.3

# Define o comando padrão a ser executado quando o contêiner for iniciado
CMD ["sh", "-c", "php-fpm && nginx -g 'daemon off;'"]