#!/bin/bash

echo "🔧 Configurando OAuth para Google..."

# Limpiar caché de configuración
echo "🧹 Limpiando caché..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Regenerar caché de configuración
echo "♻️ Regenerando caché de configuración..."
php artisan config:cache

# Verificar configuración
echo "🔍 Verificando configuración de Google OAuth..."
php artisan tinker --execute="
echo 'Google Client ID: ' . config('services.google.client_id') . PHP_EOL;
echo 'Google Redirect URI: ' . config('services.google.redirect') . PHP_EOL;
echo 'Frontend URL: ' . env('FRONTEND_URL') . PHP_EOL;
echo 'Google Success Redirect: ' . env('GOOGLE_SUCCESS_REDIRECT') . PHP_EOL;
echo 'Google Error Redirect: ' . env('GOOGLE_ERROR_REDIRECT') . PHP_EOL;
"

echo "✅ Configuración completada"
echo ""
echo "📝 URLs importantes:"
echo "   - OAuth Redirect: https://api.fresaterra.shop/api/v1/auth/google/redirect"
echo "   - OAuth Callback: https://api.fresaterra.shop/api/v1/auth/google/callback"
echo "   - Frontend Success: https://fresaterra.shop/auth/callback"
echo "   - Frontend Error: https://fresaterra.shop/login"
echo ""
echo "🚨 Asegúrate de que estas URLs estén configuradas en Google Console:"
echo "   - Authorized redirect URIs: https://api.fresaterra.shop/api/v1/auth/google/callback"
