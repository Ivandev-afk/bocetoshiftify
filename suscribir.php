<?php
// Guarda la suscripción push del celular para su turno actual.
// La llama aviso-turno.js al tocar «Activar aviso»:
//   POST suscribir.php?e=shiftify  {"endpoint": "https://fcm.googleapis.com/..."}
session_start();
require "includes/conexion.php";
require "includes/funciones.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

$empresa = empresaPorSlug($conexion, $_GET["e"] ?? "");
$datos = json_decode(file_get_contents("php://input"), true);
$endpoint = is_array($datos) ? (string) ($datos["endpoint"] ?? "") : "";

// Solo direcciones https de un servicio de push, y no demasiado largas
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !$empresa || !preg_match('#^https://[^\s]+$#', $endpoint) || strlen($endpoint) > 600) {
    http_response_code(400);
    echo json_encode(["ok" => false]);
    exit;
}

$turno = turnoDeLaSesion($conexion, (int) $empresa["id"]);
session_write_close();

if (!$turno || !in_array($turno["estado"], ["esperando", "atendiendo"], true)) {
    http_response_code(409);
    echo json_encode(["ok" => false, "motivo" => "sin turno activo"]);
    exit;
}

// INSERT IGNORE: activar dos veces no duplica la suscripción
consultar($conexion, "INSERT IGNORE INTO suscripciones (turno_id, endpoint) VALUES (?, ?)", "is", $turno["id"], $endpoint);
$conexion->close();

echo json_encode(["ok" => true]);
