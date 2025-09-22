FROM composer:1.7.2
  
RUN git clone https://github.com/modmore/Gitify.git /usr/local/lib/gitify && \
    cd /usr/local/lib/gitify && \
    composer install && \
    ln -s /usr/local/lib/gitify/Gitify /usr/local/bin/Gitify && \
    mkdir /gitify
RUN apk --no-cache add mysql-client
RUN docker-php-ext-install pdo_mysql

COPY docker-entrypoint.sh /
ENTRYPOINT ["/docker-entrypoint.sh"]
VOLUME ["/var/www/html", "/gitify"]
WORKDIR /var/www/html
