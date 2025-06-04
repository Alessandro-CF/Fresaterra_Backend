<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:100',
            'apellidos' => 'required|string|min:3|max:250',
            'telefono' => 'required|string|min:7|max:15', // AÑADE VALIDACIÓN PARA TELEFONO (ajusta las reglas según necesites)
            'role' => 'required|string|in:admin,client',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:10|confirmed', // Indica que se vuelva a escribir el password
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()
            ], 422);
        }

        // Obtener el nombre del rol desde la solicitud
        $roleNameFromRequest = $request->input('role');
        // Buscar el modelo Rol basado en el nombre
        $roleModel = Rol::where('nombre', $roleNameFromRequest)->first();

        // Si el rol no se encuentra en la base de datos
        if (!$roleModel) {
            // Esto no debería ocurrir si la validación 'in:admin,client' funciona
            // y tu tabla 'roles' tiene estos nombres.
            return response()->json(['error' => 'El rol especificado no es válido o no existe en la base de datos.'], 400);
        }

        $user = User::create([
            'nombre' => $request->get('name'),
            'apellidos' => $request->get('apellidos'),
            'telefono' => $request->get('telefono'),
            //'role' => 2, // Asignar rol por defecto
            'email' => $request->get('email'),
            'password' => bcrypt($request->get('password')),
            'roles_id_rol' => $roleModel->id_rol, // CORRECCIÓN AQUÍ: Usar el ID del rol y la columna correcta
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'user' => $user,
            'token' => $token
        ], 201);
    }

    public function login(Request $request) {
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

            return response()->json([
                'token' => $token,
                'user' => $user
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'No se pudo crear el token', $e
            ], 500);
        }
    }

    public function getUser() {
        //$user = Auth::user();
        $user = JWTAuth::parseToken()->authenticate();
        return response()->json([
            'user' => $user
        ], 200);
    }

    public function logout() {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ], 200);
    }
}
