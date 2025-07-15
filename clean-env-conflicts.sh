#!/bin/bash

echo "🧹 Limpiando variables de entorno conflictivas..."

# Lista de variables que pueden causar conflictos con el .env
CONFLICTING_VARS=(
    "APP_URL"
    "FRONTEND_URL"
    "GOOGLE_CLIENT_ID"
    "GOOGLE_CLIENT_SECRET"
    "GOOGLE_REDIRECT_URI"
    "FACEBOOK_CLIENT_ID"
    "FACEBOOK_CLIENT_SECRET"
    "FACEBOOK_REDIRECT_URI"
)

echo "Variables eliminadas:"
for var in "${CONFLICTING_VARS[@]}"; do
    if [ -n "${!var}" ]; then
        echo "  - $var: ${!var}"
        unset $var
    fi
done

echo ""
echo "✅ Variables de entorno del sistema limpiadas"
echo "📝 Ahora Laravel usará exclusivamente las variables del archivo .env"

# Limpiar caché de Laravel
cd /home/alex/Escritorio/Proyecto_Fresaterra/Fresaterra_Backend
php artisan config:clear
php artisan cache:clear
php artisan route:clear

echo ""
echo "🔄 Caché de Laravel limpiada"
echo ""
echo "🔍 Ejecuta 'php artisan oauth:check' para verificar la configuración"
