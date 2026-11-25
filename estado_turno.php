<?php
// Estado del turno del visitante, en JSON, para que turno.php se actualice sin
// recargar cada 5 s (y así no pierda el permiso de sonido que dio el cliente).
// estado_turno.php?e=shiftify → {"estado":"esperando","antes":2,"minutos":10,"caja":"Caja 2"}
session_start();
require "includes/conexion.php";
require "includes/funciones.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

$empresa = empresaPorSlug($conexion, $_GET["e"] ?? "");
if (!$empresa) {
    http_response_code(404);
    echo json_encode(["estado" => "ninguno"]);
    exit;
}

$empresaId = (int) $empresa["id"];
$turno = turnoDeLaSesion($conexion, $empresaId);
// Solo se lee la sesión: se suelta ya para no frenar otras peticiones del mismo visitante
session_write_close();

if (!$turno) {
    echo json_encode(["estado" => "ninguno"]);
    exit;
}

$minutos = $turno["estado"] === "esperando"
    ? minutosEstimados(minutosPorCaja($conexion, $empresaId), $turno["antes"] + 1, $turno["caja_id"] !== null ? (int) $turno["caja_id"] : null, minutosBase($conexion, $empresaId))
    : 0;
$conexion->close();

echo json_encode([
    "id"      => (int) $turno["id"],
    "codigo"  => codigoTurno($turno["numero"]),
    "estado"  => $turno["estado"],
    "antes"   => (int) $turno["antes"],
    "minutos" => $minutos,
    "caja"    => $turno["caja"] ? nombreCaja($turno["caja"]) : null,
    "llamadas"   => (int) $turno["llamadas"],
    "confirmado" => (bool) $turno["confirmado"],
]);
