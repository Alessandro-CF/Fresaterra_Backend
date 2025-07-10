<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class InventarioController extends Controller
{
    /**
     * Listar existencias de todos los productos con su stock, estado, categoría
     * GET /api/v1/admin/inventory/products
     */
    public function getProductsInventory(Request $request)
    {
        try {
            $query = Producto::with(['categoria', 'inventarios'])
                ->leftJoin('inventarios', 'productos.id_producto', '=', 'inventarios.productos_id_producto')
                ->select(
                    'productos.*',
                    'inventarios.id_inventario',
                    'inventarios.cantidad_disponible',
                    'inventarios.fecha_ingreso',
                    'inventarios.ultima_actualizacion',
                    'inventarios.estado as estado_inventario'
                );

            // Filtrar por estado del producto
            if ($request->has('estado_producto')) {
                $query->where('productos.estado', $request->estado_producto);
            }

            // Filtrar por estado del inventario
            if ($request->has('estado_inventario')) {
                $query->where('inventarios.estado', $request->estado_inventario);
            }

            // Filtrar por categoría
            if ($request->has('categoria_id')) {
                $query->where('productos.categorias_id_categoria', $request->categoria_id);
            }

            // Filtrar por stock bajo
            if ($request->has('stock_bajo') && $request->stock_bajo == 'true') {
                $stockMinimo = $request->get('stock_minimo', 10);
                $query->where('inventarios.cantidad_disponible', '<=', $stockMinimo);
            }

            // Búsqueda por nombre de producto
            if ($request->has('search')) {
                $query->where('productos.nombre', 'like', '%' . $request->search . '%');
            }

            // Ordenamiento
            $orderBy = $request->get('order_by', 'inventarios.ultima_actualizacion');
            $orderDirection = $request->get('order_direction', 'desc');
            $query->orderBy($orderBy, $orderDirection);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $inventarios = $query->paginate($perPage);

            // Transformar los datos para una respuesta más limpia
            $data = $inventarios->getCollection()->map(function ($item) {
                return [
                    'id_producto' => $item->id_producto,
                    'nombre_producto' => $item->nombre,
                    'precio' => $item->precio,
                    'estado_producto' => $item->estado,
                    'categoria' => $item->categoria ? $item->categoria->nombre : 'Sin categoría',
                    'inventario' => [
                        'id_inventario' => $item->id_inventario,
                        'cantidad_disponible' => $item->cantidad_disponible ?? 0,
                        'fecha_ingreso' => $item->fecha_ingreso,
                        'ultima_actualizacion' => $item->ultima_actualizacion,
                        'estado_inventario' => $item->estado_inventario ?? 'sin_inventario'
                    ]
                ];
            });

            return response()->json([
                'message' => 'Inventario de productos obtenido exitosamente',
                'data' => $data,
                'pagination' => [
                    'current_page' => $inventarios->currentPage(),
                    'per_page' => $inventarios->perPage(),
                    'total' => $inventarios->total(),
                    'last_page' => $inventarios->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener inventario de productos: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener detalle del inventario de un producto específico
     * GET /api/v1/admin/inventory/products/{id}
     */
    public function getProductInventory($productId)
    {
        try {
            $producto = Producto::with(['categoria', 'inventarios'])->find($productId);

            if (!$producto) {
                return response()->json([
                    'message' => 'Producto no encontrado'
                ], 404);
            }


            // Calcular estadísticas del inventario
            $inventarios = $producto->inventarios;
            $stockTotal = $inventarios->sum('cantidad_disponible');
            $inventariosDisponibles = $inventarios->where('estado', 'disponible')->count();
            $inventariosAgotados = $inventarios->where('estado', 'agotado')->count();
            $ultimaActualizacion = $inventarios->max('ultima_actualizacion');

            $data = [
                'producto' => [
                    'id_producto' => $producto->id_producto,
                    'nombre' => $producto->nombre,
                    'descripcion' => $producto->descripcion,
                    'precio' => $producto->precio,
                    'estado' => $producto->estado,
                    'categoria' => $producto->categoria ? $producto->categoria->nombre : 'Sin categoría'
                ],
                'inventario_resumen' => [
                    'stock_total' => $stockTotal,
                    'registros_inventario' => $inventarios->count(),
                    'inventarios_disponibles' => $inventariosDisponibles,
                    'inventarios_agotados' => $inventariosAgotados,
                    'ultima_actualizacion' => $ultimaActualizacion
                ],
                'inventarios_detalle' => $inventarios->map(function ($inventario) {
                    return [
                        'id_inventario' => $inventario->id_inventario,
                        'cantidad_disponible' => $inventario->cantidad_disponible,
                        'fecha_ingreso' => $inventario->fecha_ingreso,
                        'ultima_actualizacion' => $inventario->ultima_actualizacion,
                        'estado' => $inventario->estado
                    ];
                })
            ];

            return response()->json([
                'message' => 'Detalle de inventario obtenido exitosamente',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener detalle de inventario: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Crear un nuevo registro de inventario para un producto
     * POST /api/v1/admin/inventory
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'productos_id_producto' => 'required|integer|exists:productos,id_producto',
            'cantidad_disponible' => 'required|integer|min:0',
            'fecha_ingreso' => 'sometimes|date',
            'estado' => 'required|in:disponible,agotado'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verificar que el producto existe
            $producto = Producto::find($request->productos_id_producto);
            if (!$producto) {
                return response()->json([
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            $inventario = Inventario::create([
                'productos_id_producto' => $request->productos_id_producto,
                'cantidad_disponible' => $request->cantidad_disponible,
                'fecha_ingreso' => $request->get('fecha_ingreso', now()),
                'ultima_actualizacion' => now(),
                'estado' => $request->estado
            ]);

            return response()->json([
                'message' => 'Registro de inventario creado exitosamente',
                'data' => $inventario->load('producto')
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear registro de inventario: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Actualizar solo la cantidad en stock de un producto
     * PATCH /api/v1/admin/inventory/products/{id}/stock
     */
    public function updateStock(Request $request, $productId)
    {
        $validator = Validator::make($request->all(), [
            'cantidad_disponible' => 'required|integer|min:0',
            'accion' => 'sometimes|in:aumentar,reducir,establecer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $producto = Producto::find($productId);
            if (!$producto) {
                return response()->json([
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            // Buscar el inventario más reciente del producto
            $inventario = Inventario::where('productos_id_producto', $productId)
                ->orderByDesc('id_inventario')
                ->first();

            $accion = $request->get('accion', 'establecer');
            $nuevaCantidad = $request->cantidad_disponible;

            if (!$inventario) {
                // Si no existe inventario, crear uno nuevo
                $estado = $nuevaCantidad > 0 ? 'disponible' : 'agotado';
                $inventario = Inventario::create([
                    'productos_id_producto' => $productId,
                    'cantidad_disponible' => $nuevaCantidad,
                    'fecha_ingreso' => now(),
                    'ultima_actualizacion' => now(),
                    'estado' => $estado
                ]);
            } else {
                // Actualizar inventario existente según la acción
                switch ($accion) {
                    case 'aumentar':
                        $nuevaCantidad = $inventario->cantidad_disponible + $request->cantidad_disponible;
                        break;
                    case 'reducir':
                        $nuevaCantidad = max(0, $inventario->cantidad_disponible - $request->cantidad_disponible);
                        break;
                    case 'establecer':
                    default:
                        // $nuevaCantidad ya viene del request
                        break;
                }

                $estado = $nuevaCantidad > 0 ? 'disponible' : 'agotado';

                $inventario->update([
                    'cantidad_disponible' => $nuevaCantidad,
                    'ultima_actualizacion' => now(),
                    'estado' => $estado
                ]);
            }

            return response()->json([
                'message' => 'Stock actualizado exitosamente',
                'data' => [
                    'producto' => $producto->nombre,
                    'inventario' => $inventario,
                    'accion_realizada' => $accion
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al actualizar stock: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Cambiar el estado (activo/inactivo) del inventario de un producto
     * PATCH /api/v1/admin/inventory/products/{id}/status
     */
    public function updateStatus(Request $request, $productId)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:disponible,agotado'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $producto = Producto::find($productId);
            if (!$producto) {
                return response()->json([
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            // Actualizar todos los registros de inventario del producto
            $inventarios = Inventario::where('productos_id_producto', $productId);
            $count = $inventarios->count();

            if ($count === 0) {
                return response()->json([
                    'message' => 'No se encontraron registros de inventario para este producto'
                ], 404);
            }

            $inventarios->update([
                'estado' => $request->estado,
                'ultima_actualizacion' => now()
            ]);

            return response()->json([
                'message' => "Estado de inventario actualizado exitosamente para {$count} registro(s)",
                'data' => [
                    'producto' => $producto->nombre,
                    'nuevo_estado' => $request->estado,
                    'registros_actualizados' => $count
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de inventario: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas generales del inventario
     * GET /api/v1/admin/inventory/statistics
     */
    public function getStatistics()
    {
        try {
            // Estadísticas generales
            $totalProductos = Producto::count();
            $productosConInventario = Producto::whereHas('inventarios')->count();
            $productosSinInventario = $totalProductos - $productosConInventario;
            

            // Stock total
            $stockTotal = Inventario::where('estado', 'disponible')->sum('cantidad_disponible');

            // Productos con stock bajo (menos de 10 unidades)
            $stockBajo = Inventario::where('estado', 'disponible')
                ->where('cantidad_disponible', '<=', 10)
                ->count();

            // Productos agotados
            $productosAgotados = Inventario::where('estado', 'agotado')
                ->orWhere('cantidad_disponible', 0)
                ->count();

            // Inventarios por estado
            $inventariosPorEstado = Inventario::selectRaw('estado, COUNT(*) as count')
                ->groupBy('estado')
                ->pluck('count', 'estado');

            return response()->json([
                'message' => 'Estadísticas de inventario obtenidas exitosamente',
                'data' => [
                    'resumen_productos' => [
                        'total_productos' => $totalProductos,
                        'productos_con_inventario' => $productosConInventario,
                        'productos_sin_inventario' => $productosSinInventario
                    ],
                    'resumen_stock' => [
                        'stock_total' => $stockTotal,
                        'productos_stock_bajo' => $stockBajo,
                        'productos_agotados' => $productosAgotados
                    ],
                    'inventarios_por_estado' => $inventariosPorEstado
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de inventario: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
}
