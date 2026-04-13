FROM php:8.2-apache

# 必要なパッケージと拡張モジュールのインストール
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_sqlite

# Apacheの設定（mod_rewrite有効化など）
RUN a2enmod rewrite

# www-data ユーザーがファイルを書き込めるように所有権を変更（SQLiteキャッシュ等用）
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
