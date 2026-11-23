# Imagen de Shiftify para KyraCloud: PHP 8.2 con Apache, escuchando en el puerto 8080.
#
# KyraCloud no copia el código en la imagen: lo MONTA en la carpeta del WORKDIR
# (/app) y, en cada push a main, sincroniza esa carpeta y reinicia sin volver a
# construir. Por eso Apache sirve directamente desde /app y aquí no hay COPY.
#
# La conexión a MySQL llega por variables de entorno (ver includes/conexion.php):
# DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT
FROM php:8.2-apache

WORKDIR /app

# mysqli para hablar con MySQL
RUN docker-php-ext-install mysqli

# Hora de Colombia para PHP (las fechas de la fila las calcula MySQL)
RUN echo "date.timezone = America/Bogota" > /usr/local/etc/php/conf.d/zona.ini

# Apache: puerto 8080 y carpeta /app en vez de 80 y /var/www/html.
# La configuración propia de Shiftify (qué no se puede descargar, /health) va
# escrita aquí y no en un archivo del repo, porque el repo se monta después.
RUN sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf && \
    sed -i -e 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' \
           -e 's#/var/www/html#/app#' /etc/apache2/sites-available/000-default.conf && \
    echo "ok" > /var/www/health.txt && \
    printf '%s\n' \
      '<Directory /app>' \
      '    Options -Indexes +FollowSymLinks' \
      '    AllowOverride None' \
      '    Require all granted' \
      '</Directory>' \
      '' \
      '# Solo para el servidor: la conexión, los SQL, la documentación y las pruebas' \
      '<DirectoryMatch "^/app/(includes|database|docs|docker|pruebas|\.git)(/|$)">' \
      '    Require all denied' \
      '</DirectoryMatch>' \
      '<FilesMatch "\.(sql|md|ps1|yml)$|^(Dockerfile|\..*)$">' \
      '    Require all denied' \
      '</FilesMatch>' \
      '' \
      'DirectoryIndex index.html index.php' \
      '' \
      '# Chequeo de salud de KyraCloud' \
      'Alias /health /var/www/health.txt' \
      '<Location /health>' \
      '    Require all granted' \
      '</Location>' \
      > /etc/apache2/conf-available/shiftify.conf && \
    a2enconf shiftify

EXPOSE 8080

# apache2-foreground mantiene Apache en primer plano (el CMD de la imagen base)
CMD ["apache2-foreground"]
