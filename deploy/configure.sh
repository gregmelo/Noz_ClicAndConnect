#!/bin/bash
# Configuration Nginx, PHP-FPM, Mercure, MySQL

DOMAIN="nozamberieu.fr"
DB_NAME="nozamberieu_db"
DB_USER="nozamberieu"
DB_PASS=$(openssl rand -base64 24)
MERCURE_SECRET=$(openssl rand -base64 32)

echo "📝 Configuration en cours..."

# ─── 1. Configuration Nginx ──────────────────────────────────────────────────
cat > /etc/nginx/sites-available/nozamberieu << EOF
server {
    listen 80;
    server_name nozamberieu.fr www.nozamberieu.fr;
    root /var/www/nozamberieu/public;
    index index.php;

    # Logs
    access_log /var/log/nginx/nozamberieu.access.log;
    error_log /var/log/nginx/nozamberieu.error.log;

    # Assets statiques - servis directement par Nginx sans toucher PHP
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|svg|webp)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    # Mercure Hub
    location /.well-known/mercure {
        proxy_pass http://127.0.0.1:3000/.well-known/mercure;
        proxy_read_timeout 24h;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Host \$host;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    # Symfony
    location / {
        try_files \$uri /index.php\$is_args\$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_read_timeout 60;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
EOF

ln -sf /etc/nginx/sites-available/nozamberieu /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ─── 2. Configuration PHP-FPM ────────────────────────────────────────────────
cat > /etc/php/8.2/fpm/pool.d/nozamberieu.conf << EOF
[nozamberieu]
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

php_admin_value[opcache.enable] = 1
php_admin_value[opcache.memory_consumption] = 256
php_admin_value[opcache.max_accelerated_files] = 20000
php_admin_value[opcache.validate_timestamps] = 0
EOF

systemctl restart php8.2-fpm

# ─── 3. MySQL ────────────────────────────────────────────────────────────────
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# ─── 4. Redis ────────────────────────────────────────────────────────────────
sed -i 's/# maxmemory-policy noeviction/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf
systemctl restart redis-server

# ─── 5. Service Mercure (systemd) ────────────────────────────────────────────
cat > /etc/systemd/system/mercure.service << EOF
[Unit]
Description=Mercure Hub
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/nozamberieu
Environment=MERCURE_JWT_SECRET=${MERCURE_SECRET}
ExecStart=/usr/local/bin/mercure run --config /var/www/nozamberieu/Caddyfile.prod
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable mercure

# ─── 6. Sauvegarde des credentials ───────────────────────────────────────────
cat > /root/credentials.txt << EOF
=== CREDENTIALS SERVEUR NOZ ===
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
MERCURE_SECRET=${MERCURE_SECRET}
EOF
chmod 600 /root/credentials.txt

echo "✅ Configuration terminée !"
echo "📋 Tes credentials sont dans /root/credentials.txt"
echo "👉 Lance maintenant : bash deploy/deploy.sh"