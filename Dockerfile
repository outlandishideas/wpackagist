FROM php:8.1-apache

ARG env
RUN test -n "$env"

# Install the AWS CLI - needed to load in secrets safely from S3. See https://aws.amazon.com/blogs/security/how-to-manage-secrets-for-amazon-ec2-container-service-based-applications-by-using-amazon-s3-and-docker/
RUN apt-get update -qq && apt-get install -y awscli && \
    rm -rf /var/lib/apt/lists/* /var/cache/apk/*

# Install svn client, a requirement for the current native exec approach; git for
# Composer pulls; libpq-dev for Postgres; libicu-dev for intl; libonig-dev for mbstring.
RUN apt-get update -qq && \
    apt-get install -y git libicu-dev libonig-dev libpq-dev subversion && \
    rm -rf /var/lib/apt/lists/* /var/cache/apk/*

# intl recommended by something in the Doctrine/Symfony stack for improved performance.
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
 && docker-php-ext-install intl mbstring pdo_pgsql

RUN docker-php-ext-enable opcache

RUN pecl install redis && rm -rf /tmp/pear && docker-php-ext-enable redis

# Get latest Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set up virtual host.
COPY config/apache/symfony.conf /etc/apache2/sites-available/
RUN a2enmod rewrite \
 && a2enmod remoteip \
 && a2dissite 000-default \
 && a2ensite symfony \
 && echo ServerName localhost >> /etc/apache2/apache2.conf

COPY . /var/www/html

# Configure PHP to e.g. not hit 128M memory limit.
COPY ./config/php/php.ini /usr/local/etc/php/

# Ensure Apache can run as www-data and still write to these when the Docker build creates them as root.
RUN mkdir /tmp/twig
RUN chmod -R 777 /tmp/twig

RUN APP_ENV=${env} composer install --no-interaction --quiet --optimize-autoloader --no-dev
