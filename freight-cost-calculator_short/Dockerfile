FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
    && printf 'ServerName localhost\n' >> /etc/apache2/apache2.conf \
    && sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf
COPY app /var/www/html/app
COPY public /var/www/html/public
COPY tests /var/www/html/tests
RUN printf 'display_errors=Off\nlog_errors=On\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/app.ini
