<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:50',
            'apellidos' => 'required|string|min:2|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:10|confirmed',
            'telefono' => 'nullable|string|max:20', // Hacer telefono opcional
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'nombre' => $request->get('nombre'),
            'apellidos' => $request->get('apellidos'),
            'email' => $request->get('email'),
            'password' => bcrypt($request->get('password')),
            'telefono' => $request->get('telefono', ''), // Valor por defecto si no se proporciona
            'roles_id_rol' => 2, // Asignar rol por defecto (usuario)
        ]);

        // Enviar notificación de bienvenida por correo
        $emailSent = false;
        try {
            $user->notify(new WelcomeUserNotification($user));
            $emailSent = true;
            Log::info("Correo de bienvenida enviado exitosamente a: {$user->email}");
        } catch (\Exception $e) {
            // Log del error pero no fallar el registro
            Log::error('Error enviando email de bienvenida: ' . $e->getMessage());
        }

        $token = JWTAuth::fromUser($user);

        $response = [
            'message' => 'Usuario registrado exitosamente',
            'user' => $user,
            'token' => $token
        ];

        // Agregar información sobre el correo enviado
        if ($emailSent) {
            $response['email_notification'] = "Correo de confirmación enviado a {$user->email}";
        } else {
            $response['email_notification'] = "Usuario registrado, pero no se pudo enviar el correo de confirmación";
        }

        return response()->json($response, 201);
    }

    public function login(Request $request) {
         $validator = Validator::make($request->all(), [
            'email' => 'required|email|min:12|max:100',
            'password' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
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
            $authenticatedUser = Auth::user();

            // Obtener el usuario con la relación del rol usando el primaryKey correcto
            $user = \App\Models\User::where('id_usuario', $authenticatedUser->id_usuario)->with('role')->first();

            return response()->json([
                'success' => true,
                'message' => 'Login exitoso',
                'token' => $token,
                'user' => $user,
                'access_token' => $token, // Algunos frontend esperan access_token
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60
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