FROM php:8.2-apache

# Instalar extensión MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copiar todo el proyecto a la carpeta raíz por defecto de Apache
COPY . /var/www/html/

# Permisos correctos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Habilitar mod_rewrite para permitir redirecciones y reglas del archivo .htaccess
RUN a2enmod rewrite

EXPOSE 80