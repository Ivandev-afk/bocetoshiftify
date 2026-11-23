<?php
// Conexión a MySQL.
// En el hosting (KyraCloud) los datos llegan por variables de entorno, que se
// configuran en su panel: así la contraseña nunca queda publicada en GitHub.
// Si no existen, se usan los valores de XAMPP: usuario root sin contraseña.
$servidor  = getenv("DB_HOST") ?: "localhost";
$usuario   = getenv("DB_USER") ?: "root";
$clave     = getenv("DB_PASSWORD") ?: "";
$baseDatos = getenv("DB_NAME") ?: "shiftify";
$puerto    = (int) (getenv("DB_PORT") ?: 3306);

// Desde PHP 8.1 un fallo al conectar lanza una excepción (en 8.0 solo deja
// connect_error): se atienden los dos casos igual.
try {
    $conexion = new mysqli($servidor, $usuario, $clave, $baseDatos, $puerto);
    $fallo = $conexion->connect_error;
} catch (mysqli_sql_exception $e) {
    $fallo = $e->getMessage();
}

if ($fallo) {
    // El detalle va al log del servidor; el visitante no ve rutas ni datos internos
    error_log("Shiftify: no se pudo conectar a MySQL en $servidor:$puerto: $fallo");
    http_response_code(503);
    die("Error de conexión con la base de datos. Intenta de nuevo en unos minutos.");
}

// UTF-8 completo para tildes, ñ y emojis
$conexion->set_charset("utf8mb4");
