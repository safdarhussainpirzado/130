# STAGE 1: PHP Dependencies (Composer)
FROM composer:2.7 AS vendor
RUN sed -i 's/dl-cdn.alpinelinux.org/uk.alpinelinux.org/g' /etc/apk/repositories || true
WORKDIR /app
COPY composer.json composer.lock* ./
COPY database/ database/
RUN for i in 1 2 3; do \
        composer install \
            --no-dev \
            --no-interaction \
            --prefer-dist \
            --optimize-autoloader \
            --ignore-platform-reqs \
            --no-scripts \
        && break || { echo "composer attempt $i failed, retrying..."; sleep 15; }; \
    done

# STAGE 2: Frontend Assets (Node)
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN for i in 1 2 3; do \
        npm ci \
        && break || { echo "npm attempt $i failed, retrying..."; sleep 15; }; \
    done
COPY . .
RUN npm run build

# STAGE 3: Extension Builder (Heavy Compilation)
FROM php:8.3.36-fpm-alpine AS builder
RUN sed -i 's/dl-cdn.alpinelinux.org/uk.alpinelinux.org/g' /etc/apk/repositories || true
RUN for i in 1 2 3; do \
        apk add --no-cache \
            icu-dev \
            libzip-dev \
            libpng-dev \
            libjpeg-turbo-dev \
            freetype-dev \
            zip \
            unzip \
            autoconf \
            g++ \
            gcc \
            libc-dev \
            make \
        && break || { echo "apk attempt $i failed, retrying..."; sleep 15; }; \
    done \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql opcache zip pcntl intl gd \
    && pecl install redis \
    && docker-php-ext-enable redis

# STAGE 4: Final Production Runtime
FROM php:8.3.36-fpm-alpine
WORKDIR /var/www/html

# Only install runtime dependencies (no build tools)
RUN for i in 1 2 3; do \
        apk add --no-cache \
            icu-libs \
            libzip \
            libpng \
            libjpeg-turbo \
            freetype \
            zip \
            unzip \
        && break || { echo "apk attempt $i failed, retrying..."; sleep 15; }; \
    done \
    && sed -i 's/listen = 127.0.0.1:9000/listen = 9000/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm.max_children = 5/pm.max_children = 35/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm.start_servers = 2/pm.start_servers = 8/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm.min_spare_servers = 1/pm.min_spare_servers = 4/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm.max_spare_servers = 3/pm.max_spare_servers = 12/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/;pm.max_requests = 500/pm.max_requests = 1000/g' /usr/local/etc/php-fpm.d/www.conf
    
# Copy compiled extensions from builder stage
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# High Performance OPcache & JIT Compiler settings
RUN echo -e "zend_extension=opcache\n\
opcache.enable=1\n\
opcache.enable_cli=1\n\
opcache.jit_buffer_size=100M\n\
opcache.jit=tracing\n\
opcache.memory_consumption=256\n\
opcache.max_accelerated_files=20000\n\
opcache.validate_timestamps=1\n\
opcache.revalidate_freq=2\n\
opcache.interned_strings_buffer=16\n\
opcache.fast_shutdown=1" > /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# Copy NCERT Security Hardening Configuration (NCA-19.211026)
COPY docker/php/security.ini /usr/local/etc/php/conf.d/security.ini

# Copy application files with correct ownership
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor/ vendor/
COPY --from=assets --chown=www-data:www-data /app/public/build public/build
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/build_cache

# Setup the entrypoint sequence
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy RoadRunner binary to PATH
COPY --chown=www-data:www-data rr /usr/local/bin/rr
RUN chmod +x /usr/local/bin/rr 2>/dev/null || true

# User Permissions switch safely
USER www-data
EXPOSE 8000
STOPSIGNAL SIGTERM
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["rr", "serve", "-c", ".rr.yaml"]
