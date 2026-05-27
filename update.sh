#!/bin/bash

echo "🔄 Actualizando proyecto..."

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

# Cache
php artisan optimize

echo "✅ Actualización completada."
