<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;
use Illuminate\Support\Facades\Log; // Para registrar mensajes de error

class MercadoPagoController extends Controller
{
    public function createPreference(Request $request)
    {
        // 1. Establecer el access token desde la configuración de Laravel
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
        $client = new PreferenceClient();

        // 2. Validar que la request contenga un arreglo de 'items'
        // Es crucial validar los datos que vienen del frontend.
        $request->validate([
            'items' => 'required|array', // Esperamos un arreglo de items
            'items.*.title' => 'required|string|max:255', // Cada item debe tener un título
            'items.*.quantity' => 'required|integer|min:1', // Cantidad, mínimo 1
            'items.*.unit_price' => 'required|numeric|min:0.01', // Precio unitario, mínimo 0.01
            'items.*.description' => 'nullable|string|max:255', // Descripción opcional
            // Puedes agregar más validaciones si esperas otros campos como 'id' o 'currency_id'
        ]);

        $itemsForMercadoPago = [];
        // 3. Iterar sobre el arreglo de items para construir el formato que Mercado Pago espera
        foreach ($request->input('items') as $itemData) {
            $item = [
                'title'      => (string) ($itemData['title'] ?? ''),
                'quantity'   => (int) ($itemData['quantity'] ?? 1),
                'unit_price' => (float) ($itemData['unit_price'] ?? 0.01),
            ];
            // Solo agrega description si existe y no está vacía
            if (!empty($itemData['description'])) {
                $item['description'] = (string) $itemData['description'];
            }
            // Solo agrega currency_id si existe y no está vacío
            if (!empty($itemData['currency_id'])) {
                $item['currency_id'] = (string) $itemData['currency_id'];
            } else {
                $item['currency_id'] = 'PEN';
            }
            $itemsForMercadoPago[] = $item;
        }

        // Obtener la URL del frontend desde las variables de entorno
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000'); // Valor por defecto
        Log::debug('Frontend URL para back_urls: ' . $frontendUrl); // <-- NUEVO LOG AÑADIDO

        $backUrls = [
            'success' => $frontendUrl . '/pago-exitoso', // Cambiado a /pago-exitoso para coincidir con el frontend
            'failure' => $frontendUrl . '/pago-fallido', // Cambiado a /pago-fallido
            'pending' => $frontendUrl . '/pago-pendiente', // Cambiado a /pago-pendiente
        ];

        $preferenceRequest = [
            'items'       => $itemsForMercadoPago,
            'back_urls'   => $backUrls,
            //'auto_return' => 'approved', // Re-habilitado
        ];
        try {
            $preference = $client->create($preferenceRequest);
            return response()->json([
                'preference_id'      => $preference->id,
                'init_point'         => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point,
            ]);
        } catch (MPApiException $e) {
            $apiResponse = $e->getApiResponse();
            $statusCode = $apiResponse->getStatusCode();
            $rawResponseContent = $apiResponse->getContent(); // Could be string (JSON) or array

            $errorDetails = null;
            $contentForLog = null; // For storing the version of content we log

            if (is_string($rawResponseContent)) {
                $contentForLog = $rawResponseContent; // Log the raw string
                $decodedDetails = json_decode($rawResponseContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $errorDetails = $decodedDetails;
                } else {
                    // JSON decode failed, use raw string as detail and note the error
                    $errorDetails = [
                        'raw_content' => $rawResponseContent,
                        'decode_error' => json_last_error_msg()
                    ];
                }
            } elseif (is_array($rawResponseContent)) {
                $errorDetails = $rawResponseContent; // It's already an array
                $contentForLog = json_encode($rawResponseContent); // Encode for logging as a string
            } else {
                // Fallback for other unexpected types
                $stringValue = 'Unknown content type';
                try {
                    $stringValue = strval($rawResponseContent);
                } catch (\Throwable $t) { /* Ignore if strval fails */
                }
                $contentForLog = 'Unexpected content type: ' . gettype($rawResponseContent) . '. Value: ' . $stringValue;
                $errorDetails = ['raw_content' => $contentForLog];
            }

            Log::error('Request enviado a Mercado Pago: ' . json_encode($preferenceRequest));
            Log::error('Error de Mercado Pago API al crear preferencia: ' . $e->getMessage(), [
                'status_code' => $statusCode,
                'mercadopago_response_content_logged' => $contentForLog, // Log what was processed
                'mercadopago_error_details_parsed' => $errorDetails // Log the parsed/handled details
            ]);

            return response()->json([
                'error' => 'Error al comunicar con Mercado Pago.',
                'message_from_api' => $e->getMessage(),
                'mercadopago_error_details' => $errorDetails
            ], $statusCode);
        } catch (\Exception $e) { // This was already correct
            Log::error('Error general al crear preferencia de Mercado Pago: ' . $e->getMessage(), [
                'request_data' => json_encode($preferenceRequest)
            ]);
            return response()->json(['error' => 'Error interno del servidor.', 'message' => $e->getMessage()], 500);
        }
    }
}
