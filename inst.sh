#!/bin/bash

# Install packages for the server component
sudo apt-get install python-software-properties
sudo add-apt-repository ppa:nginx/stable
sudo add-apt-repository ppa:ondrej/php5

sudo apt-get update && sudo apt-get upgrade
sudo apt-get install nginx-extras mysql-client mysql-server php5-common php5-fpm php5-dev php5-mysql php5-curl php5-gd php5-intl php-pear php5-imagick php5-imap php5-mcrypt php5-ming php5-ps php5-pspell php5-recode php5-snmp php5-sqlite php5-tidy php5-xsl php5-apcu
sudo apt-get install python-pip

cd web
mkdir -p tmp/cache
chmod 775 tmp
chmod 755 tmp/cache
#mkdir -p tmp/upload
