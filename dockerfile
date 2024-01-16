# using php type and version
# linux version debian 12 bookworm
FROM    php:8.1-fpm

LABEL   author="jofer"

ENV     PORT=2022

WORKDIR /usr/phpsocket

COPY    ./examples ./examples
COPY    ./src ./src
# COPY    ./vendor ./vendor
COPY    composer* .

RUN     apt update
RUN     apt-get install wget
RUN     apt-get install -y \zlib1g-dev \libzip-dev \unzip

COPY    php-8.1.26.tar.gz .
# RUN     wget https://www.php.net/distributions/php-8.1.26.tar.gz
RUN     tar xfz php-8.1.26.tar.gz && rm -rf php-8.1.26.tar.gz
WORKDIR /usr/phpsocket/php-8.1.26/ext/pcntl/
RUN     phpize
RUN     ./configure
RUN     make && make install
RUN     rm -rf /usr/phpsocket/php-8.1.26
RUN     mkdir /usr/local/etc/php/mods-available
RUN     sh -c "echo 'extension=pcntl.so' > /usr/local/etc/php/mods-available/pcntl.ini"
RUN     echo 'extension=pcntl' >> /usr/local/etc/php/conf.d/docker-fpm.ini
WORKDIR /usr/phpsocket
RUN     php composer.phar install
# RUN     php ./examples/chat/start.php start

EXPOSE ${PORT}
CMD     ["php-fpm"]