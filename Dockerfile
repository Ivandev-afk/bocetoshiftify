# Imagen de Shiftify para KyraCloud: PHP 8.2 con Apache, escuchando en el puerto 80.
# KyraCloud la construye desde GitHub en cada push a main.
# La conexión a MySQL llega por variables de entorno (ver includes/conexion.php):
# DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT
FROM php:8.2-apache

# mysqli para hablar con MySQL
RUN docker-php-ext-install mysqli

# Hora de Colombia para PHP (las fechas de la fila las calcula MySQL)
RUN echo "date.timezone = America/Bogota" > /usr/local/etc/php/conf.d/zona.ini

# Lo que no debe poder descargarse desde el navegador:
# la conexión, los scripts SQL, la documentación interna y los archivos ocultos
COPY docker/apache-shiftify.conf /etc/apache2/conf-available/shiftify.conf
RUN a2enconf shiftify

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
