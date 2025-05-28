<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Fresaterra</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            color: #333;
            background-color: #f4f4f4;
        }
        header {
            background-color: #4CAF50;
            color: white;
            text-align: center;
            padding: 1em;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
            margin-top: 20px;
        }
        .card {
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 20px;
            padding: 20px;
            background-color: #fff;
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            color: #333;
        }
        .links {
            margin-top: 20px;
        }
        .links a {
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .links a:hover {
            background-color: #45a049;
        }
        .endpoint {
            background-color: #f9f9f9;
            padding: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #2196F3;
        }
        .method {
            font-weight: bold;
            color: #e91e63;
        }
        .url {
            color: #3f51b5;
        }
        footer {
            text-align: center;
            padding: 20px;
            background-color: #333;
            color: white;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <header>
        <h1>API Fresaterra Backend</h1>
        <p>Documentación y prueba de endpoints</p>
    </header>
    
    <div class="container">
        <div class="card">
            <h2>Servicios Disponibles</h2>
            <p>Selecciona uno de los siguientes servicios para acceder a su interfaz:</p>
            
            <div class="links">
                <a href="{{ url('/api/v1/comments') }}">Comentarios</a>
                <a href="{{ url('/api/v1/notifications') }}">Notificaciones</a>
            </div>
        </div>
        
        <div class="card">
            <h2>Documentación API - Comentarios</h2>
            
            <div class="endpoint">
                <span class="method">GET</span> 
                <span class="url">{{ url('/api/v1/comments') }}</span>
                <p>Obtiene todos los comentarios</p>
            </div>
            
            <div class="endpoint">
                <span class="method">GET</span> 
                <span class="url">{{ url('/api/v1/comments/{id}') }}</span>
                <p>Obtiene un comentario específico por ID</p>
            </div>
            
            <div class="endpoint">
                <span class="method">POST</span> 
                <span class="url">{{ url('/api/v1/comments') }}</span>
                <p>Crea un nuevo comentario</p>
            </div>
            
            <div class="endpoint">
                <span class="method">PUT</span> 
                <span class="url">{{ url('/api/v1/comments/{id}') }}</span>
                <p>Actualiza un comentario existente</p>
            </div>
            
            <div class="endpoint">
                <span class="method">DELETE</span> 
                <span class="url">{{ url('/api/v1/comments/{id}') }}</span>
                <p>Elimina un comentario</p>
            </div>
        </div>
        
        <div class="card">
            <h2>Documentación API - Notificaciones</h2>
            
            <div class="endpoint">
                <span class="method">GET</span> 
                <span class="url">{{ url('/api/v1/notifications') }}</span>
                <p>Obtiene todas las notificaciones</p>
            </div>
            
            <div class="endpoint">
                <span class="method">GET</span> 
                <span class="url">{{ url('/api/v1/notifications/{id}') }}</span>
                <p>Obtiene una notificación específica por ID</p>
            </div>
            
            <div class="endpoint">
                <span class="method">POST</span> 
                <span class="url">{{ url('/api/v1/notifications/send') }}</span>
                <p>Envía una nueva notificación</p>
            </div>
            
            <div class="endpoint">
                <span class="method">PUT</span> 
                <span class="url">{{ url('/api/v1/notifications/{id}') }}</span>
                <p>Actualiza una notificación existente</p>
            </div>
            
            <div class="endpoint">
                <span class="method">DELETE</span> 
                <span class="url">{{ url('/api/v1/notifications/{id}') }}</span>
                <p>Elimina una notificación</p>
            </div>
        </div>
    </div>
    
    <footer>
        <p>&copy; {{ date('Y') }} Fresaterra - Todos los derechos reservados</p>
    </footer>
</body>
</html>
