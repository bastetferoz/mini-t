#!/bin/bash
echo "🔄 Actualizando proyecto..."

# Bajar cambios
git pull

# Fix de permisos (evita errores de escritura en storage/cache)
sudo chown -R ubuntu:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Dependencias
composer install --no-dev --optimize-autoloader
npm install

# Migraciones y seeds
php artisan migrate --force
php artisan db:seed --force

# Assets
npm run build

# Cache
php artisan optimize

echo "✅ Actualización completada."