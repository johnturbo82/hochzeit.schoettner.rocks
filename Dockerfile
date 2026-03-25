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
