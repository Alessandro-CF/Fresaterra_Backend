<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\MercadoPagoController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\DireccionesController;
use App\Http\Controllers\Api\V1\PagosController;
use App\Http\Controllers\Api\V1\PedidoController;
use App\Http\Controllers\Api\V1\ComentariosController;
use App\Http\Controllers\Api\V1\Admin\ProductosController;
use App\Http\Controllers\Api\V1\Admin\CategoriasController;
use App\Http\Controllers\Api\V1\Admin\InventarioController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Http\Controllers\Api\V1\OrderStatusController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsUserAuth;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\Auth\SocialiteController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProfileController;

// Ruta para la página de bienvenida
Route::get('api', [WelcomeController::class, 'index']);

/**//* Rutas con el prefijo /api/v1 */

Route::prefix('v1')->group(function () {
    // * RUTAS PÚBLICAS (Sin autenticación)

    // Autenticación
    Route::controller(AuthController::class)->group(function () {
        Route::post('register', 'register');
        Route::post('login', 'login');
        Route::post('password/email', 'sendPasswordResetEmail');
        // Ruta específica para el login de administradores
        Route::post('admin/login', 'login');
    });

    // Autenticación Social - Movido a web.php
    // Las rutas sociales necesitan sesiones que no están disponibles en el grupo API
    Route::post('auth/{provider}', [SocialiteController::class, 'handleSocialAuth']);

    // Productos públicos (ESTANDARIZADAS EN INGLÉS)
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/search', [ProductController::class, 'search']);
    Route::get('products/featured', [ProductController::class, 'featured']);
    Route::get('products/stats', [ProductController::class, 'stats']);
    Route::get('products/{id}', [ProductController::class, 'show']);
    Route::get('categories', [ProductController::class, 'categories']);

    // Reviews públicas
    Route::get('products/{productId}/reviews', [ComentariosController::class, 'getProductReviews']);

    // * RUTAS PRIVADAS (Requieren autenticación JWT)
    Route::middleware('jwt.auth')->group(function () {

        // Gestión de productos (REQUIERE AUTENTICACIÓN)
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{id}', [ProductController::class, 'update']);
        Route::delete('products/{id}', [ProductController::class, 'destroy']);

        // Gestión de cuenta del usuario
        Route::controller(AuthController::class)->group(function () {
            Route::post('logout', 'logout');            // Cerrar sesión
            Route::get('me', 'me');                     // Obtener datos del usuario
            Route::put('profile', 'updateProfile');     // Actualizar perfil completo
            Route::patch('profile', 'updateProfile');   // Actualizar campos específicos
            Route::patch('me/password', 'changePassword'); // Cambiar contraseña
            Route::patch('me/deactivate', 'deactivateAccount'); // Desactivar cuenta
        });

        // Gestión de direcciones del usuario
        Route::controller(DireccionesController::class)->group(function () {
            Route::get('/me/addresses', 'index');                          // Listar direcciones
            Route::post('addresses', 'store');                         // Crear dirección
            Route::get('addresses/default', 'getDefault');              // Obtener predeterminada
            Route::get('addresses/{id}', 'show');                      // Obtener dirección específica
            Route::put('addresses/{id}', 'update');                    // Actualizar dirección
            Route::patch('addresses/{id}', 'update');                  // Actualizar dirección parcial
            Route::delete('addresses/{id}', 'destroy');                // Eliminar dirección
            Route::patch('addresses/{id}/set-default', 'setAsDefault'); // Establecer como predeterminada
        });

        // Gestión de reseñas del usuario
        Route::controller(ComentariosController::class)->group(function () {
            Route::post('reviews', 'store');                               // Crear nueva reseña
            Route::put('reviews/{id}', 'update');                         // Actualizar reseña
            Route::delete('reviews/{id}', 'destroy');                     // Eliminar reseña
            Route::get('products/{productId}/my-review', 'getUserReview'); // Obtener mi reseña para un producto
        });

        // Gestión del carrito
        Route::controller(CartController::class)->prefix('cart')->group(function () {
            Route::get('/', 'index');           // Obtener carrito actual
            Route::post('/', 'store');          // Agregar producto al carrito
            Route::put('/{id}', 'update');      // Actualizar cantidad
            Route::delete('/{id}', 'destroy');  // Eliminar item del carrito
            Route::delete('/', 'clearAll');     // Vaciar todo el carrito
            Route::post('/checkout', 'checkout'); // Convertir carrito a pedido
        });

        // Pagos (requiere autenticación)
        Route::post('create-preference', [MercadoPagoController::class, 'createPreference']);

        // Gestión de pagos del usuario
        Route::controller(PagosController::class)->prefix('payments')->group(function () {
            Route::get('methods', 'getPaymentMethods');          // Métodos de pago activos
            Route::get('/', 'getUserPayments');                 // Historial de pagos del usuario
            Route::post('/', 'store');                          // Crear nuevo pago
            Route::post('confirm', 'confirmPayment');           // Confirmar pago (webhook MP)
            Route::get('/{id}', 'show');                        // Detalles de pago específico
            Route::patch('/{id}/status', 'updateStatus');       // Actualizar estado de pago
            Route::post('success', 'handlePaymentSuccess');     // Confirmar pago exitoso al regresar de MP
            Route::post('update-status-from-mp', 'updateStatusFromMercadoPago'); // Actualizar estado desde MP
        });

        // Gestión de pedidos del usuario
        Route::controller(PedidoController::class)->prefix('orders')->group(function () {
            Route::get('/', 'index');                           // Listar pedidos del usuario
            Route::post('/', 'store');                          // Crear nuevo pedido
            Route::get('/{id}', 'show');                        // Detalles de pedido específico
            Route::patch('/{id}/cancel', 'cancel');             // Cancelar pedido
            Route::get('/{id}/payment', [PagosController::class, 'getPaymentByOrder']); // Info de pago del pedido
        });
        
        // Gestión de estados de pedidos
        // Gestión de estado de pedidos para ADMIN
        Route::prefix('admin/orders')->group(function () {
            Route::patch('{id}/status', [\App\Http\Controllers\Api\V1\OrderStatusController::class, 'updateStatus']);
        });

        Route::controller(OrderStatusController::class)->prefix('orders')->group(function () {
            Route::get('/{id}/status', 'getStatus');           // Obtener estado de pedido
            Route::patch('/{id}/status', 'updateStatus');      // Actualizar estado de pedido
            Route::post('/{id}/resume', 'resumeOrder');        // Reanudar un pedido abandonado
        });
    });

    // * RUTA ESPECIAL PARA ABANDONO (Sin autenticación estricta)
    Route::post('orders/{id}/mark-abandoned', [OrderStatusController::class, 'markAsAbandoned']);

    // * RUTAS DE ADMINISTRADOR (JWT + Admin)

    Route::middleware(['jwt.auth', 'admin'])->group(function () {

        // Rutas de usuarios (admin) - desde NotificationController
        Route::prefix('admin/users')->group(function () {
            // Obtener lista de usuarios registrados (acceso administrativo)
            Route::get('registered', [NotificacionController::class, 'getRegisteredUsers']);
            // Ruta de prueba para verificar que el grupo funciona
            Route::get('test-users-group', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'Grupo admin/users funcionando',
                    'test' => 'Esta ruta funciona'
                ]);
            });
            // Obtener conteo de usuarios activos
            Route::get('count', [NotificacionController::class, 'getActiveUsersCount']);
            // Debug de usuarios
            Route::get('debug-status', [NotificacionController::class, 'debugUsersStatus']);
        });

        // Gestión de usuarios (admin) - desde AuthController
        Route::controller(AuthController::class)->prefix('admin')->group(function () {
            Route::get('dashboard', 'adminDashboard');                    // Dashboard con estadísticas
            Route::get('users', 'getAllUsers');                           // Listar todos los usuarios con filtros
            Route::get('users/deactivated', 'getDeactivatedUsers');       // Listar usuarios desactivados
            Route::get('users/search', 'searchUsers');                    // Búsqueda avanzada de usuarios
            Route::get('users/statistics', 'getUserStatistics');          // Estadísticas detalladas
            Route::get('users/{id}', 'getUserDetails');                   // Obtener detalles de usuario
            Route::patch('users/{id}/reactivate', 'reactivateUser');      // Reactivar usuario
            Route::patch('users/{id}/deactivate', 'adminDeactivateUser'); // Desactivar usuario como admin
            Route::patch('users/{id}/reset-password', 'adminResetPassword'); // Resetear contraseña como admin
            
            // RUTAS ESPECÍFICAS PARA CLIENTES
            Route::get('clients', 'getClients');                          // Listar solo clientes
            Route::get('clients/statistics', 'getClientStatistics');      // Estadísticas de clientes
        });

        // Gestión de productos (admin)
        Route::controller(ProductosController::class)->prefix('admin')->group(function () {
            Route::get('products', 'index');                           // Listar todos los productos
            Route::post('products', 'store');                          // Crear nuevo producto
            Route::get('products/low-stock', 'lowStock');              // Productos con bajo inventario
            Route::get('products/{id}', 'show');                       // Mostrar producto específico
            Route::put('products/{id}', 'update');                     // Actualizar producto completo
            Route::patch('products/{id}', 'partialUpdate');            // Actualización parcial
            Route::patch('products/{id}/status', 'updateStatus');      // Cambiar estado
            Route::delete('products/{id}', 'destroy');                 // Eliminar producto
        });

        // Gestión de categorías (admin)
        Route::controller(CategoriasController::class)->prefix('admin')->group(function () {
            Route::get('categories', 'index');                         // Listar todas las categorías
            Route::post('categories', 'store');                        // Crear nueva categoría
            Route::get('categories/statistics', 'statistics');         // Estadísticas de categorías
            Route::get('categories/{id}', 'show');                     // Mostrar categoría específica
            Route::put('categories/{id}', 'update');                   // Actualizar categoría completa
            Route::patch('categories/{id}', 'partialUpdate');          // Actualización parcial
            Route::delete('categories/{id}', 'destroy');               // Eliminar categoría
        });

        // Gestión de inventario (admin)
        Route::controller(InventarioController::class)->prefix('admin')->group(function () {
            Route::get('inventory/products', 'getProductsInventory');          // Listar inventario de productos
            Route::get('inventory/products/{id}', 'getProductInventory');      // Detalle inventario producto
            Route::get('inventory/statistics', 'getStatistics');               // Estadísticas de inventario
            Route::post('inventory', 'store');                                 // Crear registro inventario
            Route::patch('inventory/products/{id}/stock', 'updateStock');      // Actualizar stock
            Route::patch('inventory/products/{id}/status', 'updateStatus');    // Cambiar estado inventario
        });

        // Gestión de direcciones (admin)
        Route::controller(DireccionesController::class)->group(function () {
            Route::get('admin/users/{userId}/addresses', 'getUserAddresses');     // Direcciones de un usuario
            Route::get('admin/addresses/statistics', 'getAddressStatistics');     // Estadísticas de direcciones
        });

        // Gestión de pagos (admin)
        Route::controller(PagosController::class)->prefix('admin/payments')->group(function () {
            Route::get('/', 'getAllPayments');                     // Todos los pagos con filtros
            Route::get('/statistics', 'getPaymentStatistics');     // Estadísticas de pagos
            Route::get('/{id}/details', 'adminShowPaymentDetails'); // Detalles completos de un pago específico
        });

        // Gestión de pedidos (admin)
        Route::controller(PedidoController::class)->prefix('admin/orders')->group(function () {
            Route::get('/', 'getAllOrders');                    // Todos los pedidos con filtros
            Route::get('/statistics', 'getOrderStatistics');    // Estadísticas de pedidos
            Route::patch('/{id}/status', 'adminUpdateStatus');  // Actualizar estado como admin
        });
    });

    // Ruta de prueba para verificar autenticación de admin (fuera del grupo admin)
    Route::middleware('jwt.auth')->get('admin/test-auth', function () {
        return response()->json([
            'success' => true,
            'message' => 'Autenticación JWT funcionando',
            'user' => Auth::user(),
            'user_id' => Auth::id(),
            'is_admin' => Auth::user() ? Auth::user()->roles_id_rol === 1 : false
        ]);
    });

    // Ruta de prueba para verificar middleware admin completo
    Route::middleware(['jwt.auth', 'admin'])->get('admin/test-admin-middleware', function () {
        return response()->json([
            'success' => true,
            'message' => 'Middleware admin funcionando',
            'user' => Auth::user(),
            'user_id' => Auth::id(),
            'is_admin' => Auth::user() ? Auth::user()->roles_id_rol === 1 : false
        ]);
    });

    // * WEBHOOKS (Sin autenticación)  
    Route::post('mercadopago/notifications', [MercadoPagoController::class, 'handleWebhook'])
        ->name('mercadopago.notifications');

    // NOTA: Las rutas de carrito están ya definidas arriba dentro del grupo 'middleware jwt.auth'
    // Las rutas duplicadas a continuación están en proceso de deprecación
    // y deberían utilizar las rutas estandarizadas en inglés definidas arriba

    // Rutas para notificaciones de usuario
    Route::middleware('jwt.auth')->group(function () {
        Route::prefix('me/notificaciones')->group(function () {
            Route::get('/', [NotificacionController::class, 'getUserNotifications']);
            Route::get('/unread', [NotificacionController::class, 'getUnreadNotifications']); // Ruta faltante
            Route::delete('/clean-email', [NotificacionController::class, 'cleanEmailNotifications']); // Nueva ruta
            Route::patch('/{id_notificacion}', [NotificacionController::class, 'updateUserNotificationStatus']);
            Route::delete('/{id_notificacion}', [NotificacionController::class, 'deleteUserNotification']);
        });
    });

    // Rutas para notificaciones de administrador
    Route::middleware(['jwt.auth', 'admin'])->group(function () {
        Route::prefix('admin/notificaciones')->group(function () {
            Route::post('/', [NotificacionController::class, 'sendAdminNotification']);
            Route::get('/', [NotificacionController::class, 'getAllAdminNotifications']);
            Route::delete('/{id_notificacion}', [NotificacionController::class, 'deleteAdminNotification']);
        });
    });

    // Rutas API adicionales para NotificationService (protegidas)
    Route::middleware(['jwt.auth', 'admin'])->group(function () {
        Route::prefix('admin/notificaciones')->group(function () {
            Route::post('/send-complete', [NotificacionController::class, 'apiSendCompleteNotification']);
            Route::post('/from-message', [NotificacionController::class, 'apiCreateNotificationFromMessage']);
            Route::post('/notify-multiple', [NotificacionController::class, 'apiNotifyMultipleUsers']);
            Route::post('/notify-by-event', [NotificacionController::class, 'apiNotifyByEvent']);
        });

        // Rutas para servicios específicos de notificación
        Route::prefix('notificaciones')->group(function () {
            Route::post('/enviar-email', [NotificacionController::class, 'enviarEmail']);
            Route::post('/enviar-campanita', [NotificacionController::class, 'enviarCampanita']);
            Route::post('/enviar-campanita-con-email', [NotificacionController::class, 'enviarCampanitaConEmail']);
            Route::post('/enviar-directa', [NotificacionController::class, 'enviarDirecta']);
            Route::put('/marcar-leida/{id}', [NotificacionController::class, 'marcarComoLeida']);
            Route::put('/marcar-todas-leidas', [NotificacionController::class, 'marcarTodasComoLeidas']);
            Route::get('/estadisticas', [NotificacionController::class, 'obtenerEstadisticas']);
            Route::delete('/eliminar/{id}', [NotificacionController::class, 'eliminarNotificacion']);
        });
    });

    // Rutas para confirmaciones de registro por email (solo administradores)
    Route::middleware(['jwt.auth', 'admin'])->group(function () {
        Route::prefix('admin/emails')->group(function () {
            // Envío de emails de confirmación de registro
            Route::post('test', [NotificacionController::class, 'sendTestEmail']);
            Route::post('registration-confirmation', [NotificacionController::class, 'sendRegistrationConfirmation']);
            Route::post('registration-confirmation/multiple', [NotificacionController::class, 'sendMultipleRegistrationConfirmations']);
            Route::post('registration-confirmation/all', [NotificacionController::class, 'sendConfirmationToAllUsers']);
        });
    });
});
