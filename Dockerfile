# ============================================================
# ETAPA 1: Compilar los assets del frontend con Node/Vite
# ============================================================
FROM node:22-alpine AS frontend

WORKDIR /app

# Copiamos primero los archivos de dependencias para aprovechar
# la caché de Docker.
COPY package*.json ./

# Instalamos las dependencias exactas definidas en package-lock.json.
RUN npm install

# Copiamos el proyecto completo porque Vite puede necesitar
# resources/, vite.config.js/ts, etc.
COPY . .

# Compilamos CSS y JavaScript para producción.
RUN npm run build


# ============================================================
# ETAPA 2: Aplicación Laravel con PHP + Apache
# ============================================================
FROM php:8.3-apache

WORKDIR /var/www/html

# ------------------------------------------------------------
# Dependencias del sistema necesarias para Laravel y extensiones PHP
# ------------------------------------------------------------
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# ------------------------------------------------------------
# Configuración de GD
# ------------------------------------------------------------
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg

# ------------------------------------------------------------
# Extensiones PHP utilizadas habitualmente por Laravel
# ------------------------------------------------------------
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    bcmath \
    intl \
    zip \
    gd

# ------------------------------------------------------------
# Habilitamos mod_rewrite.
#
# Laravel necesita redirigir las URLs hacia public/index.php.
# ------------------------------------------------------------
RUN a2enmod rewrite

# ------------------------------------------------------------
# Composer
# ------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ------------------------------------------------------------
# Copiamos primero composer.json y composer.lock para poder
# aprovechar mejor la caché de Docker.
# ------------------------------------------------------------
COPY composer.json composer.lock ./

# Instalamos dependencias PHP de producción.
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# ------------------------------------------------------------
# Copiamos el resto de Laravel
# ------------------------------------------------------------
COPY . .

# Copiamos los assets que compiló Vite en la etapa de Node.
COPY --from=frontend /app/public/build ./public/build

# Ejecutamos los scripts de Composer ahora que todo el código
# de Laravel está disponible.
RUN composer dump-autoload --optimize

# ------------------------------------------------------------
# Apache debe servir /public, NO la raíz del proyecto Laravel.
# ------------------------------------------------------------
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf

RUN sed -ri \
    -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# ------------------------------------------------------------
# Laravel necesita permisos de escritura aquí.
# ------------------------------------------------------------
RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

RUN chmod -R 775 \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

# ------------------------------------------------------------
# Render normalmente utiliza PORT=10000.
#
# Dejamos un valor por defecto para permitir ejecutar el
# contenedor también localmente.
# ------------------------------------------------------------
ENV PORT=10000

EXPOSE 10000

# ------------------------------------------------------------
# Al arrancar:
#
# 1. Configuramos Apache para escuchar el puerto asignado.
# 2. Limpiamos cachés antiguas.
# 3. Generamos las cachés de producción.
# 4. Arrancamos Apache.
# ------------------------------------------------------------
CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf && sed -i \"s/:80/:${PORT}/g\" /etc/apache2/sites-available/000-default.conf && php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan config:cache && php artisan route:cache && apache2-foreground"]