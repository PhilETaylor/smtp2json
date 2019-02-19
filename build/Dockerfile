FROM alpine:latest

RUN apk update \
    && apk add busybox-extras \
        curl \
        php-cli \
        php-json \
        php-phar \
        php-mbstring \
        php-openssl

# Composer
RUN wget https://raw.githubusercontent.com/composer/getcomposer.org/da7be05fa1c9f68b9609726451d1aaac7dd832cb/web/installer -O - -q | php -- --quiet
RUN mv composer.phar /usr/local/bin/composer

RUN curl -L https://github.com/PhilETaylor/smtp2json/archive/master.zip > /tmp/master.zip
RUN unzip -q -d /tmp /tmp/master.zip
RUN mv /tmp/smtp2json-master /app
RUN cp /app/build/inetd.conf /etc/inetd.conf
RUN cd /app && composer install
RUN chmod +x /app/fake-server.php

CMD /usr/sbin/inetd && tail -f /dev/null