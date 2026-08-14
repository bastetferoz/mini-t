#!/bin/bash
echo "🔄 Actualizando proyecto..."

# Detectar usuario del webserver
WEB_USER=$(ps aux | grep -E "nginx|apache|php-fpm" | grep -v grep | head -1 | awk '{print $1}')
WEB_USER=${WEB_USER:-www-data}
CURRENT_USER=$(whoami)

echo "  → Usuario web: $WEB_USER"
echo "  → Usuario actual: $CURRENT_USER"

# Bajar cambios
git pull

# Dependencias
composer install --no-dev --optimize-autoloader
npm install

# Migraciones y seeds
php artisan migrate --force
php artisan db:seed --force

# Assets
npm run build

# Storage link (si no existe)
if [ ! -L "public/storage" ]; then
    php artisan storage:link
    echo "  → Storage link creado"
fi

# Fix de permisos (storage completo + public/storage)
sudo chown -R $CURRENT_USER:$WEB_USER storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo chmod -R 775 storage/app/public
sudo chmod -R 755 public/storage 2>/dev/null

# Asegurar que las carpetas de invoices existan y sean accesibles
mkdir -p storage/app/public/invoices
sudo chown -R $CURRENT_USER:$WEB_USER storage/app/public/invoices
sudo chmod -R 775 storage/app/public/invoices

# Cache
php artisan optimize

echo "✅ Actualización completada."
