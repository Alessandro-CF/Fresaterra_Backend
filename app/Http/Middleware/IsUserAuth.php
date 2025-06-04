<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class IsUserAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            // Verificar si la cuenta está activa
            if (!$user->isActive()) {
                return response()->json([
                    'error' => 'Cuenta desactivada. Contacta al administrador'
                ], 403);
            }

            // Cargar la relación con el rol para optimizar consultas futuras
            $user->load('role');

        } catch (TokenExpiredException $e) {
            return response()->json([
                'error' => 'Token expirado'
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token ausente'
            ], 401);
        }

        return $next($request);
    }
}
