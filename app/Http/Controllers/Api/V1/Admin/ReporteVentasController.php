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

            // Total de ventas (sumatoria de pagos completados en el periodo)
            $totalVentas = Pago::where('estado_pago', Pago::ESTADO_COMPLETADO)
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
                ->selectRaw('pedido_items.producto_nombre_snapshot as nombre, SUM(pedido_items.cantidad) as total_vendidos')
                ->groupBy('pedido_items.producto_nombre_snapshot')
                ->orderByDesc('total_vendidos')
                ->limit(5)
                ->get();

            // Total de carritos abandonados en el periodo
            $carritosAbandonados = Carrito::where('estado', Carrito::ESTADO_ABANDONADO)
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
                'estado' => Reporte::ESTADO_EN_PROCESO,
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
                'estado' => Reporte::ESTADO_GENERADO,
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
                $reporte->update(['estado' => Reporte::ESTADO_ERROR]);
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

        // Total de ventas (sumatoria de pagos completados en el periodo)
        $totalVentas = Pago::where('estado_pago', Pago::ESTADO_COMPLETADO)
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
            ->selectRaw('pedido_items.producto_nombre_snapshot as nombre, SUM(pedido_items.cantidad) as total_vendidos, SUM(pedido_items.subtotal) as ingresos_generados')
            ->groupBy('pedido_items.producto_nombre_snapshot')
            ->orderByDesc('total_vendidos')
            ->limit(10)
            ->get();

        // Ventas por día
        $ventasPorDia = Pago::where('estado_pago', Pago::ESTADO_COMPLETADO)
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

            if ($reporte->estado !== Reporte::ESTADO_GENERADO || !$reporte->archivo_url) {
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

            if ($reporte->estado === Reporte::ESTADO_EN_PROCESO) {
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
            'estado' => 'required|in:' . implode(',', Reporte::getEstados())
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

            $reporte->estado = Reporte::ESTADO_GENERADO;
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
                'chart_data' => $data,
                'metadata' => [
                    'generado_en' => now()->toISOString(),
                    'tipo_grafico_recomendado' => $data['chart_type'] ?? 'line',
                    'total_registros' => count($data['labels'] ?? [])
                ]
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
     * Tipo de gráfico recomendado: Gráfico de líneas con doble eje Y (Line Chart - Dual Axis)
     * Propósito: Mostrar evolución temporal de ingresos y volumen de transacciones
     */
    private function getVentasDiariasChart($fechaInicio, $fechaFin)
    {
        $ventas = Pago::where('estado_pago', Pago::ESTADO_COMPLETADO)
            ->whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
            ->selectRaw('DATE(fecha_pago) as fecha, SUM(monto_pago) as ingresos_diarios, COUNT(*) as num_transacciones, AVG(monto_pago) as ticket_promedio_dia')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        return [
            'chart_type' => 'line_dual_axis',
            'title' => 'Evolución de Ventas Diarias',
            'description' => 'Análisis de ingresos y volumen de transacciones por día',
            'labels' => $ventas->pluck('fecha')->toArray(),
            'datasets' => [
                [
                    'label' => 'Ingresos Diarios ($)',
                    'data' => $ventas->pluck('ingresos_diarios')->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'borderWidth' => 3,
                    'fill' => true,
                    'tension' => 0.4,
                    'yAxisID' => 'y'
                ],
                [
                    'label' => 'Número de Transacciones',
                    'data' => $ventas->pluck('num_transacciones')->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 2,
                    'borderDash' => [5, 5],
                    'fill' => false,
                    'yAxisID' => 'y1'
                ],
                [
                    'label' => 'Ticket Promedio por Día ($)',
                    'data' => $ventas->pluck('ticket_promedio_dia')->map(function($valor) {
                        return round($valor, 2);
                    })->toArray(),
                    'backgroundColor' => 'rgba(168, 85, 247, 0.1)',
                    'borderColor' => 'rgba(168, 85, 247, 1)',
                    'borderWidth' => 2,
                    'pointRadius' => 4,
                    'fill' => false,
                    'yAxisID' => 'y'
                ]
            ],
            'chart_config' => [
                'scales' => [
                    'y' => ['title' => 'Ingresos ($)', 'position' => 'left'],
                    'y1' => ['title' => 'Transacciones', 'position' => 'right', 'grid' => false]
                ]
            ]
        ];
    }

    /**
     * Datos optimizados para gráfico de top productos
     * Tipo de gráfico recomendado: Gráfico de barras horizontales (Horizontal Bar Chart)
     * Propósito: Ranking de productos por desempeño de ventas y rentabilidad
     */
    private function getTopProductosChart($fechaInicio, $fechaFin)
    {
        $productos = Pedido::whereBetween('pedidos.fecha_creacion', [$fechaInicio, $fechaFin])
            ->join('pedido_items', 'pedidos.id_pedido', '=', 'pedido_items.pedidos_id_pedido')
            ->selectRaw('
                pedido_items.producto_nombre_snapshot as producto_nombre, 
                SUM(pedido_items.cantidad) as unidades_vendidas, 
                SUM(pedido_items.subtotal) as ingresos_generados,
                COUNT(DISTINCT pedidos.id_pedido) as pedidos_incluidos,
                ROUND(AVG(pedido_items.cantidad), 1) as cantidad_promedio_por_pedido
            ')
            ->groupBy('pedido_items.producto_nombre_snapshot')
            ->orderByDesc('ingresos_generados')
            ->limit(10)
            ->get();

        return [
            'chart_type' => 'horizontal_bar',
            'title' => 'Top 10 Productos por Ingresos',
            'description' => 'Ranking de productos más rentables del período',
            'labels' => $productos->pluck('producto_nombre')->toArray(),
            'datasets' => [
                [
                    'label' => 'Ingresos Generados ($)',
                    'data' => $productos->pluck('ingresos_generados')->toArray(),
                    'backgroundColor' => [
                        '#10B981', '#059669', '#047857', '#065F46', '#064E3B', // Verdes degradados
                        '#3B82F6', '#2563EB', '#1D4ED8', '#1E40AF', '#1E3A8A'  // Azules degradados
                    ],
                    'borderColor' => '#374151',
                    'borderWidth' => 1,
                    'yAxisID' => 'y'
                ]
            ],
            'additional_data' => [
                'unidades_vendidas' => $productos->pluck('unidades_vendidas')->toArray(),
                'pedidos_incluidos' => $productos->pluck('pedidos_incluidos')->toArray(),
                'cantidad_promedio_por_pedido' => $productos->pluck('cantidad_promedio_por_pedido')->toArray()
            ],
            'chart_config' => [
                'indexAxis' => 'y',
                'responsive' => true,
                'plugins' => [
                    'legend' => ['display' => false],
                    'tooltip' => [
                        'callbacks' => [
                            'afterLabel' => 'Mostrar unidades vendidas y pedidos'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Datos optimizados para gráfico de estados de pedidos
     * Tipo de gráfico recomendado: Gráfico de dona/donut (Doughnut Chart)
     * Propósito: Distribución porcentual del estado operativo de pedidos
     */
    private function getEstadosPedidosChart($fechaInicio, $fechaFin)
    {
        // Primero obtenemos el total de pedidos en el período
        $totalPedidos = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])->count();
        
        // Luego obtenemos los estados con sus cantidades
        $estados = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])
            ->selectRaw('estado as estado_pedido, COUNT(*) as cantidad_pedidos')
            ->groupBy('estado')
            ->orderByDesc('cantidad_pedidos')
            ->get();

        // Calculamos los porcentajes manualmente
        $estados = $estados->map(function($estado) use ($totalPedidos) {
            $estado->porcentaje_del_total = $totalPedidos > 0 ? 
                round(($estado->cantidad_pedidos / $totalPedidos) * 100, 1) : 0;
            return $estado;
        });

        // Mapeo de estados a etiquetas más descriptivas
        $estadosDescriptivos = [
            'pendiente' => 'Pendientes de Procesar',
            'en_proceso' => 'En Preparación',
            'en_camino' => 'En Camino al Cliente',
            'entregado' => 'Entregados Exitosamente',
            'cancelado' => 'Cancelados',
            'devuelto' => 'Devueltos'
        ];

        $labels = $estados->map(function($estado) use ($estadosDescriptivos) {
            return $estadosDescriptivos[$estado->estado_pedido] ?? ucfirst($estado->estado_pedido);
        })->toArray();

        return [
            'chart_type' => 'doughnut',
            'title' => 'Distribución de Estados de Pedidos',
            'description' => 'Análisis del flujo operativo y eficiencia de entrega',
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Pedidos por Estado',
                    'data' => $estados->pluck('cantidad_pedidos')->toArray(),
                    'backgroundColor' => [
                        '#FCD34D', // Pendiente - Amarillo
                        '#60A5FA', // En proceso - Azul claro
                        '#34D399', // En camino - Verde claro
                        '#10B981', // Entregado - Verde
                        '#F87171', // Cancelado - Rojo claro
                        '#F59E0B'  // Devuelto - Naranja
                    ],
                    'borderColor' => '#FFFFFF',
                    'borderWidth' => 2,
                    'hoverOffset' => 4
                ]
            ],
            'additional_data' => [
                'porcentajes' => $estados->pluck('porcentaje_del_total')->toArray(),
                'estados_originales' => $estados->pluck('estado_pedido')->toArray(),
                'total_pedidos' => $estados->sum('cantidad_pedidos')
            ],
            'chart_config' => [
                'cutout' => '60%',
                'plugins' => [
                    'legend' => [
                        'position' => 'right',
                        'labels' => ['usePointStyle' => true]
                    ],
                    'tooltip' => [
                        'callbacks' => [
                            'label' => 'Mostrar cantidad y porcentaje'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Datos optimizados para gráfico de ventas mensuales
     * Tipo de gráfico recomendado: Gráfico de área (Area Chart)
     * Propósito: Tendencia de crecimiento y estacionalidad de ingresos por mes
     */
    private function getVentasMensualesChart($fechaInicio, $fechaFin)
    {
        $ventas = Pago::where('estado_pago', Pago::ESTADO_COMPLETADO)
            ->whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
            ->selectRaw('
                YEAR(fecha_pago) as año, 
                MONTH(fecha_pago) as mes, 
                SUM(monto_pago) as ingresos_mensuales,
                COUNT(*) as transacciones_mes,
                COUNT(DISTINCT DATE(fecha_pago)) as dias_con_ventas,
                ROUND(AVG(monto_pago), 2) as ticket_promedio_mes
            ')
            ->groupBy('año', 'mes')
            ->orderBy('año')
            ->orderBy('mes')
            ->get();

        // Formatear etiquetas de meses en español
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $labels = $ventas->map(function($venta) use ($meses) {
            return $meses[$venta->mes] . ' ' . $venta->año;
        });

        return [
            'chart_type' => 'area',
            'title' => 'Evolución de Ventas Mensuales',
            'description' => 'Análisis de tendencias y estacionalidad de ingresos',
            'labels' => $labels->toArray(),
            'datasets' => [
                [
                    'label' => 'Ingresos Mensuales ($)',
                    'data' => $ventas->pluck('ingresos_mensuales')->toArray(),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.3)',
                    'borderColor' => 'rgba(16, 185, 129, 1)',
                    'borderWidth' => 3,
                    'fill' => true,
                    'tension' => 0.4,
                    'pointBackgroundColor' => 'rgba(16, 185, 129, 1)',
                    'pointBorderColor' => '#FFFFFF',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 5
                ]
            ],
            'additional_data' => [
                'transacciones_por_mes' => $ventas->pluck('transacciones_mes')->toArray(),
                'dias_con_ventas' => $ventas->pluck('dias_con_ventas')->toArray(),
                'ticket_promedio_mes' => $ventas->pluck('ticket_promedio_mes')->toArray(),
                'años_meses' => $ventas->map(function($v) { return $v->año . '-' . str_pad($v->mes, 2, '0', STR_PAD_LEFT); })->toArray()
            ],
            'chart_config' => [
                'scales' => [
                    'x' => ['title' => 'Período'],
                    'y' => ['title' => 'Ingresos ($)', 'beginAtZero' => true]
                ],
                'plugins' => [
                    'legend' => ['display' => true, 'position' => 'top'],
                    'tooltip' => [
                        'mode' => 'index',
                        'intersect' => false,
                        'callbacks' => [
                            'afterLabel' => 'Mostrar transacciones y días activos'
                        ]
                    ]
                ],
                'interaction' => [
                    'mode' => 'nearest',
                    'axis' => 'x',
                    'intersect' => false
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
            $totalVentas = Pago::where('estado_pago', Pago::ESTADO_COMPLETADO)
                ->whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
                ->sum('monto_pago');

            $totalPedidos = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])->count();
            
            $ticketPromedio = $totalPedidos > 0 ? round($totalVentas / $totalPedidos, 2) : 0;

            $crecimientoVentas = $this->calcularCrecimientoVentas($fechaInicio, $fechaFin);
            $tasaConversion = $this->calcularTasaConversion($fechaInicio, $fechaFin);

            // Información del período para contexto
            $diasPeriodo = \Carbon\Carbon::parse($fechaInicio)->diffInDays(\Carbon\Carbon::parse($fechaFin)) + 1;
            $fechaInicioAnterior = \Carbon\Carbon::parse($fechaInicio)->subDays($diasPeriodo)->format('Y-m-d');
            $fechaFinAnterior = \Carbon\Carbon::parse($fechaInicio)->subDay()->format('Y-m-d');

            return response()->json([
                'mensaje' => 'KPIs obtenidos exitosamente',
                'periodo' => [
                    'actual' => [
                        'fecha_inicio' => $fechaInicio,
                        'fecha_fin' => $fechaFin,
                        'dias' => $diasPeriodo
                    ],
                    'anterior' => [
                        'fecha_inicio' => $fechaInicioAnterior,
                        'fecha_fin' => $fechaFinAnterior,
                        'dias' => $diasPeriodo
                    ]
                ],
                'data' => [
                    'total_ventas' => [
                        'valor' => $totalVentas,
                        'formato' => 'currency',
                        'crecimiento' => [
                            'porcentaje' => $crecimientoVentas['ventas'],
                            'tendencia' => $crecimientoVentas['ventas'] > 0 ? 'positiva' : ($crecimientoVentas['ventas'] < 0 ? 'negativa' : 'estable'),
                            'valor_anterior' => $crecimientoVentas['ventas_anterior'],
                            'diferencia_absoluta' => $totalVentas - $crecimientoVentas['ventas_anterior']
                        ]
                    ],
                    'total_pedidos' => [
                        'valor' => $totalPedidos,
                        'formato' => 'number',
                        'crecimiento' => [
                            'porcentaje' => $crecimientoVentas['pedidos'],
                            'tendencia' => $crecimientoVentas['pedidos'] > 0 ? 'positiva' : ($crecimientoVentas['pedidos'] < 0 ? 'negativa' : 'estable'),
                            'valor_anterior' => $crecimientoVentas['pedidos_anterior'],
                            'diferencia_absoluta' => $totalPedidos - $crecimientoVentas['pedidos_anterior']
                        ]
                    ],
                    'ticket_promedio' => [
                        'valor' => $ticketPromedio,
                        'formato' => 'currency',
                        'crecimiento' => [
                            'porcentaje' => $crecimientoVentas['ticket_promedio'],
                            'tendencia' => $crecimientoVentas['ticket_promedio'] > 0 ? 'positiva' : ($crecimientoVentas['ticket_promedio'] < 0 ? 'negativa' : 'estable'),
                            'valor_anterior' => $crecimientoVentas['ticket_promedio_anterior'],
                            'diferencia_absoluta' => $ticketPromedio - $crecimientoVentas['ticket_promedio_anterior']
                        ]
                    ],
                    'conversion_rate' => [
                        'valor' => $tasaConversion['tasa'],
                        'formato' => 'percentage',
                        'contexto' => [
                            'carritos_creados' => $tasaConversion['carritos_creados'],
                            'pedidos_completados' => $tasaConversion['pedidos_completados'],
                            'descripcion' => "De {$tasaConversion['carritos_creados']} carritos, {$tasaConversion['pedidos_completados']} se convirtieron en pedidos"
                        ]
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
        $ventasActuales = Pago::where('estado_pago', Pago::ESTADO_COMPLETADO)
            ->whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
            ->sum('monto_pago');

        // Ventas periodo anterior
        $ventasAnteriores = Pago::where('estado_pago', Pago::ESTADO_COMPLETADO)
            ->whereBetween('fecha_pago', [$fechaInicioAnterior, $fechaFinAnterior])
            ->sum('monto_pago');

        // Pedidos
        $pedidosActuales = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])->count();
        $pedidosAnteriores = Pedido::whereBetween('fecha_creacion', [$fechaInicioAnterior, $fechaFinAnterior])->count();

        // Calcular ticket promedio
        $ticketPromedioActual = $pedidosActuales > 0 ? round($ventasActuales / $pedidosActuales, 2) : 0;
        $ticketPromedioAnterior = $pedidosAnteriores > 0 ? round($ventasAnteriores / $pedidosAnteriores, 2) : 0;

        // Calcular porcentajes de crecimiento con límites razonables
        $crecimientoVentas = $ventasAnteriores > 0 ? 
            round((($ventasActuales - $ventasAnteriores) / $ventasAnteriores) * 100, 2) : 
            ($ventasActuales > 0 ? 100 : 0);

        $crecimientoPedidos = $pedidosAnteriores > 0 ? 
            round((($pedidosActuales - $pedidosAnteriores) / $pedidosAnteriores) * 100, 2) : 
            ($pedidosActuales > 0 ? 100 : 0);

        $crecimientoTicketPromedio = $ticketPromedioAnterior > 0 ? 
            round((($ticketPromedioActual - $ticketPromedioAnterior) / $ticketPromedioAnterior) * 100, 2) : 
            ($ticketPromedioActual > 0 ? 100 : 0);

        return [
            'ventas' => min(max($crecimientoVentas, -100), 1000), // Limitar entre -100% y 1000%
            'ventas_anterior' => $ventasAnteriores,
            'pedidos' => min(max($crecimientoPedidos, -100), 1000), // Limitar entre -100% y 1000%
            'pedidos_anterior' => $pedidosAnteriores,
            'ticket_promedio' => min(max($crecimientoTicketPromedio, -100), 1000), // Limitar entre -100% y 1000%
            'ticket_promedio_anterior' => $ticketPromedioAnterior
        ];
    }

    /**
     * Calcular tasa de conversión con contexto adicional
     */
    private function calcularTasaConversion($fechaInicio, $fechaFin)
    {
        $carritosCreados = Carrito::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])->count();
        $pedidosCompletados = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])
            ->where('estado', Pedido::ESTADO_ENTREGADO)
            ->count();

        $tasa = $carritosCreados > 0 ? round(($pedidosCompletados / $carritosCreados) * 100, 2) : 0;

        return [
            'tasa' => $tasa,
            'carritos_creados' => $carritosCreados,
            'pedidos_completados' => $pedidosCompletados
        ];
    }
}
