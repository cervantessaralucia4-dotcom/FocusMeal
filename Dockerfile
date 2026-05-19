FROM php:8.2-apache

# Instalar extensión MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copiar todo el proyecto
COPY . /var/www/html/

# Permisos correctos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Habilitar mod_rewrite (por si usas rutas amigables)
RUN a2enmod rewrite

EXPOSE 80