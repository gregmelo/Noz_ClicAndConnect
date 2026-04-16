#!/bin/bash
# Script d'installation serveur Hetzner - nozamberieu.fr
# À exécuter en root sur Ubuntu 24.04

set -e
echo "🚀 Installation du serveur Noz ClicAndConnect..."

# ─── 1. Mise à jour système ───────────────────────────────────────────────────
apt update && apt upgrade -y
apt install -y curl wget git unzip zip nginx redis-server supervisor ufw

# ─── 2. PHP 8.2 + extensions ─────────────────────────────────────────────────
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml php8.2-curl \
    php8.2-mbstring php8.2-zip php8.2-intl php8.2-redis php8.2-gd \
    php8.2-opcache php8.2-bcmath

# ─── 3. MySQL ────────────────────────────────────────────────────────────────
apt install -y mysql-server
mysql_secure_installation

# ─── 4. Composer ─────────────────────────────────────────────────────────────
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# ─── 5. Mercure ──────────────────────────────────────────────────────────────
wget https://github.com/dunglas/mercure/releases/latest/download/mercure_Linux_x86_64.tar.gz
tar -xzf mercure_Linux_x86_64.tar.gz
mv mercure /usr/local/bin/mercure
chmod +x /usr/local/bin/mercure
rm mercure_Linux_x86_64.tar.gz

# ─── 6. Dossier application ──────────────────────────────────────────────────
mkdir -p /var/www/nozamberieu
chown -R www-data:www-data /var/www/nozamberieu

# ─── 7. Firewall ─────────────────────────────────────────────────────────────
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

echo "✅ Installation de base terminée !"
echo "👉 Lance maintenant : bash deploy/configure.sh"