<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
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
            //'role' => 'required|string|in:admin,user',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:10|confirmed', // Indica que se vuelva a escribir el password
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->get('name'),
            // 'role' => $request->get('role'),
            'role' => 'user', // Asignar rol por defecto
            'email' => $request->get('email'),
            'password' => bcrypt($request->get('password')),
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
