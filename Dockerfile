FROM php:7.4-apache

ARG env
RUN test -n "$env"

# Install the AWS CLI - needed to load in secrets safely from S3. See https://aws.amazon.com/blogs/security/how-to-manage-secrets-for-amazon-ec2-container-service-based-applications-by-using-amazon-s3-and-docker/
RUN apt-get update -qq && apt-get install -y python unzip && \
    cd /tmp && \
    curl "https://s3.amazonaws.com/aws-cli/awscli-bundle.zip" -o "awscli-bundle.zip" && \
    unzip awscli-bundle.zip && \
    ./awscli-bundle/install -i /usr/local/aws -b /usr/local/bin/aws && \
    rm awscli-bundle.zip && rm -rf awscli-bundle && \
    rm -rf /var/lib/apt/lists/* /var/cache/apk/*

# Install svn client, a requirement for the current native exec approach, and git to clone
# the Composer performance-helping plugin below. (unzip is needed but installed above.)
# And now libpq-dev for Postgres; libicu-dev for intl.
RUN apt-get update -qq && \
    apt-get install -y git libicu-dev libpq-dev subversion && \
    rm -rf /var/lib/apt/lists/* /var/cache/apk/*

# intl recommended by something in the Doctrine/Symfony stack for improved performance.
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
 && docker-php-ext-install intl pdo_pgsql

# Get latest Composer & parallel install plugin prestissimo.
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer global require hirak/prestissimo

# Set up virtual host.
COPY config/apache/symfony.conf /etc/apache2/sites-available/
RUN a2enmod rewrite \
 && a2enmod remoteip \
 && a2dissite 000-default \
 && a2ensite symfony \
 && echo ServerName localhost >> /etc/apache2/apache2.conf

ADD . /var/www/html

# Configure PHP to e.g. not hit 128M memory limit.
COPY ./config/php/php.ini /usr/local/etc/php/

RUN APP_ENV=${env} composer install --no-interaction --quiet --optimize-autoloader --no-dev
