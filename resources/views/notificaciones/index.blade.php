<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Notificaciones</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        h1 {
            color: #2980b9;
            text-align: center;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .notificacion {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .estado {
            font-weight: bold;
            color: #27ae60;
        }
        .mensaje {
            margin-top: 10px;
            padding: 10px;
            background-color: #ecf0f1;
            border-radius: 5px;
        }
        .fecha {
            color: #7f8c8d;
            font-size: 0.8em;
            text-align: right;
        }
        .usuario {
            font-weight: bold;
            color: #3498db;
        }
        .api-links {
            margin: 20px 0;
            padding: 10px;
            background-color: #e7f3fe;
            border-left: 6px solid #2196F3;
        }
        .api-links a {
            display: block;
            margin: 5px 0;
            color: #2196F3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Lista de Notificaciones</h1>
        
        <div class="api-links">
            <h3>Enlaces API</h3>
            <a href="{{ url('/api/v1/comments') }}" target="_blank">API: Todos los comentarios (JSON)</a>
            <a href="{{ url('/api/v1/notifications') }}" target="_blank">API: Todas las notificaciones (JSON)</a>
        </div>

        @if(count($notificaciones) > 0)
            @foreach($notificaciones as $notificacion)
                <div class="notificacion">
                    <div class="estado">
                        Estado: {{ $notificacion->estado }}
                    </div>
                    @if($notificacion->mensaje)
                        <div class="mensaje">
                            <strong>Tipo:</strong> {{ $notificacion->mensaje->tipo ?? 'No especificado' }}<br>
                            <strong>Contenido:</strong> {{ $notificacion->mensaje->contenido ?? 'No disponible' }}
                        </div>
                    @else
                        <div class="mensaje">Mensaje no disponible</div>
                    @endif
                    <div class="usuario">
                        @if($notificacion->usuario)
                            Usuario: {{ $notificacion->usuario->nombre ?? 'Desconocido' }} 
                            {{ $notificacion->usuario->apellidos ?? '' }}
                        @else
                            Usuario: No disponible
                        @endif
                    </div>
                    <div class="fecha">Fecha: {{ $notificacion->fecha_creacion }}</div>
                </div>
            @endforeach
        @else
            <p>No hay notificaciones disponibles.</p>
        @endif
    </div>
</body>
</html>
