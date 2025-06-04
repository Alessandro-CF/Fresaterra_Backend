# Sistema de Notificaciones y Emails - Fresaterra API

## ⚡ Actualización Importante

**Sistema de Emails Actualizado**: El sistema ahora utiliza Laravel Notifications (`EmailNotification`) en lugar de Laravel Mailables (`NotificationMail`). Esto proporciona:
- ✅ Mejor integración con el sistema de notificaciones de Laravel
- ✅ Almacenamiento automático en base de datos cuando se configuran múltiples canales
- ✅ Gestión más eficiente de colas y trabajos en segundo plano
- ✅ Compatibilidad mejorada con el modelo `User` que incluye el trait `Notifiable`

## Descripción General

Este sistema combina dos funcionalidades importantes:

1. **Sistema de Notificaciones**: Gestión de notificaciones internas del sistema (almacenadas en base de datos)
2. **Sistema de Emails**: Envío de correos electrónicos a usuarios registrados usando Laravel Notifications

## Endpoints del Sistema de Notificaciones

### 🔐 Endpoints para Usuarios Autenticados

#### 1. Obtener Notificaciones del Usuario
- **Método**: `GET`
- **Endpoint**: `/api/v1/me/notificaciones`
- **Descripción**: Obtiene todas las notificaciones del usuario autenticado
- **Parámetros opcionales**:
  - `unread_only=true`: Solo notificaciones no leídas
  - `type`: Filtrar por tipo de notificación
  - `per_page`: Número de notificaciones por página (default: 20)

**Respuesta exitosa (200)**:
```json
{
  "total": 5,
  "datos": [
    {
      "id_notificacion": 1,
      "estado": "no_leida",
      "fecha_creacion": "2025-05-30T10:00:00Z",
      "mensaje": {
        "tipo": "promocion",
        "contenido": "¡Oferta especial para ti!"
      }
    }
  ]
}
```

#### 2. Actualizar Estado de Notificación
- **Método**: `PATCH`
- **Endpoint**: `/api/v1/me/notificaciones/{id_notificacion}`
- **Body**:
```json
{
  "estado": "leida"  // o "no_leida"
}
```

**Respuesta exitosa (200)**:
```json
{
  "mensaje": "Estado de notificación actualizado",
  "id_notificacion": 1,
  "estado": "leida"
}
```

#### 3. Eliminar Notificación
- **Método**: `DELETE`
- **Endpoint**: `/api/v1/me/notificaciones/{id_notificacion}`

**Respuesta exitosa (200)**:
```json
{
  "mensaje": "Notificación eliminada exitosamente"
}
```

### 👑 Endpoints para Administradores

#### 4. Enviar Notificación (con opción de email)
- **Método**: `POST`
- **Endpoint**: `/api/v1/admin/notificaciones`
- **Body para usuario específico**:
```json
{
  "id_usuario": 101,
  "tipo_mensaje": "promocion",
  "contenido_mensaje": "¡Descuento en frutas!",
  "enviar_email": true,
  "asunto_email": "Oferta especial"
}
```

- **Body para todos los usuarios**:
```json
{
  "todos_los_usuarios": true,
  "tipo_mensaje": "novedad",
  "contenido_mensaje": "¡Nuevos productos en stock!",
  "enviar_email": false
}
```

**Tipos de mensaje válidos**: `general`, `promocion`, `sistema`, `urgente`, `novedad`

**Respuesta exitosa (201)**:
```json
{
  "mensaje": "Notificación(es) enviada(s)",
  "id_notificacion": 201,
  "total_enviadas": 1,
  "emails_enviados": 1
}
```

#### 5. Obtener Todas las Notificaciones
- **Método**: `GET`
- **Endpoint**: `/api/v1/admin/notificaciones`
- **Parámetros opcionales**:
  - `type`: Filtrar por tipo
  - `estado`: `leida` o `no_leida`
  - `user_id`: Filtrar por usuario específico
  - `per_page`: Número por página (default: 50)

**Respuesta exitosa (200)**:
```json
{
  "total": 100,
  "datos": [
    {
      "id_notificacion": 1,
      "estado": "leida",
      "fecha_creacion": "2025-05-30T10:00:00Z",
      "usuario": {
        "id_usuario": 101,
        "nombre": "Juan"
      },
      "mensaje": {
        "tipo": "promocion",
        "contenido": "¡Oferta especial para ti!"
      }
    }
  ]
}
```

#### 6. Eliminar Notificación (Admin)
- **Método**: `DELETE`
- **Endpoint**: `/api/v1/admin/notificaciones/{id_notificacion}`

**Respuesta exitosa (200)**:
```json
{
  "mensaje": "Notificación eliminada permanentemente"
}
```

