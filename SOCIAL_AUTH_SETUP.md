# Configuración de Autenticación Social

## Variables de Entorno Requeridas

Añade estas variables a tu archivo `.env`:

```dotenv
# URL del Frontend
FRONTEND_URL=http://localhost:5173

# Google OAuth
GOOGLE_CLIENT_ID=tu_google_client_id
GOOGLE_CLIENT_SECRET=tu_google_client_secret
GOOGLE_REDIRECT_URI="${APP_URL}/api/v1/auth/google/callback"

# Facebook OAuth
FACEBOOK_CLIENT_ID=tu_facebook_client_id
FACEBOOK_CLIENT_SECRET=tu_facebook_client_secret
FACEBOOK_REDIRECT_URI="${APP_URL}/api/v1/auth/facebook/callback"
```

## Cómo obtener las credenciales:

### Google:
1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un nuevo proyecto o selecciona uno existente
3. Habilita la API de Google+ 
4. Ve a "Credenciales" → "Crear credenciales" → "ID de cliente de OAuth 2.0"
5. Configura las URLs de redirección autorizadas: `http://localhost:8000/api/v1/auth/google/callback`

### Facebook:
1. Ve a [Facebook for Developers](https://developers.facebook.com/)
2. Crea una nueva aplicación
3. Ve a "Configuración" → "Básica" para obtener el App ID y App Secret
4. En "Productos" → "Facebook Login" → "Configuración"
5. Añade la URL de redirección: `http://localhost:8000/api/v1/auth/facebook/callback`

## Ejecutar migración:
```bash
php artisan migrate
``` 