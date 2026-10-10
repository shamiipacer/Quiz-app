FROM php:8.4-cli-bookworm
RUN --mount=type=secret,id=system-ca,target=/tmp/system-ca.crt,required=false sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources && if [ -f /tmp/system-ca.crt ]; then cp /tmp/system-ca.crt /usr/local/share/ca-certificates/environment.crt && update-ca-certificates; fi && apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libonig-dev libxml2-dev libsqlite3-dev && docker-php-ext-install pdo_mysql pdo_sqlite mbstring zip dom && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app/backend
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
