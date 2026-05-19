FROM php:8.2-apache

# Instalar extensión MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copiar todo el proyecto
COPY . /var/www/focusmeal/

# Apuntar Apache a la carpeta html/ donde está el index.html
RUN sed -i 's|/var/www/html|/var/www/focusmeal/html|g' /etc/apache2/sites-available/000-default.conf

# Permisos correctos
RUN chown -R www-data:www-data /var/www/focusmeal \
    && chmod -R 755 /var/www/focusmeal

# Habilitar mod_rewrite
RUN a2enmod rewrite

EXPOSE 80