## Endpoints del Sistema de Emails

### 👑 Endpoints para Administradores (Solo Emails)

#### 1. Enviar Email de Prueba
- **Método**: `POST`
- **Endpoint**: `/api/v1/admin/emails/test`
- **Descripción**: Envía un email de prueba para verificar la configuración

**Respuesta exitosa (200)**:
```json
{
  "success": true,
  "message": "Email de prueba enviado correctamente"
}
```

#### 2. Enviar Email de Confirmación a Usuario Específico
- **Método**: `POST`
- **Endpoint**: `/api/v1/admin/emails/registration-confirmation`
- **Body**:
```json
{
  "user_id": 123
}
```

**Respuesta exitosa (200)**:
```json
{
  "success": true,
  "message": "Correo de confirmación de registro enviado exitosamente",
  "user_email": "usuario@email.com",
  "user_name": "Juan Pérez"
}
```

#### 3. Enviar Emails a Múltiples Usuarios
- **Método**: `POST`
- **Endpoint**: `/api/v1/admin/emails/registration-confirmation/multiple`
- **Body**:
```json
{
  "user_ids": [123, 456, 789]
}
```

**Respuesta exitosa (200/207)**:
```json
{
  "success": true,
  "message": "Proceso completado. 3 correos enviados, 0 errores.",
  "summary": {
    "total_usuarios": 3,
    "enviados_exitosamente": 3,
    "errores": 0
  },
  "details": [
    {
      "user_id": 123,
      "email": "usuario1@email.com",
      "status": "enviado",
      "message": "Correo enviado exitosamente"
    }
  ]
}
```

#### 4. Enviar Emails a Todos los Usuarios
- **Método**: `POST`
- **Endpoint**: `/api/v1/admin/emails/registration-confirmation/all`
- **Descripción**: Envía email de confirmación a todos los usuarios registrados

#### 5. Obtener Lista de Usuarios Registrados
- **Método**: `GET`
- **Endpoint**: `/api/v1/admin/users/registered`

**Respuesta exitosa (200)**:
```json
{
  "success": true,
  "message": "Lista de usuarios obtenida exitosamente",
  "total_usuarios": 150,
  "usuarios": [
    {
      "id_usuario": 1,
      "nombre": "Juan",
      "apellidos": "Pérez",
      "email": "juan@email.com",
      "telefono": "1234567890",
      "created_at": "2025-01-15T10:30:00Z"
    }
  ]
}
```

## Códigos de Respuesta

### Códigos de Éxito
- **200 OK**: Operación exitosa
- **201 Created**: Notificación creada
- **207 Multi-Status**: Algunos emails enviados con errores parciales

### Códigos de Error
- **400 Bad Request**: Datos de entrada inválidos
- **401 Unauthorized**: No autenticado
- **403 Forbidden**: Sin permisos de administrador
- **404 Not Found**: Recurso no encontrado
- **422 Unprocessable Entity**: Errores de validación
- **500 Internal Server Error**: Error del servidor

## Tablas Afectadas

- **notificaciones**: Almacena las notificaciones del sistema
- **mensajes**: Almacena los mensajes de las notificaciones (legacy)
- **users**: Usuarios del sistema

## Middleware Requerido

- **IsUserAuth**: Para endpoints de usuario (`/api/v1/me/*`)
- **IsAdmin**: Para endpoints de administrador (`/api/v1/admin/*`)

## Configuración de Email

Asegúrate de tener configuradas las siguientes variables de entorno en tu `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu-email@gmail.com
MAIL_FROM_NAME="Fresaterra"
FRONTEND_URL=http://localhost:3000
```

## Integración Email + Notificación

Cuando usas el endpoint `POST /api/v1/admin/notificaciones` con el parámetro `enviar_email: true`, el sistema:

1. **Crea la notificación** en la base de datos
2. **Envía el email** al usuario(s) especificado(s)
3. **Retorna información** de ambas operaciones

Esto permite tener un sistema completo donde puedes:
- Notificar dentro de la aplicación
- Notificar por email
- O ambas al mismo tiempo

## Ejemplo de Uso Completo

```javascript
// Enviar notificación con email a un usuario específico
const response = await fetch('/api/v1/admin/notificaciones', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + token
  },
  body: JSON.stringify({
    id_usuario: 123,
    tipo_mensaje: 'promocion',
    contenido_mensaje: '¡Descuento del 20% en frutas frescas!',
    enviar_email: true,
    asunto_email: 'Oferta especial para ti'
  })
});

const data = await response.json();
console.log('Notificación creada:', data.id_notificacion);
console.log('Emails enviados:', data.emails_enviados);
```
