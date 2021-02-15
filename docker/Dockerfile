FROM php:7.4-cli

RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev

RUN docker-php-ext-install zip

RUN pecl install xdebug-2.8.1 \
    && docker-php-ext-enable xdebug

# Install composer and add its bin to the PATH.
RUN curl -s http://getcomposer.org/installer | php && \
    echo "export PATH=${PATH}:/var/www/vendor/bin" >> ~/.bashrc && \
    mv composer.phar /usr/local/bin/composer

# Add bash aliases
RUN echo "alias ll='ls --color=auto -al'" >> ~/.bashrc


# Source the bash
RUN . ~/.bashrc

WORKDIR /usr/src/app
