FROM php:8.1-cli@sha256:76e563191d1ade120313a8736df24154d21da5155c0756f147c0b01bd19d9087 AS php-with-mbstring

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        ca-certificates \
        git \
        unzip \
        libonig-dev \
        libicu-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring intl \
    && rm -rf /var/lib/apt/lists/*

FROM debian:bookworm-slim@sha256:734a72b3d6142a71993b4bc580ca01d8ca687f77a2c422d96a961d3376b179bc AS php-without-mbstring

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        unzip \
        php8.2-cli \
        php8.2-intl \
        php8.2-xml \
        php8.2-zip \
        php8.2-iconv \
    && rm -rf /var/lib/apt/lists/*

RUN if php -m | grep -qi '^mbstring$'; then exit 1; fi

WORKDIR /app
