FROM php:8.1-cli

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions zip xdebug

# Install composer and add its bin to the PATH.
RUN curl -s http://getcomposer.org/installer | php && \
    echo "export PATH=${PATH}:/var/www/vendor/bin" >> ~/.bashrc && \
    mv composer.phar /usr/local/bin/composer

# Add bash aliases
RUN echo "alias ll='ls --color=auto -al'" >> ~/.bashrc


# Source the bash
RUN . ~/.bashrc

WORKDIR /usr/src/app
