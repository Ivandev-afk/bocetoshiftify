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

$conexion = new mysqli($servidor, $usuario, $clave, $baseDatos, $puerto);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// UTF-8 completo para tildes, ñ y emojis
$conexion->set_charset("utf8mb4");
