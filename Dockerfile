FROM wordpress:6.8.2-php8.3-apache

ARG COMPOSER_VERSION=2.8.12
ARG COMPOSER_SHA256=f446ea719708bb85fcbf4ef18def5d0515f1f9b4d703f6d820c9c1656e10a2f2

RUN apt-get update \
	&& apt-get install --yes --no-install-recommends git unzip zip \
	&& rm -rf /var/lib/apt/lists/*

RUN curl --fail --silent --show-error --location \
	"https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar" \
	--output /usr/local/bin/composer \
	&& echo "${COMPOSER_SHA256}  /usr/local/bin/composer" | sha256sum --check --strict \
	&& chmod +x /usr/local/bin/composer

WORKDIR /plugin
