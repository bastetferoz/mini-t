#!/bin/bash

echo "🚀 Instalando proyecto..."

# Dependencias
composer install
npm install

# Configuración
cp .env.example .env
php artisan key:generate

echo ""
echo "⚙️  Configurá tu .env con los datos de la base de datos y volvé a correr este script desde el paso de migraciones."
echo "Presioná ENTER cuando estés listo..."
read

# Base de datos
php artisan migrate --seed

# Assets
npm run build

# Permisos
chmod -R 775 storage bootstrap/cache

echo "✅ Instalación completada."
echo "👤 Usuario: admin@admin.com"
echo "🔑 Password: 1234"
