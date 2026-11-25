<?php
// Re-llamado automático. panel-vivo.js lo llama cada 10 s mientras un asesor
// tiene el panel abierto: a quien la caja llamó y no ha respondido «Ya voy»,
// se le vuelve a avisar cada 30 s, hasta 3 llamadas (ver includes/avisos.php).
require "includes/sesion.php";
require "includes/conexion.php";
require "includes/funciones.php";
require "includes/avisos.php";

session_write_close();

// Se responde de una vez: el panel no espera a que salgan los avisos
ignore_user_abort(true);
http_response_code(204);
header("Content-Length: 0");
header("Connection: close");
while (ob_get_level() > 0) {
    ob_end_flush();
}
flush();

rellamarPendientes($conexion, $empresaId);
$conexion->close();
