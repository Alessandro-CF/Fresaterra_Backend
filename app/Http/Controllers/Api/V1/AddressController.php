<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Direccion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /**
     * Obtener todas las direcciones del usuario autenticado
     */
    public function index()
    {
        try {
            $userId = Auth::id();
            
            $direcciones = Direccion::where('usuarios_id_usuario', $userId)
                ->orderByRaw("CASE WHEN predeterminada = 'Si' THEN 0 ELSE 1 END")
                ->orderBy('id_direccion', 'desc')
                ->get();

            // Formatear las direcciones para el frontend
            $formattedAddresses = $direcciones->map(function ($direccion) {
                return [
                    'id' => $direccion->id_direccion,
                    'calle' => $direccion->calle,
                    'numero' => $direccion->numero,
                    'distrito' => $direccion->distrito,
                    'ciudad' => $direccion->ciudad,
                    'referencia' => $direccion->referencia,
                    'predeterminada' => $direccion->predeterminada === 'Si',
                    'isDefault' => $direccion->predeterminada === 'Si',
                    'name' => $direccion->calle . ' ' . $direccion->numero . ', ' . $direccion->distrito,
                    'address' => $direccion->calle . ' ' . $direccion->numero,
                    'state' => $direccion->distrito,
                    'zipCode' => '' // Si tienes código postal en el futuro
                ];
            });

            return response()->json([
                'success' => true,
                'addresses' => $formattedAddresses
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener direcciones del usuario: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las direcciones.'
            ], 500);
        }
    }

    /**
     * Guardar una nueva dirección
     */
    public function store(Request $request)
    {
        try {
            // Validar los datos de entrada
            $validated = $request->validate([
                'calle' => 'required|string|max:255',
                'numero' => 'required|string|max:50',
                'distrito' => 'required|string|max:100',
                'ciudad' => 'required|string|max:100',
                'referencia' => 'nullable|string|max:255',
                'predeterminada' => 'nullable|string|in:si,no'
            ]);

            $userId = Auth::id();
            
            // Si esta dirección se marca como predeterminada, desmarcar las demás
            if (isset($validated['predeterminada']) && $validated['predeterminada'] === 'si') {
                DB::beginTransaction();
                
                // Desmarcar todas las direcciones predeterminadas del usuario
                Direccion::where('usuarios_id_usuario', $userId)
                    ->where('predeterminada', 'Si')
                    ->update(['predeterminada' => 'No']);
                
                $predeterminada = 'Si';
            } else {
                // Verificar si el usuario no tiene ninguna dirección predeterminada
                $hasPredeterminada = Direccion::where('usuarios_id_usuario', $userId)
                    ->where('predeterminada', 'Si')
                    ->exists();
                
                // Si no tiene ninguna dirección predeterminada, hacer esta la predeterminada
                $predeterminada = $hasPredeterminada ? 'No' : 'Si';
            }

            // Crear la nueva dirección
            $direccion = Direccion::create([
                'calle' => $validated['calle'],
                'numero' => $validated['numero'],
                'distrito' => $validated['distrito'],
                'ciudad' => $validated['ciudad'],
                'referencia' => $validated['referencia'] ?? null,
                'predeterminada' => $predeterminada,
                'usuarios_id_usuario' => $userId,
                'envios_id_envio' => null // Nullable según la migración actualizada
            ]);

            if (isset($validated['predeterminada']) && $validated['predeterminada'] === 'si') {
                DB::commit();
            }

            // Formatear la respuesta
            $formattedAddress = [
                'id' => $direccion->id_direccion,
                'calle' => $direccion->calle,
                'numero' => $direccion->numero,
                'distrito' => $direccion->distrito,
                'ciudad' => $direccion->ciudad,
                'referencia' => $direccion->referencia,
                'predeterminada' => $direccion->predeterminada === 'Si',
                'isDefault' => $direccion->predeterminada === 'Si',
                'name' => $direccion->calle . ' ' . $direccion->numero . ', ' . $direccion->distrito,
                'address' => $direccion->calle . ' ' . $direccion->numero,
                'state' => $direccion->distrito,
                'zipCode' => ''
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dirección guardada exitosamente.',
                'address' => $formattedAddress
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            if (isset($validated['predeterminada']) && $validated['predeterminada'] === 'si') {
                DB::rollBack();
            }
            
            Log::error('Error al guardar dirección: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la dirección.'
            ], 500);
        }
    }

    /**
     * Establecer una dirección como predeterminada
     */
    public function setDefault($id)
    {
        try {
            $userId = Auth::id();
            
            // Verificar que la dirección pertenezca al usuario
            $direccion = Direccion::where('id_direccion', $id)
                ->where('usuarios_id_usuario', $userId)
                ->first();

            if (!$direccion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dirección no encontrada.'
                ], 404);
            }

            DB::beginTransaction();

            // Desmarcar todas las direcciones predeterminadas del usuario
            Direccion::where('usuarios_id_usuario', $userId)
                ->where('predeterminada', 'Si')
                ->update(['predeterminada' => 'No']);

            // Marcar la dirección seleccionada como predeterminada
            $direccion->update(['predeterminada' => 'Si']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Dirección establecida como predeterminada.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al establecer dirección predeterminada: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al establecer la dirección como predeterminada.'
            ], 500);
        }
    }

    /**
     * Obtener la dirección predeterminada del usuario
     */
    public function getDefault()
    {
        try {
            $userId = Auth::id();
            
            $direccion = Direccion::where('usuarios_id_usuario', $userId)
                ->where('predeterminada', 'Si')
                ->first();

            if (!$direccion) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes una dirección predeterminada.'
                ], 404);
            }

            // Formatear la respuesta
            $formattedAddress = [
                'id' => $direccion->id_direccion,
                'calle' => $direccion->calle,
                'numero' => $direccion->numero,
                'distrito' => $direccion->distrito,
                'ciudad' => $direccion->ciudad,
                'referencia' => $direccion->referencia,
                'predeterminada' => true,
                'isDefault' => true,
                'name' => $direccion->calle . ' ' . $direccion->numero . ', ' . $direccion->distrito,
                'address' => $direccion->calle . ' ' . $direccion->numero,
                'state' => $direccion->distrito,
                'zipCode' => ''
            ];

            return response()->json([
                'success' => true,
                'address' => $formattedAddress
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener dirección predeterminada: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la dirección predeterminada.'
            ], 500);
        }
    }
}
