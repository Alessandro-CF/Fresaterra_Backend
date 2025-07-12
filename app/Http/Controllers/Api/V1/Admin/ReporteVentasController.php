<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reporte;
use App\Models\Pedido;
use App\Models\Envio;
use App\Models\Pago;
use App\Models\Carrito;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReporteVentasController extends Controller
{
    /**
     * Obtener resumen de ventas en un periodo de tiempo
     * GET /api/v1/admin/reportes/ventas-resumen?fecha_inicio=YYYY-MM-DD&fecha_fin=YYYY-MM-DD
     */
    public function ventasResumen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'mensaje' => 'Solicitud incorrecta',
                'errores' => $validator->errors()
            ], 400);
        }

        try {
            $fechaInicio = $request->fecha_inicio;
            $fechaFin = $request->fecha_fin;

            // Total de pedidos en el periodo
            $totalPedidos = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])->count();

            // Total de ventas (sumatoria de pagos aprobados en el periodo)
            $totalVentas = Pago::where('estado_pago', 'aprobado')
                ->whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
                ->sum('monto_pago');

            // Total de envíos realizados en el periodo
            $totalEnvios = Envio::whereBetween('fecha_envio', [$fechaInicio, $fechaFin])->count();

            // Pedidos por estado
            $pedidosPorEstado = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])
                ->selectRaw('estado, COUNT(*) as cantidad')
                ->groupBy('estado')
                ->pluck('cantidad', 'estado');

            // Top 5 productos más vendidos en el periodo
            $topProductos = Pedido::whereBetween('pedidos.fecha_creacion', [$fechaInicio, $fechaFin])
                ->join('pedido_items', 'pedidos.id_pedido', '=', 'pedido_items.pedidos_id_pedido')
                ->join('productos', 'pedido_items.productos_id_producto', '=', 'productos.id_producto')
                ->selectRaw('productos.id_producto, productos.nombre, SUM(pedido_items.cantidad) as total_vendidos')
                ->groupBy('productos.id_producto', 'productos.nombre')
                ->orderByDesc('total_vendidos')
                ->limit(5)
                ->get();

            // Total de carritos abandonados en el periodo
            $carritosAbandonados = Carrito::where('estado', 'abandonado')
                ->whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])
                ->count();

            return response()->json([
                'mensaje' => 'Resumen de ventas obtenido exitosamente',
                'data' => [
                    'total_pedidos' => $totalPedidos,
                    'total_ventas' => $totalVentas,
                    'total_envios' => $totalEnvios,
                    'pedidos_por_estado' => $pedidosPorEstado,
                    'top_productos' => $topProductos,
                    'carritos_abandonados' => $carritosAbandonados
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener resumen de ventas: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Solicitar la generación de un nuevo reporte de ventas (asíncrono)
     * POST /api/v1/admin/reportes
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo' => 'required|in:ventas_mensuales,ventas_diarias,ventas_personalizadas',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'generar_inmediato' => 'sometimes|boolean' // Opción para generar inmediatamente
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensaje' => 'Solicitud incorrecta',
                'errores' => $validator->errors()
            ], 400);
        }

        try {
            $user = Auth::user();
            
            // Crear el reporte en la base de datos
            $reporte = Reporte::create([
                'tipo' => $request->tipo,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'estado' => 'en_proceso',
                'usuarios_id_usuario' => $user->id_usuario ?? $user->id ?? null,
            ]);

            // Si se solicita generación inmediata, procesar el reporte
            if ($request->get('generar_inmediato', false)) {
                return $this->generateReport($reporte);
            }

            return response()->json([
                'mensaje' => 'Generación de reporte solicitada. Se le notificará cuando esté listo.',
                'id_reporte' => $reporte->id_reporte,
                'estado' => $reporte->estado
            ], 202);
        } catch (\Exception $e) {
            Log::error('Error al solicitar generación de reporte: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Generar reporte inmediatamente y guardarlo en Storage
     * GET /api/v1/admin/reports/{id_reporte}/generate
     */
    public function generateReport($reporte = null, $id_reporte = null)
    {
        try {
            // Si viene un ID, buscar el reporte
            if ($id_reporte && !$reporte) {
                $reporte = Reporte::find($id_reporte);
                if (!$reporte) {
                    return response()->json([
                        'mensaje' => 'Reporte no encontrado'
                    ], 404);
                }
            }

            // Obtener los datos del reporte
            $datosReporte = $this->obtenerDatosReporte($reporte->fecha_inicio, $reporte->fecha_fin);

            // Generar el contenido del reporte en formato JSON/CSV/PDF
            $contenidoReporte = $this->generarContenidoReporte($datosReporte, $reporte->tipo);

            // Crear el nombre del archivo
            $nombreArchivo = $this->generarNombreArchivo($reporte);

            // Guardar en Storage
            $rutaArchivo = "reports/ventas/{$nombreArchivo}";
            Storage::disk('public')->put($rutaArchivo, $contenidoReporte);

            // Generar URL pública
            $archivoUrl = asset('storage/' . $rutaArchivo);

            // Actualizar el reporte en la base de datos
            $reporte->update([
                'estado' => 'generado',
                'archivo_url' => $archivoUrl
            ]);

            return response()->json([
                'mensaje' => 'Reporte generado exitosamente',
                'data' => [
                    'id_reporte' => $reporte->id_reporte,
                    'estado' => $reporte->estado,
                    'archivo_url' => $archivoUrl,
                    'nombre_archivo' => $nombreArchivo,
                    'fecha_generacion' => $reporte->updated_at,
                    'datos_reporte' => $datosReporte
                ]
            ], 200);

        } catch (\Exception $e) {
            // En caso de error, marcar el reporte como error
            if ($reporte) {
                $reporte->update(['estado' => 'error']);
            }
            
            Log::error('Error al generar reporte: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al generar el reporte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos para el reporte
     */
    private function obtenerDatosReporte($fechaInicio, $fechaFin)
    {
        // Total de pedidos en el periodo
        $totalPedidos = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])->count();

        // Total de ventas (sumatoria de pagos aprobados en el periodo)
        $totalVentas = Pago::where('estado_pago', 'aprobado')
            ->whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
            ->sum('monto_pago');

        // Total de envíos realizados en el periodo
        $totalEnvios = Envio::whereBetween('fecha_envio', [$fechaInicio, $fechaFin])->count();

        // Pedidos por estado
        $pedidosPorEstado = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])
            ->selectRaw('estado, COUNT(*) as cantidad')
            ->groupBy('estado')
            ->pluck('cantidad', 'estado');

        // Top productos más vendidos
        $topProductos = Pedido::whereBetween('pedidos.fecha_creacion', [$fechaInicio, $fechaFin])
            ->join('pedido_items', 'pedidos.id_pedido', '=', 'pedido_items.pedidos_id_pedido')
            ->join('productos', 'pedido_items.productos_id_producto', '=', 'productos.id_producto')
            ->selectRaw('productos.id_producto, productos.nombre, SUM(pedido_items.cantidad) as total_vendidos, SUM(pedido_items.cantidad * productos.precio) as ingresos_generados')
            ->groupBy('productos.id_producto', 'productos.nombre')
            ->orderByDesc('total_vendidos')
            ->limit(10)
            ->get();

        // Ventas por día
        $ventasPorDia = Pago::where('estado_pago', 'aprobado')
            ->whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
            ->selectRaw('DATE(fecha_pago) as fecha, SUM(monto_pago) as total_ventas, COUNT(*) as cantidad_transacciones')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        return [
            'periodo' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin
            ],
            'resumen' => [
                'total_pedidos' => $totalPedidos,
                'total_ventas' => $totalVentas,
                'total_envios' => $totalEnvios,
                'promedio_venta_diaria' => $ventasPorDia->count() > 0 ? round($totalVentas / $ventasPorDia->count(), 2) : 0
            ],
            'pedidos_por_estado' => $pedidosPorEstado,
            'top_productos' => $topProductos,
            'ventas_por_dia' => $ventasPorDia
        ];
    }

    /**
     * Generar contenido del reporte en formato JSON
     */
    private function generarContenidoReporte($datos, $tipo)
    {
        $reporte = [
            'metadata' => [
                'tipo_reporte' => $tipo,
                'fecha_generacion' => now()->toISOString(),
                'periodo' => $datos['periodo']
            ],
            'datos' => $datos
        ];

        return json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generar nombre único para el archivo
     */
    private function generarNombreArchivo($reporte)
    {
        $fecha = now()->format('Y-m-d_H-i-s');
        $tipo = str_replace('_', '-', $reporte->tipo);
        $periodo = $reporte->fecha_inicio . '_' . $reporte->fecha_fin;
        
        return "reporte-{$tipo}_{$periodo}_{$fecha}_{$reporte->id_reporte}.json";
    }

    /**
     * Descargar reporte generado
     * GET /api/v1/admin/reports/{id_reporte}/download
     */
    public function downloadReport($id_reporte)
    {
        try {
            $reporte = Reporte::find($id_reporte);
            if (!$reporte) {
                return response()->json([
                    'mensaje' => 'Reporte no encontrado'
                ], 404);
            }

            if ($reporte->estado !== 'generado' || !$reporte->archivo_url) {
                return response()->json([
                    'mensaje' => 'El reporte no está disponible para descarga'
                ], 409);
            }

            // Extraer la ruta del archivo desde la URL
            $rutaArchivo = str_replace(asset('storage/'), '', $reporte->archivo_url);
            
            if (!Storage::disk('public')->exists($rutaArchivo)) {
                return response()->json([
                    'mensaje' => 'El archivo del reporte no existe'
                ], 404);
            }

            $contenido = Storage::disk('public')->get($rutaArchivo);
            $nombreArchivo = basename($rutaArchivo);
            
            return response($contenido)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"');

        } catch (\Exception $e) {
            Log::error('Error al descargar reporte: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al descargar el reporte'
            ], 500);
        }
    }

    /**
     * Obtener la lista de reportes generados (historial)
     * GET /api/v1/admin/reportes
     */
    public function index(Request $request)
    {
        try {
            // El usuario autenticado y el rol admin ya están validados por middleware
            $reportes = Reporte::with('usuario')
                ->orderByDesc('fecha_creacion')
                ->get();

            $datos = $reportes->map(function ($reporte) {
                return [
                    'id_reporte' => $reporte->id_reporte,
                    'tipo' => $reporte->tipo,
                    'fecha_creacion' => $reporte->fecha_creacion,
                    'estado' => $reporte->estado,
                    'usuario_generador' => $reporte->usuario ? $reporte->usuario->nombre : null
                ];
            });

            return response()->json([
                'total' => $reportes->count(),
                'datos' => $datos
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener historial de reportes: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener detalles de un reporte específico y su URL de descarga
     * GET /api/v1/admin/reportes/{id_reporte}
     */
    public function show($id_reporte)
    {
        try {
            // El usuario autenticado y el rol admin ya están validados por middleware
            $reporte = Reporte::with('usuario')->find($id_reporte);
            if (!$reporte) {
                return response()->json([
                    'mensaje' => 'Reporte no encontrado'
                ], 404);
            }

            if ($reporte->estado === 'en_proceso') {
                return response()->json([
                    'mensaje' => 'El reporte aún está en proceso'
                ], 409);
            }

            $archivoUrl = $reporte->archivo_url ?? null;

            return response()->json([
                'id_reporte' => $reporte->id_reporte,
                'tipo' => $reporte->tipo,
                'fecha_creacion' => $reporte->fecha_creacion,
                'estado' => $reporte->estado,
                'archivo_url' => $archivoUrl,
                'usuario_generador' => [
                    'id_usuario' => $reporte->usuario ? $reporte->usuario->id_usuario : null,
                    'nombre' => $reporte->usuario ? $reporte->usuario->nombre : null
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener detalles de reporte: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Eliminar un reporte generado
     * DELETE /api/v1/admin/reportes/{id_reporte}
     */
    public function destroy($id_reporte)
    {
        try {
            // El usuario autenticado y el rol admin ya están validados por middleware
            $reporte = Reporte::find($id_reporte);
            if (!$reporte) {
                return response()->json([
                    'mensaje' => 'Reporte no encontrado'
                ], 404);
            }

            // Guardar información del reporte antes de eliminarlo
            $tipoReporte = $reporte->tipo;
            $fechaCreacion = $reporte->fecha_creacion;
            
            // Opcional: Eliminar también el archivo del storage si existe
            if ($reporte->archivo_url) {
                $rutaArchivo = str_replace(asset('storage/'), '', $reporte->archivo_url);
                if (Storage::disk('public')->exists($rutaArchivo)) {
                    Storage::disk('public')->delete($rutaArchivo);
                }
            }

            $reporte->delete();
            
            return response()->json([
                'mensaje' => 'Reporte eliminado exitosamente',
                'data' => [
                    'id_reporte' => $id_reporte,
                    'tipo' => $tipoReporte,
                    'fecha_creacion' => $fechaCreacion,
                    'fecha_eliminacion' => now()->toISOString()
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar reporte: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Cambiar el estado de un reporte
     * PATCH /api/v1/admin/reports/{id_reporte}/status
     */
    public function updateStatus(Request $request, $id_reporte)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:en_proceso,generado,error,cancelado'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensaje' => 'Error de validación',
                'errores' => $validator->errors()
            ], 422);
        }

        try {
            $reporte = Reporte::find($id_reporte);
            if (!$reporte) {
                return response()->json([
                    'mensaje' => 'Reporte no encontrado'
                ], 404);
            }

            $estadoAnterior = $reporte->estado;
            $reporte->estado = $request->estado;
            $reporte->save();

            return response()->json([
                'mensaje' => 'Estado del reporte actualizado exitosamente',
                'data' => [
                    'id_reporte' => $reporte->id_reporte,
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => $reporte->estado,
                    'fecha_actualizacion' => $reporte->updated_at
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de reporte: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Marcar un reporte como completado y asignar URL de archivo
     * PATCH /api/v1/admin/reports/{id_reporte}/complete
     */
    public function markAsCompleted(Request $request, $id_reporte)
    {
        $validator = Validator::make($request->all(), [
            'archivo_url' => 'required|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensaje' => 'Error de validación',
                'errores' => $validator->errors()
            ], 422);
        }

        try {
            $reporte = Reporte::find($id_reporte);
            if (!$reporte) {
                return response()->json([
                    'mensaje' => 'Reporte no encontrado'
                ], 404);
            }

            $reporte->estado = 'generado';
            $reporte->archivo_url = $request->archivo_url;
            $reporte->save();

            return response()->json([
                'mensaje' => 'Reporte marcado como completado exitosamente',
                'data' => [
                    'id_reporte' => $reporte->id_reporte,
                    'estado' => $reporte->estado,
                    'archivo_url' => $reporte->archivo_url,
                    'fecha_completado' => $reporte->updated_at
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al marcar reporte como completado: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener datos específicos para gráficos y visualizaciones
     * GET /api/v1/admin/reports/data/charts?fecha_inicio=YYYY-MM-DD&fecha_fin=YYYY-MM-DD&tipo=ventas_diarias
     */
    public function getChartsData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'tipo' => 'required|in:ventas_diarias,ventas_mensuales,productos_vendidos,estados_pedidos'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensaje' => 'Solicitud incorrecta',
                'errores' => $validator->errors()
            ], 400);
        }

        try {
            $fechaInicio = $request->fecha_inicio;
            $fechaFin = $request->fecha_fin;
            $tipo = $request->tipo;

            $data = [];

            switch ($tipo) {
                case 'ventas_diarias':
                    $data = $this->getVentasDiariasChart($fechaInicio, $fechaFin);
                    break;
                
                case 'ventas_mensuales':
                    $data = $this->getVentasMensualesChart($fechaInicio, $fechaFin);
                    break;
                
                case 'productos_vendidos':
                    $data = $this->getTopProductosChart($fechaInicio, $fechaFin);
                    break;
                
                case 'estados_pedidos':
                    $data = $this->getEstadosPedidosChart($fechaInicio, $fechaFin);
                    break;
            }

            return response()->json([
                'mensaje' => 'Datos de gráfico obtenidos exitosamente',
                'tipo' => $tipo,
                'periodo' => [
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin
                ],
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener datos de gráfico: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Datos optimizados para gráfico de ventas diarias
     */
    private function getVentasDiariasChart($fechaInicio, $fechaFin)
    {
        $ventas = Pago::where('estado_pago', 'aprobado')
            ->whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
            ->selectRaw('DATE(fecha_pago) as fecha, SUM(monto_pago) as total, COUNT(*) as transacciones')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        return [
            'labels' => $ventas->pluck('fecha')->toArray(),
            'datasets' => [
                [
                    'label' => 'Ventas Diarias',
                    'data' => $ventas->pluck('total')->toArray(),
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'borderWidth' => 2
                ],
                [
                    'label' => 'Transacciones',
                    'data' => $ventas->pluck('transacciones')->toArray(),
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'borderWidth' => 2,
                    'yAxisID' => 'y1'
                ]
            ]
        ];
    }

    /**
     * Datos optimizados para gráfico de top productos
     */
    private function getTopProductosChart($fechaInicio, $fechaFin)
    {
        $productos = Pedido::whereBetween('pedidos.fecha_creacion', [$fechaInicio, $fechaFin])
            ->join('pedido_items', 'pedidos.id_pedido', '=', 'pedido_items.pedidos_id_pedido')
            ->join('productos', 'pedido_items.productos_id_producto', '=', 'productos.id_producto')
            ->selectRaw('productos.nombre, SUM(pedido_items.cantidad) as total_vendidos, SUM(pedido_items.cantidad * productos.precio) as ingresos')
            ->groupBy('productos.id_producto', 'productos.nombre')
            ->orderByDesc('total_vendidos')
            ->limit(10)
            ->get();

        return [
            'labels' => $productos->pluck('nombre')->toArray(),
            'datasets' => [
                [
                    'label' => 'Unidades Vendidas',
                    'data' => $productos->pluck('total_vendidos')->toArray(),
                    'backgroundColor' => [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                        '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
                    ]
                ]
            ]
        ];
    }

    /**
     * Datos optimizados para gráfico de estados de pedidos
     */
    private function getEstadosPedidosChart($fechaInicio, $fechaFin)
    {
        $estados = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])
            ->selectRaw('estado, COUNT(*) as cantidad')
            ->groupBy('estado')
            ->get();

        return [
            'labels' => $estados->pluck('estado')->toArray(),
            'datasets' => [
                [
                    'data' => $estados->pluck('cantidad')->toArray(),
                    'backgroundColor' => [
                        '#28a745', // completado - verde
                        '#ffc107', // pendiente - amarillo
                        '#dc3545', // cancelado - rojo
                        '#17a2b8', // en_proceso - azul
                        '#6c757d'  // otros - gris
                    ]
                ]
            ]
        ];
    }

    /**
     * Datos optimizados para gráfico de ventas mensuales
     */
    private function getVentasMensualesChart($fechaInicio, $fechaFin)
    {
        $ventas = Pago::where('estado_pago', 'aprobado')
            ->whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
            ->selectRaw('YEAR(fecha_pago) as año, MONTH(fecha_pago) as mes, SUM(monto_pago) as total')
            ->groupBy('año', 'mes')
            ->orderBy('año')
            ->orderBy('mes')
            ->get();

        $labels = $ventas->map(function($venta) {
            return $venta->año . '-' . str_pad($venta->mes, 2, '0', STR_PAD_LEFT);
        });

        return [
            'labels' => $labels->toArray(),
            'datasets' => [
                [
                    'label' => 'Ventas Mensuales',
                    'data' => $ventas->pluck('total')->toArray(),
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'borderWidth' => 2,
                    'fill' => true
                ]
            ]
        ];
    }

    /**
     * Obtener KPIs (indicadores clave) para el dashboard
     * GET /api/v1/admin/reports/kpis?fecha_inicio=YYYY-MM-DD&fecha_fin=YYYY-MM-DD
     */
    public function getKPIs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensaje' => 'Solicitud incorrecta',
                'errores' => $validator->errors()
            ], 400);
        }

        try {
            $fechaInicio = $request->fecha_inicio;
            $fechaFin = $request->fecha_fin;

            // KPIs principales
            $totalVentas = Pago::where('estado_pago', 'aprobado')
                ->whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
                ->sum('monto_pago');

            $totalPedidos = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])->count();
            
            $ticketPromedio = $totalPedidos > 0 ? round($totalVentas / $totalPedidos, 2) : 0;

            $crecimientoVentas = $this->calcularCrecimientoVentas($fechaInicio, $fechaFin);

            return response()->json([
                'mensaje' => 'KPIs obtenidos exitosamente',
                'data' => [
                    'total_ventas' => [
                        'valor' => $totalVentas,
                        'formato' => 'currency',
                        'crecimiento' => $crecimientoVentas['ventas']
                    ],
                    'total_pedidos' => [
                        'valor' => $totalPedidos,
                        'formato' => 'number',
                        'crecimiento' => $crecimientoVentas['pedidos']
                    ],
                    'ticket_promedio' => [
                        'valor' => $ticketPromedio,
                        'formato' => 'currency'
                    ],
                    'conversion_rate' => [
                        'valor' => $this->calcularTasaConversion($fechaInicio, $fechaFin),
                        'formato' => 'percentage'
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener KPIs: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Calcular crecimiento comparando con el periodo anterior
     */
    private function calcularCrecimientoVentas($fechaInicio, $fechaFin)
    {
        // Calcular duración del periodo actual
        $diasPeriodo = \Carbon\Carbon::parse($fechaInicio)->diffInDays(\Carbon\Carbon::parse($fechaFin)) + 1;
        
        // Periodo anterior
        $fechaInicioAnterior = \Carbon\Carbon::parse($fechaInicio)->subDays($diasPeriodo)->format('Y-m-d');
        $fechaFinAnterior = \Carbon\Carbon::parse($fechaInicio)->subDay()->format('Y-m-d');

        // Ventas periodo actual
        $ventasActuales = Pago::where('estado_pago', 'aprobado')
            ->whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
            ->sum('monto_pago');

        // Ventas periodo anterior
        $ventasAnteriores = Pago::where('estado_pago', 'aprobado')
            ->whereBetween('fecha_pago', [$fechaInicioAnterior, $fechaFinAnterior])
            ->sum('monto_pago');

        // Pedidos
        $pedidosActuales = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])->count();
        $pedidosAnteriores = Pedido::whereBetween('fecha_creacion', [$fechaInicioAnterior, $fechaFinAnterior])->count();

        return [
            'ventas' => $ventasAnteriores > 0 ? round((($ventasActuales - $ventasAnteriores) / $ventasAnteriores) * 100, 2) : 0,
            'pedidos' => $pedidosAnteriores > 0 ? round((($pedidosActuales - $pedidosAnteriores) / $pedidosAnteriores) * 100, 2) : 0
        ];
    }

    /**
     * Calcular tasa de conversión
     */
    private function calcularTasaConversion($fechaInicio, $fechaFin)
    {
        $carritosCreados = Carrito::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])->count();
        $pedidosCompletados = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])
            ->where('estado', 'completado')
            ->count();

        return $carritosCreados > 0 ? round(($pedidosCompletados / $carritosCreados) * 100, 2) : 0;
    }
}
