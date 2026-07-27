# ベースイメージ（PHP + Apache）
FROM php:8.2-apache
# 必要な拡張
RUN docker-php-ext-install mysqli
# Apache を Cloud Run の PORT=8080 に合わせる
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost *:80>/<VirtualHost *:8080>/' /etc/apache2/sites-enabled/000-default.conf
# Cloud SQL Auth Proxy v2
ADD https://storage.googleapis.com/cloud-sql-connectors/cloud-sql-proxy/v2.11.0/cloud-sql-proxy.linux.amd64 /cloud-sql-proxy
RUN chmod +x /cloud-sql-proxy
# Cloud SQL Proxy を起動（ソケット作成を待ってから Apache 起動）
CMD ["/bin/sh","-c","/cloud-sql-proxy --port=3306 $DB_CONNECTION_NAME & sleep 5 && apache2-foreground"]
# アプリ配置
COPY . /var/www/html/
