<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Direccion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class DireccionesController extends Controller
{
    /**
     * Obtener todas las direcciones del usuario autenticado
     * GET /me/addresses
     */
    public function index()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $addresses = $user->direcciones()
                ->orderBy('predeterminada', 'desc') // Predeterminada primero
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'message' => 'Direcciones obtenidas exitosamente',
                'addresses' => $addresses,
                'total' => $addresses->count()
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        }
    }

    /**
     * Crear una nueva dirección
     * POST /addresses
     */
    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'calle' => 'required|string|min:5|max:100',
                'numero' => 'required|string|max:10',
                'distrito' => 'required|string|min:3|max:50',
                'ciudad' => 'required|string|min:3|max:50',
                'referencia' => 'nullable|string|max:255',
                'predeterminada' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            // Si se marca como predeterminada, quitar el estado de las otras
            if ($request->get('predeterminada', false)) {
                $user->direcciones()->update(['predeterminada' => 'no']);
            }

            // Si es la primera dirección, hacerla predeterminada automáticamente
            $isFirstAddress = $user->direcciones()->count() === 0;

            $address = Direccion::create([
                'calle' => $request->calle,
                'numero' => $request->numero,
                'distrito' => $request->distrito,
                'ciudad' => $request->ciudad,
                'referencia' => $request->referencia,
                'predeterminada' => ($request->get('predeterminada', false) || $isFirstAddress) ? 'si' : 'no',
                'usuarios_id_usuario' => $user->id_usuario,
                // envios_id_envio será null temporalmente hasta implementar la logica de envios
            ]);

            return response()->json([
                'message' => 'Dirección creada exitosamente',
                'address' => $address
            ], 201);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear la dirección'
            ], 500);
        }
    }

    /**
     * Obtener una dirección específica
     * GET /addresses/{id}
     */
    public function show($addressId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $address = $user->direcciones()->find($addressId);

            if (!$address) {
                return response()->json([
                    'error' => 'Dirección no encontrada'
                ], 404);
            }

            return response()->json([
                'message' => 'Dirección obtenida exitosamente',
                'address' => $address
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        }
    }

    /**
     * Actualizar una dirección
     * PUT/PATCH /addresses/{id}
     */
    public function update(Request $request, $addressId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $address = $user->direcciones()->find($addressId);

            if (!$address) {
                return response()->json([
                    'error' => 'Dirección no encontrada'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'calle' => 'sometimes|required|string|min:5|max:100',
                'numero' => 'sometimes|required|string|max:10',
                'distrito' => 'sometimes|required|string|min:3|max:50',
                'ciudad' => 'sometimes|required|string|min:3|max:50',
                'referencia' => 'nullable|string|max:255',
                'predeterminada' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            // Si se marca como predeterminada, quitar el estado de las otras
            if ($request->get('predeterminada', false)) {
                $user->direcciones()
                    ->where('id_direccion', '!=', $addressId)
                    ->update(['predeterminada' => 'no']);
            }

            // Actualizar solo los campos enviados
            $updateData = $request->only(['calle', 'numero', 'distrito', 'ciudad', 'referencia']);
            
            if ($request->has('predeterminada')) {
                $updateData['predeterminada'] = $request->predeterminada ? 'si' : 'no';
            }

            $address->update($updateData);
            $address->refresh();

            return response()->json([
                'message' => 'Dirección actualizada exitosamente',
                'address' => $address
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar la dirección'
            ], 500);
        }
    }

    /**
     * Eliminar una dirección
     * DELETE /addresses/{id}
     */
    public function destroy($addressId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $address = $user->direcciones()->find($addressId);

            if (!$address) {
                return response()->json([
                    'error' => 'Dirección no encontrada'
                ], 404);
            }

            // Si se elimina la dirección predeterminada, asignar otra como predeterminada
            $wasPredeterminada = $address->predeterminada === 'si';
            
            $address->delete();

            if ($wasPredeterminada) {
                $firstAddress = $user->direcciones()->first();
                if ($firstAddress) {
                    $firstAddress->update(['predeterminada' => 'si']);
                }
            }

            return response()->json([
                'message' => 'Dirección eliminada exitosamente'
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar la dirección'
            ], 500);
        }
    }

    /**
     * Establecer una dirección como predeterminada
     * PATCH /addresses/{id}/set-default
     */
    public function setAsDefault($addressId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $address = $user->direcciones()->find($addressId);

            if (!$address) {
                return response()->json([
                    'error' => 'Dirección no encontrada'
                ], 404);
            }

            // Quitar predeterminada de todas las direcciones
            $user->direcciones()->update(['predeterminada' => 'no']);

            // Establecer como predeterminada
            $address->update(['predeterminada' => 'si']);

            return response()->json([
                'message' => 'Dirección establecida como predeterminada',
                'address' => $address
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al establecer dirección predeterminada'
            ], 500);
        }
    }

    /**
     * Obtener la dirección predeterminada del usuario
     * GET /addresses/default
     */
    public function getDefault()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $defaultAddress = $user->direcciones()
                ->where('predeterminada', 'si')
                ->first();

            if (!$defaultAddress) {
                return response()->json([
                    'message' => 'No hay dirección predeterminada configurada',
                    'address' => null
                ], 200);
            }

            return response()->json([
                'message' => 'Dirección predeterminada obtenida',
                'address' => $defaultAddress
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        }
    }

    // * MÉTODOS PARA ADMINISTRADORES

    /**
     * Obtener todas las direcciones de un usuario específico (Admin)
     * GET /admin/users/{userId}/addresses
     */
    public function getUserAddresses($userId)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            $addresses = $user->direcciones()
                ->orderBy('predeterminada', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'message' => 'Direcciones del usuario obtenidas exitosamente',
                'user' => [
                    'id' => $user->id_usuario,
                    'nombre' => $user->nombre,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email
                ],
                'addresses' => $addresses,
                'total' => $addresses->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener las direcciones del usuario'
            ], 500);
        }
    }

    /**
     * Estadísticas de direcciones (Admin)
     * GET /admin/addresses/statistics
     */
    public function getAddressStatistics()
    {
        try {
            $stats = [
                'total_addresses' => Direccion::count(),
                'users_with_addresses' => User::whereHas('direcciones')->count(),
                'users_without_addresses' => User::whereDoesntHave('direcciones')->count(),
                'most_common_cities' => Direccion::selectRaw('ciudad, count(*) as count')
                    ->groupBy('ciudad')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get(),
                'most_common_districts' => Direccion::selectRaw('distrito, count(*) as count')
                    ->groupBy('distrito')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get(),
                'addresses_per_user' => [
                    'average' => round(Direccion::count() / (User::count() ?: 1), 2),
                    'max' => Direccion::selectRaw('usuarios_id_usuario, count(*) as count')
                        ->groupBy('usuarios_id_usuario')
                        ->orderBy('count', 'desc')
                        ->first()?->count ?? 0
                ]
            ];

            return response()->json([
                'message' => 'Estadísticas de direcciones obtenidas exitosamente',
                'statistics' => $stats,
                'generated_at' => now()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener estadísticas de direcciones'
            ], 500);
        }
    }
}
