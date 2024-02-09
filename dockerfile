# Use a imagem base do PHP 8.3
FROM php:8.3-fpm-alpine

# Executa um comando no shell durante o processo de construção da imagem
RUN /bin/sh -c set -eux;

RUN apk --no-cache add postgresql-dev\
    && docker-php-ext-install pgsql \
    && rm -rf /var/cache/apk/*

COPY php.ini /usr/local/etc/php/conf.d/php.ini

# Define variáveis de ambiente
ENV PHP_HOME=/usr/local/8.3-fpm-alpine
ENV PATH=$PHP_HOME/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
ENV LANG=C.UTF-8
ENV PHP_VERSION=8.3

# Expõe a porta 8000/tcp do contêiner
EXPOSE 8000/tcp

# Define o comando padrão a ser executado quando o contêiner for iniciado
CMD ["php", "-S", "0.0.0.0:8000"]