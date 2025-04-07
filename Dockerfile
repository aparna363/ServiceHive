# Use the official PHP-Apache base image
FROM php:8.2-apache

# Copy your PHP project files to the container
COPY . /var/www/html/

# (Optional) Enable Apache mod_rewrite if needed
RUN a2enmod rewrite
