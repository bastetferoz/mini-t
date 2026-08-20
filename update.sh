#!/bin/bash
echo "🔄 Actualizando proyecto..."

# Usuario del webserver (forzar www-data para evitar detección fallida)
WEB_USER="www-data"
CURRENT_USER=$(whoami)

echo "  → Usuario web: $WEB_USER"
echo "  → Usuario actual: $CURRENT_USER"

# Fix /tmp (previene error tempnam)
sudo chmod 1777 /tmp 2>/dev/null

# Bajar cambios
git pull

# Dependencias
composer install --no-dev --optimize-autoloader
npm install

# Limpiar cache ANTES de migrar (previene errores de vistas)
php artisan view:clear
php artisan config:clear

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

# Fix de permisos
sudo chown -R $CURRENT_USER:$WEB_USER storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo chmod -R 775 storage/app/public
sudo chmod -R 755 public/storage 2>/dev/null

# Carpeta invoices
mkdir -p storage/app/public/invoices
sudo chown -R $CURRENT_USER:$WEB_USER storage/app/public/invoices
sudo chmod -R 775 storage/app/public/invoices

# Cache (limpiar en vez de compilar — previene error tempnam)
php artisan optimize:clear

# Fix final de permisos (después de optimize:clear que puede crear archivos de cache)
sudo chown -R $CURRENT_USER:$WEB_USER storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Reiniciar PHP-FPM
sudo systemctl restart php8.5-fpm

# Crontab para scheduler (si no existe)
CRON_JOB="* * * * * cd /home/ubuntu/mini-t && php artisan schedule:run >> /dev/null 2>&1"
(crontab -l 2>/dev/null | grep -q "schedule:run") || (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
echo "  → Crontab verificado"

# Queue worker service (si no existe)
if [ ! -f /etc/systemd/system/mini-t-worker.service ]; then
    sudo cp queue-worker.service /etc/systemd/system/mini-t-worker.service
    sudo systemctl daemon-reload
    sudo systemctl enable mini-t-worker
    sudo systemctl start mini-t-worker
    echo "  → Queue worker instalado"
else
    sudo systemctl restart mini-t-worker
    echo "  → Queue worker reiniciado"
fi

echo "✅ Actualización completada."
