#!/bin/bash

echo "🚀 Limpiando variables de entorno para OAuth en producción..."
echo ""

# Limpiar variables de entorno de la sesión actual
echo "🧹 Limpiando variables de entorno conflictivas..."
unset APP_URL
unset FRONTEND_URL
unset GOOGLE_REDIRECT_URI
unset FACEBOOK_REDIRECT_URI

echo "✅ Variables eliminadas de la sesión actual"
echo ""

# Limpiar caché de Laravel
echo "🔄 Limpiando caché de Laravel..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear

echo "✅ Caché limpiada"
echo ""

# Verificar configuración
echo "🔍 Verificando configuración actual..."
php artisan oauth:check

echo ""
echo "⚠️  IMPORTANTE:"
echo "   Si las variables siguen apareciendo incorrectas:"
echo "   1. Busca variables en archivos de perfil (.bashrc, .profile, etc.)"
echo "   2. Revisa el panel de control de tu hosting"
echo "   3. Reinicia el servidor web después de los cambios"
echo ""
echo "📝 Configuración correcta esperada:"
echo "   - APP_URL: https://api.fresaterra.shop"
echo "   - FRONTEND_URL: https://fresaterra.shop"
echo "   - GOOGLE_REDIRECT_URI: https://api.fresaterra.shop/api/v1/auth/google/callback"
