<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $data['subject'] ?? 'Notificación de Fresaterra' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .notification-type {
            background-color: #e8f5e8;
            padding: 10px;
            border-left: 4px solid #4CAF50;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🌱 Fresaterra</h1>
        <p>{{ $data['subject'] ?? 'Nueva Notificación' }}</p>
    </div>
    
    <div class="content">
        <h2>¡Hola {{ $data['user_name'] ?? 'Estimado cliente' }}!</h2>
        
        <p>{{ $data['message'] ?? 'Tienes una nueva notificación de Fresaterra.' }}</p>
        
        @if(isset($data['type']))
        <div class="notification-type">
            <strong>Tipo de notificación:</strong> 
            @switch($data['type'])
                @case('pedido')
                    📦 Actualización de Pedido
                    @break
                @case('producto')
                    🥕 Información de Producto
                    @break
                @case('promocion')
                    🏷️ Promoción Especial
                    @break
                @case('envio')
                    🚛 Estado de Envío
                    @break
                @case('pago')
                    💳 Información de Pago
                    @break                @case('registro')
                    🌱 Confirmación de Registro
                    @break
                @default
                    📢 Notificación General
            @endswitch
        </div>
        @endif
        
        @if(isset($data['details']) && is_array($data['details']))
        <h3>Detalles:</h3>
        <ul>
            @foreach($data['details'] as $key => $value)
            <li><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</li>
            @endforeach
        </ul>
        @endif
        
        @if(isset($data['action_url']))
        <p>
            <a href="{{ $data['action_url'] }}" class="button">Ver Detalles</a>
        </p>
        @endif
        
        <p>Gracias por confiar en Fresaterra para tus productos frescos y naturales.</p>
        
        <p>
            Saludos cordiales,<br>
            <strong>El equipo de Fresaterra</strong>
        </p>
    </div>
    
    <div class="footer">
        <p>Este es un correo automático, por favor no responder a este mensaje.</p>
        <p>© {{ date('Y') }} Fresaterra. Todos los derechos reservados.</p>
    </div>
</body>
</html>
