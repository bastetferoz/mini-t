#!/bin/bash
echo "🔧 Configurando timeouts para producción..."

# ─── NGINX ───
NGINX_CONF="/etc/nginx/sites-available/default"

# Agregar fastcgi_read_timeout si no existe
if ! grep -q "fastcgi_read_timeout" "$NGINX_CONF"; then
    echo "  → Agregando fastcgi_read_timeout 300s a nginx..."
    sudo sed -i '/fastcgi_pass/a\        fastcgi_read_timeout 300s;' "$NGINX_CONF"
else
    echo "  → Actualizando fastcgi_read_timeout a 300s..."
    sudo sed -i 's/fastcgi_read_timeout.*/fastcgi_read_timeout 300s;/' "$NGINX_CONF"
fi

# Agregar proxy_read_timeout si no existe
if ! grep -q "proxy_read_timeout" "$NGINX_CONF"; then
    sudo sed -i '/fastcgi_read_timeout/a\        proxy_read_timeout 300s;' "$NGINX_CONF"
else
    sudo sed -i 's/proxy_read_timeout.*/proxy_read_timeout 300s;/' "$NGINX_CONF"
fi

# Agregar send_timeout si no existe
if ! grep -q "send_timeout" "$NGINX_CONF"; then
    sudo sed -i '/server_name/a\    send_timeout 300s;' "$NGINX_CONF"
else
    sudo sed -i 's/send_timeout.*/send_timeout 300s;/' "$NGINX_CONF"
fi

# Agregar client_max_body_size si no existe
if ! grep -q "client_max_body_size" "$NGINX_CONF"; then
    sudo sed -i '/server_name/a\    client_max_body_size 20M;' "$NGINX_CONF"
else
    sudo sed -i 's/client_max_body_size.*/client_max_body_size 20M;/' "$NGINX_CONF"
fi

# Verificar config de nginx
echo "  → Verificando configuración nginx..."
sudo nginx -t
if [ $? -ne 0 ]; then
    echo "❌ Error en configuración de nginx. Revisá manualmente."
    exit 1
fi

# ─── PHP-FPM ───
PHP_INI=$(find /etc/php -name "php.ini" -path "*/fpm/*" | head -1)

if [ -n "$PHP_INI" ]; then
    echo "  → Actualizando max_execution_time en $PHP_INI..."
    sudo sed -i 's/^max_execution_time.*/max_execution_time = 300/' "$PHP_INI"
    sudo sed -i 's/^upload_max_filesize.*/upload_max_filesize = 20M/' "$PHP_INI"
    sudo sed -i 's/^post_max_size.*/post_max_size = 25M/' "$PHP_INI"
else
    echo "⚠️  No se encontró php.ini de FPM"
fi

# ─── REINICIAR SERVICIOS ───
echo "  → Reiniciando nginx..."
sudo systemctl reload nginx

echo "  → Reiniciando PHP-FPM..."
sudo systemctl restart php8.5-fpm

echo "✅ Timeouts configurados: 300s (5 minutos)"
echo "   - nginx: fastcgi_read_timeout, proxy_read_timeout, send_timeout"
echo "   - PHP: max_execution_time = 300"
echo "   - Upload: 20MB max"
