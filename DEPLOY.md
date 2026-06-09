# ProxyFF - VPS Deploy Guide
# ==============================

## Yeu cau VPS:
# - Ubuntu 22.04 (hoac CentOS 7+)
# - RAM 1GB+, public IP
# - Port mo: 80, 443, 8080, 53/UDP

## 1. Upload code
# rsync -avz /path/to/ProxyFF/ root@VPS_IP:/var/www/proxyff/

## 2. Cai dat LAMP
sudo apt update
sudo apt install -y apache2 mysql-server php php-mysql php-curl \
  php-mbstring php-xml php-json libapache2-mod-php

## 3. MySQL
sudo mysql -e "CREATE DATABASE proxy_db"
sudo mysql proxy_db < /var/www/proxyff/schema.sql
# Tao user:
sudo mysql -e "CREATE USER 'proxyff'@'localhost' IDENTIFIED BY 'password_moi'"
sudo mysql -e "GRANT ALL ON proxy_db.* TO 'proxyff'@'localhost'"

## 4. Apache config
sudo cp /var/www/proxyff/proxyff.conf /etc/apache2/sites-available/
sudo a2ensite proxyff.conf
sudo a2enmod rewrite
sudo systemctl restart apache2

## 5. Python proxy server
pip3 install mitmproxy mysql-connector-python
# Chay proxy - thay YOUR_IP bang IP VPS that
mitmdump --listen-port 8080 --listen-host 0.0.0.0 \
  -s _proxy_weapon.py --ssl-insecure --allow-hosts freefiremobile.com &

## 6. Cap nhat BASE_URL trong config.php
# Sua thanh: define('BASE_URL', 'http://YOUR_DOMAIN_OR_IP');

## 7. Password admin mac dinh: admin / admin123
# DOI NGAY!
