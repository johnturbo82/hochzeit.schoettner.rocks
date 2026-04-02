FROM php:8.2-apache

# Install SQLite PDO extension
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libsqlite3-dev; \
    docker-php-ext-configure pdo_sqlite; \
    docker-php-ext-install pdo_sqlite; \
    rm -rf /var/lib/apt/lists/*

# Enable mod_rewrite and set index preference
RUN set -eux; \
    a2enmod rewrite; \
    echo 'DirectoryIndex index.php index.html' >> /etc/apache2/apache2.conf

# Increase PHP upload limits for multiple mobile photo uploads
RUN set -eux; \
    { \
        echo 'upload_max_filesize=64M'; \
        echo 'post_max_size=1024M'; \
        echo 'max_file_uploads=350'; \
        echo 'max_input_time=900'; \
        echo 'max_execution_time=900'; \
    } > /usr/local/etc/php/conf.d/uploads.ini
