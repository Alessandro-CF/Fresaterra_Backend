<?php
// Archivo de verificación para producción
// Accede a: https://api.fresaterra.shop/verify-oauth.php

echo "<h1>Verificación OAuth - Fresaterra</h1>";
echo "<h2>Variables de Entorno (.env file):</h2>";
echo "<ul>";
echo "<li>APP_URL desde .env: " . ($_ENV['APP_URL'] ?? 'NO DEFINIDA') . "</li>";
echo "<li>FRONTEND_URL desde .env: " . ($_ENV['FRONTEND_URL'] ?? 'NO DEFINIDA') . "</li>";
echo "<li>GOOGLE_REDIRECT_URI desde .env: " . ($_ENV['GOOGLE_REDIRECT_URI'] ?? 'NO DEFINIDA') . "</li>";
echo "</ul>";

echo "<h2>Variables del Sistema:</h2>";
echo "<ul>";
echo "<li>APP_URL del sistema: " . (getenv('APP_URL') ?: 'NO DEFINIDA') . "</li>";
echo "<li>FRONTEND_URL del sistema: " . (getenv('FRONTEND_URL') ?: 'NO DEFINIDA') . "</li>";
echo "<li>GOOGLE_REDIRECT_URI del sistema: " . (getenv('GOOGLE_REDIRECT_URI') ?: 'NO DEFINIDA') . "</li>";
echo "</ul>";

echo "<h2>URLs que debería generar Laravel:</h2>";
echo "<ul>";
echo "<li>Google Redirect: https://api.fresaterra.shop/api/v1/auth/google/redirect</li>";
echo "<li>Google Callback: https://api.fresaterra.shop/api/v1/auth/google/callback</li>";
echo "<li>Frontend Success: https://fresaterra.shop/auth/callback</li>";
echo "<li>Frontend Error: https://fresaterra.shop/login</li>";
echo "</ul>";

echo "<p><strong>Nota:</strong> Elimina este archivo después de la verificación por seguridad.</p>";
?>
