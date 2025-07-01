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
            $user = JWTAuth::parseToken()->authenticate(); // Authenticate the user via token

            if (!$user) {
                return response()->json(['error' => 'Usuario no autenticado o no encontrado'], 401);
            }

            // Check if the user has the admin role (roles_id_rol = 1)
            if ($user->roles_id_rol === 1) {
                return $next($request);
            } else {
                return response()->json(['error' => 'Acceso denegado. No eres administrador.'], 403);
            }

        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token ha expirado'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token es inválido'], 401);
        } catch (JWTException $e) {
            // This will catch if the token is not present or malformed
            return response()->json(['error' => 'Token de autorización no encontrado o inválido'], 401);
        }

        return $next($request);
    }
}
