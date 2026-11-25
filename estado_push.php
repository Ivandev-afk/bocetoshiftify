<?php
// Qué decir en la notificación push. La llama sw.js cuando llega un aviso:
// el aviso viene sin contenido, así que el service worker pregunta aquí por el
// turno de su suscripción (no depende de cookies: sirve con el navegador cerrado).
//   POST estado_push.php  {"endpoint": "..."}  →  {"titulo": "...", "cuerpo": "...", "url": "..."}
require "includes/conexion.php";
require "includes/funciones.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

$datos = json_decode(file_get_contents("php://input"), true);
$endpoint = is_array($datos) ? (string) ($datos["endpoint"] ?? "") : "";

// El turno más reciente de esa suscripción
$fila = $endpoint === "" ? null : consultar($conexion, "
    SELECT s.turno_id, e.slug
    FROM suscripciones s
    JOIN turnos t ON t.id = s.turno_id
    JOIN empresas e ON e.id = t.empresa_id
    WHERE s.endpoint = ?
    ORDER BY s.id DESC
    LIMIT 1
", "s", $endpoint)->fetch_assoc();

$turno = $fila ? turnoPorId($conexion, (int) $fila["turno_id"]) : null;
$conexion->close();

if (!$turno) {
    echo json_encode(["titulo" => "Shiftify", "cuerpo" => "Revisa tu turno.", "url" => "./"]);
    exit;
}

$codigo = codigoTurno($turno["numero"]);
$url = "turno.php?e=" . urlencode($fila["slug"]);

if ($turno["estado"] === "atendiendo") {
    $respuesta = [
        "titulo"  => "🔔 ¡Te toca! $codigo",
        "cuerpo"  => "Acércate a la " . ($turno["caja"] ? nombreCaja($turno["caja"]) : "ventanilla") . " · " . $turno["negocio"],
        "llamado" => true,
    ];
} elseif ($turno["estado"] === "esperando") {
    $respuesta = [
        "titulo"  => "⏳ ¡Ya casi! $codigo",
        "cuerpo"  => ($turno["antes"] == 0 ? "¡Eres el siguiente!" : "Solo {$turno['antes']} " . ($turno["antes"] == 1 ? "persona" : "personas") . " antes de ti.") . " Acércate · " . $turno["negocio"],
        "llamado" => false,
    ];
} else {
    $respuesta = ["titulo" => "Shiftify", "cuerpo" => "Tu atención en " . $turno["negocio"] . " terminó. ¡Gracias!", "llamado" => false];
}

$respuesta["url"] = $url;
$respuesta["tag"] = "turno-" . $turno["id"];
echo json_encode($respuesta);
