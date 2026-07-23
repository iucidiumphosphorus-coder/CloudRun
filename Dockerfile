# ベースイメージ（PHP + Apache）
FROM php:8.2-apache

# 必要な拡張
RUN docker-php-ext-install mysqli

# Cloud SQL Auth Proxy を追加
ADD https://storage.googleapis.com/cloud-sql-connectors/cloud-sql-proxy/v2.10.1/cloud-sql-proxy.linux.amd64 /cloud-sql-proxy
RUN chmod +x /cloud-sql-proxy

# Cloud SQL Proxy を起動（Cloud Run は自動で Unix ソケットを使う）
CMD /cloud-sql-proxy $DB_HOST & apache2-foreground

# アプリ配置
COPY . /var/www/html/
