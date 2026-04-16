#!/bin/bash
# Déploiement de l'application Symfony

APP_DIR="/var/www/nozamberieu"
REPO="https://github.com/gregmelo/Noz_ClicAndConnect.git"

echo "📦 Déploiement de l'application..."

# ─── Clone ou pull ────────────────────────────────────────────────────────────
if [ -d "$APP_DIR/.git" ]; then
    cd $APP_DIR
    sudo -u www-data git pull origin master
else
    sudo -u www-data git clone $REPO $APP_DIR
    cd $APP_DIR
fi

# ─── Composer ────────────────────────────────────────────────────────────────
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

# ─── Assets ──────────────────────────────────────────────────────────────────
sudo -u www-data php bin/console asset-map:compile --env=prod

# ─── Cache ───────────────────────────────────────────────────────────────────
sudo -u www-data php bin/console cache:clear --env=prod
sudo -u www-data php bin/console cache:warmup --env=prod

# ─── Migrations ──────────────────────────────────────────────────────────────
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# ─── Permissions ─────────────────────────────────────────────────────────────
chown -R www-data:www-data $APP_DIR
chmod -R 755 $APP_DIR
chmod -R 777 $APP_DIR/var
chmod -R 777 $APP_DIR/public/uploads

# ─── Mercure ─────────────────────────────────────────────────────────────────
systemctl restart mercure

echo "✅ Déploiement terminé !"
echo "🌍 Ton site est disponible sur https://nozamberieu.fr"