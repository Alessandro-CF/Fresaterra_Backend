<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Registrar un nuevo usuario (endpoint /register)
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:3|max:100',
            'apellidos' => 'required|string|min:3|max:250',
            'email' => 'required|email|unique:users,email',
            'telefono' => 'required|string|min:7|max:15',
            'password' => 'required|string|min:10|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'nombre' => $request->get('nombre'),
            'apellidos' => $request->get('apellidos'),
            'telefono' => $request->get('telefono'),
            'email' => $request->get('email'),
            'password' => bcrypt($request->get('password')),
            'roles_id_rol' => 2, // Siempre asignar rol de Cliente (id 2)
            'estado' => true, // Usuario activo por defecto
        ]);

        // Cargar la relación con el rol
        $user->load('role');

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'user' => $user,
            'token' => $token
        ], 201);
    }

    /**
     * Iniciar sesión (endpoint /login)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|min:12|max:100',
            'password' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'error' => 'Credenciales inválidas'
                ], 401);
            }

            // Obtener el usuario autenticado
            $user = Auth::user();

            // Verificar si la cuenta está activa usando el campo 'estado'
            if (!$user || $user->estado !== true) {
                // Invalidar el token recién creado para mayor seguridad
                try {
                    JWTAuth::invalidate($token);
                } catch (JWTException $e) {
                    // Si no se puede invalidar el token, continuar con la respuesta de error
                    Log::warning('No se pudo invalidar el token para usuario desactivado', [
                        'user_id' => $user ? $user->id_usuario : null,
                        'error' => $e->getMessage()
                    ]);
                }
        
                return response()->json([
                    'error' => 'Tu cuenta ha sido desactivada. Por favor, contacta al soporte para reactivarla.'
                ], 403);
            }

            return response()->json([
                'token' => $token,
                'user' => $user
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'No se pudo crear el token',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Cerrar sesión (endpoint /logout)
     */
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ], 200);
    }

    /**
     * Obtener datos del usuario autenticado (endpoint /me)
     */
    public function me()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $user->load('role');
            
            return response()->json([
                'user' => $user
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        }
    }

    /**
     * Actualizar perfil del usuario (PUT/PATCH /profile)
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            // Validación para actualización de perfil (sin contraseña)
            $validator = Validator::make($request->all(), [
                'nombre' => 'sometimes|required|string|min:3|max:100',
                'apellidos' => 'sometimes|required|string|min:3|max:250',
                'email' => 'sometimes|required|email|unique:users,email,' . $user->id_usuario . ',id_usuario',
                'telefono' => 'sometimes|required|string|min:7|max:15',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            // Solo actualizar los campos enviados
            $updateData = $request->only(['nombre', 'apellidos', 'email', 'telefono']);

            // Filtrar campos vacíos
            $updateData = array_filter($updateData, function ($value) {
                return $value !== null && $value !== '';
            });

            if (empty($updateData)) {
                return response()->json([
                    'error' => 'No se proporcionaron datos para actualizar'
                ], 400);
            }

            // Actualizar usuario
            $user->update($updateData);
            $user->refresh();

            return response()->json([
                'message' => 'Perfil actualizado exitosamente',
                'user' => $user
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar el perfil'
            ], 500);
        }
    }

    /**
     * Cambiar contraseña del usuario (PATCH /me/password)
     */
    public function changePassword(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'password' => 'required|string|min:10|confirmed|different:current_password',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            // Verificar contraseña actual
            if (!password_verify($request->current_password, $user->password)) {
                return response()->json([
                    'error' => ['current_password' => ['La contraseña actual es incorrecta']]
                ], 422);
            }

            // Actualizar contraseña
            $user->update([
                'password' => bcrypt($request->password)
            ]);

            return response()->json([
                'message' => 'Contraseña actualizada exitosamente'
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar la contraseña'
            ], 500);
        }
    }

    /**
     * Solicitar restablecimiento de contraseña (para usuarios no autenticados)
     */
    public function sendPasswordResetEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'error' => 'No se encontró un usuario con ese correo electrónico'
            ], 404);
        }

        // Generar token de restablecimiento
        $token = \Illuminate\Support\Str::random(60);

        // TODO: Implementar envío de email
        // Mail::to($user->email)->send(new PasswordResetMail($token));

        return response()->json([
            'message' => 'Se ha enviado un enlace de restablecimiento a tu correo electrónico',
            'token' => $token // Solo para desarrollo, remover en producción
        ], 200);
    }

    /**
     * Desactivar cuenta del usuario (PATCH /me/deactivate)
     */
    public function deactivateAccount(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            // Verificar si la cuenta ya está desactivada
            if (!$user->isActive()) {
                return response()->json([
                    'error' => 'La cuenta ya está desactivada'
                ], 400);
            }

            // Validar contraseña para confirmar la desactivación
            $validator = Validator::make($request->all(), [
                'password' => 'required|string',
                'confirmation' => 'required|string|in:DESACTIVAR', // Confirmación explícita
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            // Verificar contraseña
            if (!password_verify($request->password, $user->password)) {
                return response()->json([
                    'error' => ['password' => ['La contraseña es incorrecta']]
                ], 422);
            }

            // Desactivar la cuenta
            $user->deactivate();

            // Invalidar todos los tokens del usuario (logout)
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'message' => 'Cuenta desactivada exitosamente. Has sido desconectado.'
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al desactivar la cuenta'
            ], 500);
        }
    }

    /**
     * Obtener datos del usuario autenticado (endpoint /user)
     */
    public function getUser()
    {
        //$user = Auth::user();
        $user = JWTAuth::parseToken()->authenticate();
        return response()->json([
            'user' => $user
        ], 200);
    }

    // * MÉTODOS DE ADMINISTRADOR

    /**
     * Listar usuarios desactivados (solo admin)
     * GET /admin/users/deactivated
     */
    public function getDeactivatedUsers()
    {
        try {
            $deactivatedUsers = User::deactivated()
                ->with('role')
                ->select('id_usuario', 'nombre', 'apellidos', 'email', 'telefono', 'estado', 'created_at', 'roles_id_rol')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'message' => 'Usuarios desactivados obtenidos exitosamente',
                'users' => $deactivatedUsers,
                'total' => $deactivatedUsers->count()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener usuarios desactivados'
            ], 500);
        }
    }

    /**
     * Obtener detalles de un usuario específico (solo admin)
     * GET /admin/users/{id}
     */
    public function getUserDetails($userId)
    {
        try {
            $user = User::with('role')
                ->select('id_usuario', 'nombre', 'apellidos', 'email', 'telefono', 'estado', 'created_at', 'updated_at', 'roles_id_rol')
                ->find($userId);

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            return response()->json([
                'message' => 'Usuario obtenido exitosamente',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener el usuario'
            ], 500);
        }
    }

    /**
     * Reactivar cuenta de usuario (solo admin)
     * PATCH /admin/users/{id}/reactivate
     */
    public function reactivateUser(Request $request, $userId)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            if ($user->isActive()) {
                return response()->json([
                    'error' => 'La cuenta ya está activa'
                ], 400);
            }

            // Validar motivo de reactivación (opcional pero recomendado)
            $validator = Validator::make($request->all(), [
                'reason' => 'sometimes|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            // Reactivar la cuenta
            $user->activate();

            // Log de la acción del administrador
            $admin = JWTAuth::parseToken()->authenticate();
            Log::info('Admin user reactivation', [
                'admin_id' => $admin->id_usuario,
                'admin_email' => $admin->email,
                'reactivated_user_id' => $user->id_usuario,
                'reactivated_user_email' => $user->email,
                'reason' => $request->get('reason', 'No especificado'),
                'timestamp' => now()
            ]);

            return response()->json([
                'message' => 'Cuenta reactivada exitosamente',
                'user' => [
                    'id' => $user->id_usuario,
                    'nombre' => $user->nombre,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email,
                    'estado' => $user->estado,
                    'reactivated_at' => now()
                ]
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al reactivar la cuenta'
            ], 500);
        }
    }

    /**
     * Listar todos los usuarios con filtros (solo admin)
     * GET /admin/users
     */
    public function getAllUsers(Request $request)
    {
        try {
            $query = User::with('role')
                ->select('id_usuario', 'nombre', 'apellidos', 'email', 'telefono', 'estado', 'created_at', 'roles_id_rol');

            // Filtros opcionales
            if ($request->has('estado')) {
                $estado = $request->get('estado') === 'true' || $request->get('estado') === '1';
                $query->where('estado', $estado);
            }

            if ($request->has('role')) {
                $query->where('roles_id_rol', $request->get('role'));
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('apellidos', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'message' => 'Usuarios obtenidos exitosamente',
                'users' => $users,
                'total' => $users->count(),
                'stats' => [
                    'active' => $users->where('estado', true)->count(),
                    'deactivated' => $users->where('estado', false)->count(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener usuarios'
            ], 500);
        }
    }

    /**
     * Dashboard de administrador con estadísticas generales
     * GET /admin/dashboard
     */
    public function adminDashboard()
    {
        try {
            // Estadísticas de usuarios
            $userStats = [
                'total_users' => User::count(),
                'active_users' => User::active()->count(),
                'deactivated_users' => User::deactivated()->count(),
                'new_users_this_month' => User::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'users_by_role' => User::selectRaw('roles_id_rol, count(*) as count')
                    ->with('role:id_rol,nombre')
                    ->groupBy('roles_id_rol')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->role->nombre => $item->count];
                    }),
            ];

            // Usuarios recientes (últimos 10)
            $recentUsers = User::with('role:id_rol,nombre')
                ->select('id_usuario', 'nombre', 'apellidos', 'email', 'estado', 'created_at', 'roles_id_rol')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Actividad reciente de administradores (últimos logs)
            $recentActivity = collect(\Illuminate\Support\Facades\File::get(storage_path('logs/laravel.log')))
                ->explode("\n")
                ->filter(function ($line) {
                    return str_contains($line, 'Admin user reactivation');
                })
                ->take(-5) // Últimas 5 actividades
                ->values();

            return response()->json([
                'message' => 'Dashboard obtenido exitosamente',
                'dashboard' => [
                    'user_statistics' => $userStats,
                    'recent_users' => $recentUsers,
                    'recent_admin_activity' => $recentActivity,
                    'system_status' => [
                        'database_connected' => true,
                        'last_updated' => now(),
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener datos del dashboard',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desactivar usuario como administrador
     * PATCH /admin/users/{id}/deactivate
     */
    public function adminDeactivateUser(Request $request, $userId)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            if (!$user->isActive()) {
                return response()->json([
                    'error' => 'La cuenta ya está desactivada'
                ], 400);
            }

            // Validar que no esté tratando de desactivar a otro administrador
            if ($user->roles_id_rol === 1) {
                return response()->json([
                    'error' => 'No se puede desactivar una cuenta de administrador'
                ], 403);
            }

            // Validar motivo de desactivación
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|min:10|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            // Desactivar la cuenta
            $user->deactivate();

            // Log de la acción del administrador
            $admin = JWTAuth::parseToken()->authenticate();
            Log::info('Admin user deactivation', [
                'admin_id' => $admin->id_usuario,
                'admin_email' => $admin->email,
                'deactivated_user_id' => $user->id_usuario,
                'deactivated_user_email' => $user->email,
                'reason' => $request->get('reason'),
                'timestamp' => now()
            ]);

            return response()->json([
                'message' => 'Cuenta desactivada exitosamente por administrador',
                'user' => [
                    'id' => $user->id_usuario,
                    'nombre' => $user->nombre,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email,
                    'estado' => $user->estado,
                    'deactivated_at' => now(),
                    'deactivated_by' => $admin->email,
                    'reason' => $request->get('reason')
                ]
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al desactivar la cuenta'
            ], 500);
        }
    }

    /**
     * Buscar usuarios con filtros avanzados
     * GET /admin/users/search
     */
    public function searchUsers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'sometimes|string|min:2|max:100',
                'role' => 'sometimes|integer|exists:roles,id_rol',
                'estado' => 'sometimes|boolean',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'per_page' => 'sometimes|integer|min:5|max:100',
                'sort_by' => 'sometimes|string|in:nombre,apellidos,email,created_at',
                'sort_direction' => 'sometimes|string|in:asc,desc',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            $query = User::with('role:id_rol,nombre')
                ->select('id_usuario', 'nombre', 'apellidos', 'email', 'telefono', 'estado', 'created_at', 'updated_at', 'roles_id_rol');

            // Filtro de búsqueda por texto
            if ($request->has('query')) {
                $searchTerm = $request->get('query');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('nombre', 'like', "%{$searchTerm}%")
                        ->orWhere('apellidos', 'like', "%{$searchTerm}%")
                        ->orWhere('email', 'like', "%{$searchTerm}%")
                        ->orWhere('telefono', 'like', "%{$searchTerm}%");
                });
            }

            // Filtro por rol
            if ($request->has('role')) {
                $query->where('roles_id_rol', $request->get('role'));
            }

            // Filtro por estado
            if ($request->has('estado')) {
                $query->where('estado', $request->get('estado'));
            }

            // Filtro por fecha de creación
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->get('date_from'));
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->get('date_to'));
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $users = $query->paginate($perPage);

            return response()->json([
                'message' => 'Búsqueda realizada exitosamente',
                'users' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage(),
                    'has_more_pages' => $users->hasMorePages(),
                ],
                'search_params' => $request->only(['query', 'role', 'estado', 'date_from', 'date_to'])
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error en la búsqueda de usuarios'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas detalladas de usuarios
     * GET /admin/users/statistics
     */
    public function getUserStatistics()
    {
        try {
            $stats = [
                'overview' => [
                    'total_users' => User::count(),
                    'active_users' => User::active()->count(),
                    'deactivated_users' => User::deactivated()->count(),
                ],
                'by_role' => User::selectRaw('r.nombre as role_name, r.id_rol, count(u.id_usuario) as count')
                    ->from('users as u')
                    ->join('roles as r', 'u.roles_id_rol', '=', 'r.id_rol')
                    ->groupBy('r.id_rol', 'r.nombre')
                    ->get(),
                'registrations_by_month' => User::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
                    ->groupByRaw('YEAR(created_at), MONTH(created_at)')
                    ->orderByRaw('YEAR(created_at) DESC, MONTH(created_at) DESC')
                    ->limit(12)
                    ->get(),
                'recent_activity' => [
                    'last_7_days' => User::where('created_at', '>=', now()->subDays(7))->count(),
                    'last_30_days' => User::where('created_at', '>=', now()->subDays(30))->count(),
                    'this_month' => User::whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->count(),
                ],
            ];

            return response()->json([
                'message' => 'Estadísticas obtenidas exitosamente',
                'statistics' => $stats,
                'generated_at' => now()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener estadísticas'
            ], 500);
        }
    }
}
