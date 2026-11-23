<?php
// Cerrar sesión: vaciar los datos, borrar la cookie y destruir la sesión
session_start();

// Quien sale deja su caja libre para otro asesor. Si estaba atendiendo a
// alguien, esa atención se da por terminada: nadie queda llamado en una caja vacía.
if (isset($_SESSION["usuario_id"])) {
    require "includes/conexion.php";
    $usuarioId = (int) $_SESSION["usuario_id"];
    $conexion->begin_transaction();
    $stmt = $conexion->prepare("UPDATE turnos t JOIN cajas c ON c.id = t.caja_id SET t.estado = 'atendido', t.finalizado = NOW() WHERE c.usuario_id = ? AND t.estado = 'atendiendo'");
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $stmt = $conexion->prepare("UPDATE cajas SET usuario_id = NULL WHERE usuario_id = ?");
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $conexion->commit();
    $conexion->close();
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), "", time() - 3600, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}

session_destroy();

header("Location: login.php");
exit;
