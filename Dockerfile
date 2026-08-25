FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY docker/start-integration /usr/local/bin/start-integration
RUN chmod +x /usr/local/bin/start-integration

CMD ["start-integration"]
