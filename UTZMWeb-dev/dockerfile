# Imagen oficial de PHP con Apache
FROM php:8.2-apache

# Establecer el directorio de trabajo
WORKDIR /var/www/html

# Habilitar mod_rewrite si usas URLs amigables
RUN a2enmod rewrite
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copiar todos los archivos del proyecto al directorio web de Apache
COPY . /var/www/html



# (OPCIONAL) Si usas PostgreSQL, descomenta esto:
 RUN apt-get update && apt-get install -y libpq-dev \
     && docker-php-ext-install pdo pdo_pgsql

# Exponer el puerto 80 dentro del contenedor
EXPOSE 80

