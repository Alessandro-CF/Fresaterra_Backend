<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Bienvenido a Fresaterra!</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            max-width: 200px;
            height: auto;
        }
        .header {
            text-align: center;
            color: #2c5530;
            margin-bottom: 30px;
        }
        .content {
            margin-bottom: 30px;
        }
        .user-info {
            background-color: #f0f8f0;
            border-left: 4px solid #4a7c59;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .cta-button {
            display: inline-block;
            background-color: #4a7c59;
            color: #ffffff;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .cta-button:hover {
            background-color: #3d6548;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #666666;
            font-size: 14px;
        }
        .social-links {
            margin: 20px 0;
        }
        .check-icon {
            color: #4a7c59;
            font-size: 18px;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="email-container">        <!-- Logo de Fresaterra -->
        <div class="logo-container">
            <!-- Diseño textual del logo con los colores originales -->
            <div style="text-align: center; background: linear-gradient(135deg, #f8f9fa, #ffffff); color: #333; padding: 25px; border: 3px solid #4a7c59; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <div style="font-size: 36px; font-weight: bold; margin-bottom: 8px; font-family: Arial, sans-serif;">
                    🌱 <span style="color: #e74c3c;">FRESA</span><span style="color: #4a7c59;">TERRA</span> 🌱
                </div>
                <div style="font-size: 16px; color: #4a7c59; font-weight: 500; letter-spacing: 1px;">
                    Productos frescos del campo a tu mesa
                </div>
                <div style="width: 100px; height: 3px; background: linear-gradient(to right, #e74c3c, #4a7c59); margin: 10px auto; border-radius: 2px;"></div>
            </div>
        </div>

        <!-- Encabezado -->
        <div class="header">
            <h1>✅ ¡Registro Confirmado!</h1>
            <h2>¡Bienvenido a Fresaterra, {{ $user->nombre }} {{ $user->apellidos }}!</h2>
        </div>

        <!-- Contenido principal -->
        <div class="content">
            <p>🎉 <strong>¡Felicitaciones!</strong> Tu cuenta ha sido creada exitosamente en Fresaterra.</p>
              <div class="user-info">
                <h3>📋 Datos de tu cuenta:</h3>
                <p><strong>👤 Nombre:</strong> {{ $user->nombre }} {{ $user->apellidos }}</p>
                <p><strong>📧 Email:</strong> {{ $user->email }}</p>
            </div>

            <p>🌱 Ahora puedes disfrutar de productos frescos y naturales directamente desde el invernadero hasta tu mesa.</p>
            
            <p>Estamos emocionados de tenerte como parte de nuestra comunidad de amantes de las fresas frescas y naturales.</p>

        </div>

        <!-- Botón de acción -->
        <div class="button-container">
            <a href="{{ $frontendUrl }}/login" class="cta-button">
                🚀 Iniciar Sesión Ahora
            </a>
        </div>

        <!-- Pie de página -->
        <div class="footer">
            <p>¡Gracias por elegir Fresaterra para tus compras de productos frescos!</p>
            
            <div class="social-links">
                <p>Síguenos en nuestras redes sociales:</p>
                <!-- Aquí puedes agregar enlaces a redes sociales -->
            </div>
            
            <p><strong>Saludos cordiales,</strong><br>
            🌱 El equipo de Fresaterra</p>
            
            <hr style="margin: 20px 0; border: none; border-top: 1px solid #e0e0e0;">
            
            <p style="font-size: 12px; color: #999999;">
                Este correo fue enviado automáticamente. Por favor no respondas a este mensaje.<br>
                Si tienes alguna pregunta, contáctanos a través de nuestro sitio web.
            </p>
        </div>
    </div>
</body>
</html>
