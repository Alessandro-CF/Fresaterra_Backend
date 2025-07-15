#!/bin/bash

echo "🔍 Buscando variables de entorno problemáticas..."
echo ""

# Buscar en archivos de perfil comunes
echo "📁 Buscando en archivos de perfil:"
for file in ~/.bashrc ~/.profile ~/.zshrc ~/.bash_profile /etc/environment; do
    if [ -f "$file" ]; then
        echo "  Revisando: $file"
        if grep -E "(APP_URL|FRONTEND_URL|GOOGLE_REDIRECT_URI)" "$file" 2>/dev/null; then
            echo "    ⚠️  Variables encontradas en $file"
        else
            echo "    ✅ No se encontraron variables en $file"
        fi
    fi
done

echo ""
echo "🌐 Variables de entorno actuales:"
echo "  APP_URL: $APP_URL"
echo "  FRONTEND_URL: $FRONTEND_URL"
echo "  GOOGLE_REDIRECT_URI: $GOOGLE_REDIRECT_URI"

echo ""
echo "🔧 Para limpiar las variables permanentemente:"
echo "1. Edita los archivos donde las encontraste"
echo "2. O ejecuta este comando temporal:"
echo "   unset APP_URL FRONTEND_URL GOOGLE_REDIRECT_URI"
echo "3. Luego reinicia el servidor web"
