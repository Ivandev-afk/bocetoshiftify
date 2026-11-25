<?php
// «Ya voy»: el cliente confirma que oyó el llamado y va hacia la caja.
// Se dejan de repetir los avisos y el panel muestra «Viene en camino».
// Lo usa el botón de la notificación (sw.js), que solo conoce su suscripción:
//   POST confirmar.php  {"endpoint": "..."}
// (el botón de la página va por turno.php, con la sesión del visitante)
require "includes/conexion.php";
require "includes/funciones.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

$datos = json_decode(file_get_contents("php://input"), true);
$endpoint = is_array($datos) ? (string) ($datos["endpoint"] ?? "") : "";

$fila = $endpoint === "" ? null : consultar($conexion, "SELECT turno_id FROM suscripciones WHERE endpoint = ? ORDER BY id DESC LIMIT 1", "s", $endpoint)->fetch_assoc();
if ($fila) {
    consultar($conexion, "UPDATE turnos SET confirmado = NOW() WHERE id = ? AND estado = 'atendiendo' AND confirmado IS NULL", "i", $fila["turno_id"]);
}
$conexion->close();

echo json_encode(["ok" => (bool) $fila]);
