<?php
// Conexión a MySQL. Valores por defecto de XAMPP: usuario root sin contraseña.
$servidor  = "localhost";
$usuario   = "root";
$clave     = "";
$baseDatos = "shiftify";

$conexion = new mysqli($servidor, $usuario, $clave, $baseDatos);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// UTF-8 completo para tildes, ñ y emojis
$conexion->set_charset("utf8mb4");
