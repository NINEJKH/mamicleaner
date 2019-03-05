FROM php:7.2-alpine

ENTRYPOINT ["/usr/local/bin/mamicleaner"]

COPY mamicleaner.phar /usr/local/bin/mamicleaner
