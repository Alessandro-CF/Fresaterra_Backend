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
     * Solicitar la generación de un nuevo reporte de ventas (asíncrono) - MEJORADO
     * POST /api/v1/admin/reportes
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo' => 'required|in:ventas_mensuales,ventas_diarias,ventas_personalizadas',
            'fecha_inicio' => 'required|date|before_or_equal:today',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio|before_or_equal:today',
            'generar_inmediato' => 'sometimes|boolean',
            'descripcion_personalizada' => 'sometimes|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensaje' => 'Solicitud incorrecta',
                'errores' => $validator->errors()
            ], 400);
        }

        try {
            $user = Auth::user();
            $fechaInicio = $request->fecha_inicio;
            $fechaFin = $request->fecha_fin;
            $tipo = $request->tipo;

            // Validaciones adicionales
            $validacionPeriodo = $this->validarPeriodoReporte($fechaInicio, $fechaFin, $tipo);
            if (!$validacionPeriodo['valido']) {
                return response()->json([
                    'mensaje' => 'Período no válido para el tipo de reporte',
                    'errores' => $validacionPeriodo['errores']
                ], 400);
            }

            // Verificar si ya existe un reporte similar en proceso
            $reporteExistente = $this->verificarReporteDuplicado($tipo, $fechaInicio, $fechaFin);
            if ($reporteExistente) {
                return response()->json([
                    'mensaje' => 'Ya existe un reporte similar en proceso o generado recientemente',
                    'reporte_existente' => [
                        'id_reporte' => $reporteExistente->id_reporte,
                        'estado' => $reporteExistente->estado,
                        'fecha_creacion' => $reporteExistente->fecha_creacion
                    ],
                    'sugerencia' => 'Puede usar el reporte existente o esperar a que complete para generar uno nuevo'
                ], 409);
            }
            
            // Crear el reporte en la base de datos
            $reporte = Reporte::create([
                'tipo' => $tipo,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'estado' => Reporte::ESTADO_EN_PROCESO,
                'usuarios_id_usuario' => $user->id_usuario ?? $user->id ?? null,
            ]);

            // Log de creación
            Log::info("Nuevo reporte solicitado", [
                'id_reporte' => $reporte->id_reporte,
                'tipo' => $tipo,
                'periodo' => "$fechaInicio a $fechaFin",
                'usuario' => $user->nombre ?? 'Sistema',
                'generar_inmediato' => $request->get('generar_inmediato', false)
            ]);

            // Si se solicita generación inmediata, procesar el reporte
            if ($request->get('generar_inmediato', false)) {
                return $this->generateReport($reporte);
            }

            return response()->json([
                'mensaje' => 'Generación de reporte solicitada exitosamente',
                'data' => [
                    'id_reporte' => $reporte->id_reporte,
                    'tipo' => $tipo,
                    'titulo' => $this->obtenerTituloReporte($tipo),
                    'estado' => $reporte->estado,
                    'estado_texto' => $this->obtenerTextoEstado($reporte->estado),
                    'periodo' => [
                        'fecha_inicio' => $fechaInicio,
                        'fecha_fin' => $fechaFin,
                        'duracion' => $this->calcularDuracionPeriodo($fechaInicio, $fechaFin)
                    ],
                    'tiempo_estimado' => $this->calcularTiempoEstimadoGeneracion($fechaInicio, $fechaFin),
                    'fecha_creacion' => $reporte->fecha_creacion
                ],
                'instrucciones' => [
                    'estado' => 'El reporte se está procesando en segundo plano',
                    'notificacion' => 'Se le notificará cuando esté listo para descarga',
                    'consulta' => 'Puede consultar el estado del reporte en cualquier momento'
                ]
            ], 202);
        } catch (\Exception $e) {
            Log::error('Error al solicitar generación de reporte: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Validar período del reporte según su tipo
     */
    private function validarPeriodoReporte($fechaInicio, $fechaFin, $tipo)
    {
        $errores = [];
        $diasPeriodo = \Carbon\Carbon::parse($fechaInicio)->diffInDays(\Carbon\Carbon::parse($fechaFin)) + 1;

        switch ($tipo) {
            case 'ventas_diarias':
                if ($diasPeriodo > 90) {
                    $errores[] = 'Para reportes diarios, el período no puede exceder 90 días';
                }
                break;

            case 'ventas_mensuales':
                if ($diasPeriodo < 30) {
                    $errores[] = 'Para reportes mensuales, se requiere un período mínimo de 30 días';
                }
                if ($diasPeriodo > 365) {
                    $errores[] = 'Para reportes mensuales, el período no puede exceder 1 año';
                }
                break;

            case 'ventas_personalizadas':
                if ($diasPeriodo > 180) {
                    $errores[] = 'Para reportes personalizados, el período no puede exceder 180 días';
                }
                break;
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores,
            'dias_periodo' => $diasPeriodo
        ];
    }

    /**
     * Verificar si existe un reporte similar
     */
    private function verificarReporteDuplicado($tipo, $fechaInicio, $fechaFin)
    {
        // Buscar reportes del mismo tipo y período en las últimas 2 horas
        return Reporte::where('tipo', $tipo)
            ->where('fecha_inicio', $fechaInicio)
            ->where('fecha_fin', $fechaFin)
            ->where(function($query) {
                $query->where('estado', Reporte::ESTADO_EN_PROCESO)
                      ->orWhere(function($q) {
                          $q->where('estado', Reporte::ESTADO_GENERADO)
                            ->where('created_at', '>=', now()->subHours(2));
                      });
            })
            ->first();
    }

    /**
     * Calcular tiempo estimado de generación
     */
    private function calcularTiempoEstimadoGeneracion($fechaInicio, $fechaFin)
    {
        $diasPeriodo = \Carbon\Carbon::parse($fechaInicio)->diffInDays(\Carbon\Carbon::parse($fechaFin)) + 1;
        
        if ($diasPeriodo <= 7) {
            return '1-2 minutos';
        } elseif ($diasPeriodo <= 30) {
            return '2-3 minutos';
        } elseif ($diasPeriodo <= 90) {
            return '3-5 minutos';
        } else {
            return '5-10 minutos';
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

            // Obtener los datos completos del reporte según el tipo
            $datosReporte = $this->obtenerDatosReporteCompleto($reporte->fecha_inicio, $reporte->fecha_fin, $reporte->tipo);

            // Generar el contenido del reporte en formato JSON optimizado
            $contenidoReporte = $this->generarContenidoReporteOptimizado($datosReporte, $reporte);

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
                    'tipo' => $reporte->tipo,
                    'estado' => $reporte->estado,
                    'archivo_url' => $archivoUrl,
                    'nombre_archivo' => $nombreArchivo,
                    'fecha_generacion' => $reporte->updated_at,
                    'periodo' => [
                        'fecha_inicio' => $reporte->fecha_inicio,
                        'fecha_fin' => $reporte->fecha_fin
                    ],
                    'resumen_ejecutivo' => [
                        'total_ventas' => $datosReporte['resumen']['total_ventas'] ?? 0,
                        'total_pedidos' => $datosReporte['resumen']['total_pedidos'] ?? 0,
                        'ticket_promedio' => $datosReporte['resumen']['ticket_promedio'] ?? 0,
                        'dias_analizados' => $datosReporte['metadata']['dias_analizados'] ?? 0
                    ],
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
     * Obtener la lista de reportes generados (historial) - MEJORADO
     * GET /api/v1/admin/reportes
     */
    public function index(Request $request)
    {
        try {
            // Parámetros de paginación y filtrado opcionales
            $porPagina = $request->get('per_page', 15);
            $estado = $request->get('estado');
            $tipo = $request->get('tipo');
            $fechaDesde = $request->get('fecha_desde');
            $fechaHasta = $request->get('fecha_hasta');

            // Query base
            $query = Reporte::with('usuario');

            // Aplicar filtros si existen
            if ($estado) {
                $query->where('estado', $estado);
            }
            if ($tipo) {
                $query->where('tipo', $tipo);
            }
            if ($fechaDesde) {
                $query->where('fecha_creacion', '>=', $fechaDesde);
            }
            if ($fechaHasta) {
                $query->where('fecha_creacion', '<=', $fechaHasta);
            }

            // Ordenar y paginar
            $reportes = $query->orderByDesc('fecha_creacion')->paginate($porPagina);

            $datos = $reportes->getCollection()->map(function ($reporte) {
                return [
                    'id_reporte' => $reporte->id_reporte,
                    'tipo' => $reporte->tipo,
                    'titulo' => $this->obtenerTituloReporte($reporte->tipo),
                    'fecha_creacion' => $reporte->fecha_creacion,
                    'fecha_inicio' => $reporte->fecha_inicio,
                    'fecha_fin' => $reporte->fecha_fin,
                    'estado' => $reporte->estado,
                    'estado_texto' => $this->obtenerTextoEstado($reporte->estado),
                    'archivo_disponible' => !empty($reporte->archivo_url),
                    'archivo_url' => $reporte->archivo_url,
                    'usuario_generador' => [
                        'id' => $reporte->usuario ? $reporte->usuario->id_usuario : null,
                        'nombre' => $reporte->usuario ? $reporte->usuario->nombre : 'Sistema'
                    ],
                    'duracion_periodo' => $this->calcularDuracionPeriodo($reporte->fecha_inicio, $reporte->fecha_fin)
                ];
            });

            return response()->json([
                'mensaje' => 'Historial de reportes obtenido exitosamente',
                'total' => $reportes->total(),
                'por_pagina' => $reportes->perPage(),
                'pagina_actual' => $reportes->currentPage(),
                'total_paginas' => $reportes->lastPage(),
                'filtros_aplicados' => [
                    'estado' => $estado,
                    'tipo' => $tipo,
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta
                ],
                'estadisticas' => [
                    'total_generados' => Reporte::where('estado', Reporte::ESTADO_GENERADO)->count(),
                    'total_en_proceso' => Reporte::where('estado', Reporte::ESTADO_EN_PROCESO)->count(),
                    'total_con_error' => Reporte::where('estado', Reporte::ESTADO_ERROR)->count()
                ],
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
     * Obtener texto descriptivo del estado
     */
    private function obtenerTextoEstado($estado)
    {
        $textos = [
            'en_proceso' => 'En Proceso',
            'generado' => 'Completado',
            'error' => 'Error al Generar'
        ];

        return $textos[$estado] ?? ucfirst($estado);
    }

    /**
     * Calcular duración del período en días
     */
    private function calcularDuracionPeriodo($fechaInicio, $fechaFin)
    {
        try {
            $inicio = \Carbon\Carbon::parse($fechaInicio);
            $fin = \Carbon\Carbon::parse($fechaFin);
            $dias = $inicio->diffInDays($fin) + 1;
            
            return [
                'dias' => $dias,
                'texto' => $dias === 1 ? '1 día' : "$dias días"
            ];
        } catch (\Exception $e) {
            return [
                'dias' => 0,
                'texto' => 'No calculado'
            ];
        }
    }

    /**
     * Obtener detalles de un reporte específico y su URL de descarga - MEJORADO
     * GET /api/v1/admin/reportes/{id_reporte}
     */
    public function show($id_reporte)
    {
        try {
            $reporte = Reporte::with('usuario')->find($id_reporte);
            if (!$reporte) {
                return response()->json([
                    'mensaje' => 'Reporte no encontrado'
                ], 404);
            }

            // Información base del reporte
            $detalles = [
                'id_reporte' => $reporte->id_reporte,
                'tipo' => $reporte->tipo,
                'titulo' => $this->obtenerTituloReporte($reporte->tipo),
                'descripcion' => $this->obtenerDescripcionReporte($reporte->tipo),
                'estado' => $reporte->estado,
                'estado_texto' => $this->obtenerTextoEstado($reporte->estado),
                'fecha_creacion' => $reporte->fecha_creacion,
                'fecha_actualizacion' => $reporte->updated_at,
                'periodo_analisis' => [
                    'fecha_inicio' => $reporte->fecha_inicio,
                    'fecha_fin' => $reporte->fecha_fin,
                    'duracion' => $this->calcularDuracionPeriodo($reporte->fecha_inicio, $reporte->fecha_fin)
                ],
                'usuario_generador' => [
                    'id_usuario' => $reporte->usuario ? $reporte->usuario->id_usuario : null,
                    'nombre' => $reporte->usuario ? $reporte->usuario->nombre : 'Sistema'
                ]
            ];

            // Información específica según el estado
            switch ($reporte->estado) {
                case Reporte::ESTADO_EN_PROCESO:
                    $detalles['mensaje_estado'] = 'El reporte está siendo procesado. Se le notificará cuando esté listo.';
                    $detalles['tiempo_estimado'] = 'Entre 1-5 minutos dependiendo del período analizado';
                    break;

                case Reporte::ESTADO_GENERADO:
                    $detalles['archivo_url'] = $reporte->archivo_url;
                    $detalles['archivo_disponible'] = !empty($reporte->archivo_url);
                    $detalles['mensaje_estado'] = 'El reporte ha sido generado exitosamente y está listo para descarga.';
                    
                    // Verificar si el archivo existe físicamente
                    if ($reporte->archivo_url) {
                        $rutaArchivo = str_replace(asset('storage/'), '', $reporte->archivo_url);
                        $detalles['archivo_existe'] = Storage::disk('public')->exists($rutaArchivo);
                        if ($detalles['archivo_existe']) {
                            $detalles['archivo_tamaño'] = Storage::disk('public')->size($rutaArchivo);
                            $detalles['archivo_tamaño_mb'] = round($detalles['archivo_tamaño'] / 1024 / 1024, 2);
                        }
                    }
                    break;

                case Reporte::ESTADO_ERROR:
                    $detalles['mensaje_estado'] = 'Ocurrió un error durante la generación del reporte. Puede intentar generar uno nuevo.';
                    $detalles['puede_reintentar'] = true;
                    break;
            }

            // Acciones disponibles según el estado
            $detalles['acciones_disponibles'] = $this->obtenerAccionesDisponibles($reporte);

            // Si el reporte está completado, incluir resumen rápido
            if ($reporte->estado === Reporte::ESTADO_GENERADO && $reporte->archivo_url) {
                $detalles['resumen_rapido'] = $this->obtenerResumenRapido($reporte);
            }

            return response()->json([
                'mensaje' => 'Detalles del reporte obtenidos exitosamente',
                'data' => $detalles
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener detalles de reporte: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener acciones disponibles según el estado del reporte
     */
    private function obtenerAccionesDisponibles($reporte)
    {
        $acciones = [];

        switch ($reporte->estado) {
            case Reporte::ESTADO_EN_PROCESO:
                $acciones = [
                    'cancelar' => 'Marcar como error para detener el proceso',
                    'consultar_estado' => 'Verificar el progreso del reporte'
                ];
                break;

            case Reporte::ESTADO_GENERADO:
                $acciones = [
                    'descargar' => 'Descargar el archivo del reporte',
                    'eliminar' => 'Eliminar el reporte y su archivo',
                    'regenerar' => 'Generar una nueva versión del reporte'
                ];
                break;

            case Reporte::ESTADO_ERROR:
                $acciones = [
                    'reintentar' => 'Intentar generar el reporte nuevamente',
                    'eliminar' => 'Eliminar el reporte fallido'
                ];
                break;
        }

        return $acciones;
    }

    /**
     * Obtener resumen rápido de un reporte completado
     */
    private function obtenerResumenRapido($reporte)
    {
        try {
            // Calcular datos básicos del período
            $fechaInicio = $reporte->fecha_inicio;
            $fechaFin = $reporte->fecha_fin;

            $totalVentas = Pago::where('estado_pago', Pago::ESTADO_COMPLETADO)
                ->whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
                ->sum('monto_pago');

            $totalPedidos = Pedido::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])->count();

            return [
                'total_ventas' => $totalVentas,
                'total_ventas_formateado' => '$' . number_format($totalVentas, 2),
                'total_pedidos' => $totalPedidos,
                'ticket_promedio' => $totalPedidos > 0 ? round($totalVentas / $totalPedidos, 2) : 0,
                'nota' => 'Resumen rápido calculado en tiempo real'
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'No se pudo calcular el resumen rápido',
                'nota' => 'Descargue el reporte para ver todos los detalles'
            ];
        }
    }

    /**
     * Eliminar un reporte generado - MEJORADO
     * DELETE /api/v1/admin/reportes/{id_reporte}
     */
    public function destroy($id_reporte)
    {
        try {
            $reporte = Reporte::find($id_reporte);
            if (!$reporte) {
                return response()->json([
                    'mensaje' => 'Reporte no encontrado'
                ], 404);
            }

            // Validar si el reporte se puede eliminar
            if (!$this->puedeEliminarReporte($reporte)) {
                return response()->json([
                    'mensaje' => 'No se puede eliminar este reporte',
                    'razon' => 'El reporte está en proceso de generación'
                ], 409);
            }

            // Guardar información del reporte antes de eliminarlo
            $infoReporte = [
                'id_reporte' => $reporte->id_reporte,
                'tipo' => $reporte->tipo,
                'estado' => $reporte->estado,
                'fecha_creacion' => $reporte->fecha_creacion,
                'periodo' => [
                    'fecha_inicio' => $reporte->fecha_inicio,
                    'fecha_fin' => $reporte->fecha_fin
                ],
                'tenia_archivo' => !empty($reporte->archivo_url)
            ];
            
            // Eliminar archivo del storage si existe
            $archivoEliminado = false;
            if ($reporte->archivo_url) {
                try {
                    $rutaArchivo = str_replace(asset('storage/'), '', $reporte->archivo_url);
                    if (Storage::disk('public')->exists($rutaArchivo)) {
                        Storage::disk('public')->delete($rutaArchivo);
                        $archivoEliminado = true;
                        Log::info("Archivo de reporte eliminado: {$rutaArchivo}");
                    }
                } catch (\Exception $e) {
                    Log::warning("No se pudo eliminar el archivo del reporte: " . $e->getMessage());
                }
            }

            // Eliminar el registro de la base de datos
            $reporte->delete();

            // Log de la eliminación
            Log::info("Reporte eliminado exitosamente", [
                'reporte_info' => $infoReporte,
                'archivo_eliminado' => $archivoEliminado,
                'usuario' => Auth::user()->nombre ?? 'Sistema'
            ]);
            
            return response()->json([
                'mensaje' => 'Reporte eliminado exitosamente',
                'data' => array_merge($infoReporte, [
                    'fecha_eliminacion' => now()->toISOString(),
                    'archivo_eliminado' => $archivoEliminado,
                    'eliminado_por' => Auth::user()->nombre ?? 'Sistema'
                ])
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar reporte: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Verificar si un reporte puede ser eliminado
     */
    private function puedeEliminarReporte($reporte)
    {
        // No permitir eliminar reportes que están en proceso
        return $reporte->estado !== Reporte::ESTADO_EN_PROCESO;
    }

    /**
     * Cambiar el estado de un reporte - MEJORADO
     * PATCH /api/v1/admin/reports/{id_reporte}/status
     */
    public function updateStatus(Request $request, $id_reporte)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:' . implode(',', Reporte::getEstados()),
            'motivo' => 'sometimes|string|max:255' // Motivo opcional del cambio
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
            $nuevoEstado = $request->estado;

            // Validaciones de transición de estado
            if (!$this->validarTransicionEstado($estadoAnterior, $nuevoEstado)) {
                return response()->json([
                    'mensaje' => 'Transición de estado no válida',
                    'estado_actual' => $estadoAnterior,
                    'estado_solicitado' => $nuevoEstado
                ], 400);
            }

            // Actualizar el estado
            $reporte->estado = $nuevoEstado;
            $reporte->save();

            // Log del cambio de estado
            Log::info("Estado de reporte actualizado", [
                'id_reporte' => $id_reporte,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $nuevoEstado,
                'usuario' => Auth::user()->nombre ?? 'Sistema',
                'motivo' => $request->motivo ?? 'No especificado'
            ]);

            return response()->json([
                'mensaje' => 'Estado del reporte actualizado exitosamente',
                'data' => [
                    'id_reporte' => $reporte->id_reporte,
                    'tipo' => $reporte->tipo,
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => $nuevoEstado,
                    'estado_texto' => $this->obtenerTextoEstado($nuevoEstado),
                    'fecha_actualizacion' => $reporte->updated_at,
                    'motivo' => $request->motivo ?? null,
                    'puede_descargar' => $nuevoEstado === Reporte::ESTADO_GENERADO && !empty($reporte->archivo_url)
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
     * Validar si una transición de estado es válida
     */
    private function validarTransicionEstado($estadoActual, $estadoNuevo)
    {
        // Definir transiciones válidas
        $transicionesValidas = [
            'en_proceso' => ['generado', 'error'],
            'generado' => ['error'], // Solo se puede marcar como error si es necesario
            'error' => ['en_proceso'] // Se puede reintentar desde error
        ];

        // Si es el mismo estado, es válido
        if ($estadoActual === $estadoNuevo) {
            return true;
        }

        // Verificar si la transición está permitida
        return isset($transicionesValidas[$estadoActual]) && 
               in_array($estadoNuevo, $transicionesValidas[$estadoActual]);
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

    /**
     * Obtener datos completos para el reporte optimizado según el tipo
     */
    private function obtenerDatosReporteCompleto($fechaInicio, $fechaFin, $tipoReporte)
    {
        // Calcular días analizados
        $diasAnalizados = \Carbon\Carbon::parse($fechaInicio)->diffInDays(\Carbon\Carbon::parse($fechaFin)) + 1;

        // Datos base del reporte (igual para todos los tipos)
        $datosBase = $this->obtenerDatosBase($fechaInicio, $fechaFin);

        // Datos específicos según el tipo de reporte
        $datosEspecificos = [];
        
        switch ($tipoReporte) {
            case 'ventas_diarias':
                $datosEspecificos = [
                    'chart_data' => $this->getVentasDiariasChart($fechaInicio, $fechaFin),
                    'enfoque' => 'Análisis diario de ventas y transacciones'
                ];
                break;
                
            case 'ventas_mensuales':
                $datosEspecificos = [
                    'chart_data' => $this->getVentasMensualesChart($fechaInicio, $fechaFin),
                    'enfoque' => 'Análisis mensual de tendencias de ventas'
                ];
                break;
                
            case 'ventas_personalizadas':
                $datosEspecificos = [
                    'chart_data' => [
                        'ventas_diarias' => $this->getVentasDiariasChart($fechaInicio, $fechaFin),
                        'productos_vendidos' => $this->getTopProductosChart($fechaInicio, $fechaFin),
                        'estados_pedidos' => $this->getEstadosPedidosChart($fechaInicio, $fechaFin)
                    ],
                    'enfoque' => 'Análisis integral personalizado con múltiples perspectivas'
                ];
                break;
                
            default:
                $datosEspecificos = [
                    'chart_data' => $this->getVentasDiariasChart($fechaInicio, $fechaFin),
                    'enfoque' => 'Análisis estándar de ventas'
                ];
                break;
        }

        // Combinar todos los datos
        return array_merge($datosBase, [
            'metadata' => [
                'tipo_reporte' => $tipoReporte,
                'dias_analizados' => $diasAnalizados,
                'fecha_generacion' => now()->toISOString(),
                'enfoque' => $datosEspecificos['enfoque'],
                'version_reporte' => '2.0'
            ],
            'datos_especificos' => $datosEspecificos
        ]);
    }

    /**
     * Obtener datos base para todos los tipos de reporte
     */
    private function obtenerDatosBase($fechaInicio, $fechaFin)
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

        // Calcular ticket promedio
        $ticketPromedio = $totalPedidos > 0 ? round($totalVentas / $totalPedidos, 2) : 0;

        // Total de carritos abandonados en el periodo
        $carritosAbandonados = Carrito::where('estado', Carrito::ESTADO_ABANDONADO)
            ->whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])
            ->count();

        // Cálculo de tasa de conversión
        $carritosCreados = Carrito::whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])->count();
        $tasaConversion = $carritosCreados > 0 ? round(($totalPedidos / $carritosCreados) * 100, 2) : 0;

        return [
            'periodo' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin
            ],
            'resumen' => [
                'total_pedidos' => $totalPedidos,
                'total_ventas' => $totalVentas,
                'total_envios' => $totalEnvios,
                'ticket_promedio' => $ticketPromedio,
                'carritos_abandonados' => $carritosAbandonados,
                'tasa_conversion' => $tasaConversion,
                'promedio_venta_diaria' => $ventasPorDia->count() > 0 ? round($totalVentas / $ventasPorDia->count(), 2) : 0
            ],
            'pedidos_por_estado' => $pedidosPorEstado,
            'top_productos' => $topProductos,
            'ventas_por_dia' => $ventasPorDia,
            'estadisticas_adicionales' => [
                'dias_con_ventas' => $ventasPorDia->count(),
                'mayor_venta_diaria' => $ventasPorDia->max('total_ventas') ?? 0,
                'menor_venta_diaria' => $ventasPorDia->min('total_ventas') ?? 0,
                'promedio_transacciones_dia' => $ventasPorDia->count() > 0 ? round($ventasPorDia->avg('cantidad_transacciones'), 2) : 0
            ]
        ];
    }

    /**
     * Generar contenido optimizado del reporte en formato JSON
     */
    private function generarContenidoReporteOptimizado($datos, $reporte)
    {
        $reporteOptimizado = [
            'header' => [
                'id_reporte' => $reporte->id_reporte,
                'tipo_reporte' => $reporte->tipo,
                'titulo' => $this->obtenerTituloReporte($reporte->tipo),
                'descripcion' => $this->obtenerDescripcionReporte($reporte->tipo),
                'fecha_generacion' => now()->toISOString(),
                'usuario_generador' => Auth::user()->nombre ?? 'Sistema',
                'version' => '2.0'
            ],
            'periodo_analisis' => $datos['periodo'],
            'resumen_ejecutivo' => [
                'metricas_principales' => $datos['resumen'],
                'insights' => $this->generarInsights($datos),
                'recomendaciones' => $this->generarRecomendaciones($datos, $reporte->tipo)
            ],
            'datos_detallados' => [
                'pedidos_por_estado' => $datos['pedidos_por_estado'],
                'productos_destacados' => $datos['top_productos'],
                'tendencias_ventas' => $datos['ventas_por_dia'],
                'estadisticas_adicionales' => $datos['estadisticas_adicionales'] ?? []
            ],
            'datos_graficos' => $datos['datos_especificos']['chart_data'] ?? [],
            'metadata' => $datos['metadata'],
            'footer' => [
                'generado_por' => 'Sistema de Reportes Fresaterra',
                'fecha_generacion' => now()->format('d/m/Y H:i:s'),
                'hash_integridad' => hash('sha256', $reporte->id_reporte . now()->timestamp)
            ]
        ];

        return json_encode($reporteOptimizado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Obtener título descriptivo según el tipo de reporte
     */
    private function obtenerTituloReporte($tipo)
    {
        $titulos = [
            'ventas_diarias' => 'Reporte de Ventas Diarias',
            'ventas_mensuales' => 'Reporte de Ventas Mensuales',
            'ventas_personalizadas' => 'Reporte Personalizado de Ventas'
        ];

        return $titulos[$tipo] ?? 'Reporte de Ventas';
    }

    /**
     * Obtener descripción según el tipo de reporte
     */
    private function obtenerDescripcionReporte($tipo)
    {
        $descripciones = [
            'ventas_diarias' => 'Análisis detallado del rendimiento de ventas día a día, incluyendo transacciones, ingresos y tendencias diarias.',
            'ventas_mensuales' => 'Resumen mensual de ventas con análisis de tendencias y comparativas de crecimiento mensual.',
            'ventas_personalizadas' => 'Reporte integral con análisis multidimensional de ventas, productos y estados de pedidos.'
        ];

        return $descripciones[$tipo] ?? 'Análisis de ventas para el período seleccionado.';
    }

    /**
     * Generar insights automáticos basados en los datos
     */
    private function generarInsights($datos)
    {
        $insights = [];
        $resumen = $datos['resumen'];
        $ventasPorDia = $datos['ventas_por_dia'];

        // Insight sobre ventas
        if ($resumen['total_ventas'] > 0) {
            $insights[] = [
                'tipo' => 'ventas',
                'mensaje' => "Se generaron ingresos por $" . number_format($resumen['total_ventas'], 2) . " en el período analizado.",
                'nivel' => 'info'
            ];
        }

        // Insight sobre ticket promedio
        if ($resumen['ticket_promedio'] > 0) {
            $insights[] = [
                'tipo' => 'ticket_promedio',
                'mensaje' => "El ticket promedio por pedido fue de $" . number_format($resumen['ticket_promedio'], 2) . ".",
                'nivel' => 'info'
            ];
        }

        // Insight sobre tendencia de ventas
        if ($ventasPorDia->count() > 1) {
            $primerDia = $ventasPorDia->first();
            $ultimoDia = $ventasPorDia->last();
            
            if ($ultimoDia->total_ventas > $primerDia->total_ventas) {
                $insights[] = [
                    'tipo' => 'tendencia',
                    'mensaje' => "Las ventas muestran una tendencia positiva durante el período.",
                    'nivel' => 'success'
                ];
            } elseif ($ultimoDia->total_ventas < $primerDia->total_ventas) {
                $insights[] = [
                    'tipo' => 'tendencia',
                    'mensaje' => "Las ventas muestran una tendencia descendente durante el período.",
                    'nivel' => 'warning'
                ];
            }
        }

        // Insight sobre tasa de conversión
        if (isset($resumen['tasa_conversion'])) {
            if ($resumen['tasa_conversion'] > 70) {
                $insights[] = [
                    'tipo' => 'conversion',
                    'mensaje' => "Excelente tasa de conversión del " . $resumen['tasa_conversion'] . "%.",
                    'nivel' => 'success'
                ];
            } elseif ($resumen['tasa_conversion'] < 30) {
                $insights[] = [
                    'tipo' => 'conversion',
                    'mensaje' => "La tasa de conversión del " . $resumen['tasa_conversion'] . "% puede mejorarse.",
                    'nivel' => 'warning'
                ];
            }
        }

        return $insights;
    }

    /**
     * Generar recomendaciones basadas en los datos y tipo de reporte
     */
    private function generarRecomendaciones($datos, $tipoReporte)
    {
        $recomendaciones = [];
        $resumen = $datos['resumen'];

        // Recomendaciones generales
        if ($resumen['carritos_abandonados'] > ($resumen['total_pedidos'] * 0.3)) {
            $recomendaciones[] = [
                'categoria' => 'conversion',
                'prioridad' => 'alta',
                'titulo' => 'Reducir Carritos Abandonados',
                'descripcion' => 'Alto número de carritos abandonados. Considerar implementar recordatorios por email o descuentos para completar la compra.'
            ];
        }

        if (isset($resumen['tasa_conversion']) && $resumen['tasa_conversion'] < 50) {
            $recomendaciones[] = [
                'categoria' => 'conversion',
                'prioridad' => 'media',
                'titulo' => 'Optimizar Proceso de Compra',
                'descripcion' => 'La tasa de conversión puede mejorarse simplificando el proceso de checkout y mejorando la experiencia de usuario.'
            ];
        }

        // Recomendaciones específicas por tipo
        if ($tipoReporte === 'ventas_diarias') {
            $ventasPorDia = $datos['ventas_por_dia'];
            if ($ventasPorDia->count() > 0) {
                $variacion = $ventasPorDia->max('total_ventas') - $ventasPorDia->min('total_ventas');
                if ($variacion > ($resumen['promedio_venta_diaria'] * 2)) {
                    $recomendaciones[] = [
                        'categoria' => 'ventas',
                        'prioridad' => 'media',
                        'titulo' => 'Estabilizar Ventas Diarias',
                        'descripcion' => 'Hay alta variabilidad en las ventas diarias. Considerar promociones en días de baja actividad.'
                    ];
                }
            }
        }

        if ($tipoReporte === 'ventas_mensuales') {
            $recomendaciones[] = [
                'categoria' => 'planificacion',
                'prioridad' => 'baja',
                'titulo' => 'Planificación Estacional',
                'descripcion' => 'Analizar patrones estacionales para optimizar inventario y campañas de marketing.'
            ];
        }

        // Recomendación sobre productos top
        $topProductos = $datos['top_productos'];
        if ($topProductos->count() > 0 && $topProductos->count() < 5) {
            $recomendaciones[] = [
                'categoria' => 'productos',
                'prioridad' => 'media',
                'titulo' => 'Diversificar Productos Populares',
                'descripcion' => 'Pocos productos concentran la mayoría de ventas. Considerar promocionar otros productos del catálogo.'
            ];
        }

        return $recomendaciones;
    }
}
