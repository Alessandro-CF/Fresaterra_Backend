<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class IsAdmin
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

            // Verificar si el usuario es administrador (rol_id = 1)
            if ($user->roles_id_rol !== 1) {
                return response()->json([
                    'error' => 'Acceso denegado. Se requieren permisos de administrador'
                ], 403);
            }

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
                'error' => 'Token inválido o ausente'
            ], 401);
        }

        return $next($request);
    }
}
