FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo pdo_mysql mysqli zip

# Enable Apache modules
RUN a2enmod rewrite ssl headers

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/logs

# Create logs directory if it doesn't exist
RUN mkdir -p /var/www/html/logs && chown -R www-data:www-data /var/www/html/logs

# Configure Apache for security headers
RUN echo "ServerTokens Prod\nServerSignature Off" >> /etc/apache2/apache2.conf

# Create custom Apache configuration
COPY <<EOF /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/public
    
    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';"
    
    <Directory /var/www/html/public>
        Options -Indexes
        AllowOverride All
        Require all granted
        
        # Prevent access to sensitive files
        <FilesMatch "\.(htaccess|htpasswd|ini|log|sh|inc|bak|config)$">
            Require all denied
        </FilesMatch>
    </Directory>
    
    # Prevent access to config and includes directories
    <Directory /var/www/html/config>
        Require all denied
    </Directory>
    
    <Directory /var/www/html/includes>
        Require all denied
    </Directory>
    
    <Directory /var/www/html/logs>
        Require all denied
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]