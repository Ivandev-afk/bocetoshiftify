<?php
// Recibe las acciones del panel (crear, llamar al siguiente, finalizar)
// y vuelve a panel.php con un aviso.
require "includes/sesion.php";
require "includes/conexion.php";
require "includes/funciones.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: panel.php");
    exit;
}

$accion = $_POST["accion"] ?? "";

if ($accion === "crear") {
    $nombre = trim($_POST["nombre"] ?? "");

    if ($nombre === "" || mb_strlen($nombre) > 100) {
        $_SESSION["error"] = "Escribe un nombre de hasta 100 caracteres.";
    } else {
        $id = crearTurno($conexion, $nombre);
        $_SESSION["aviso"] = "Turno " . codigoTurno($id) . " creado para $nombre.";
    }
} elseif ($accion === "siguiente" || $accion === "finalizar") {
    // Transacción: o se hacen los dos cambios, o ninguno
    $conexion->begin_transaction();

    // 1. Cerrar el turno que se está atendiendo (si hay)
    $conexion->query("UPDATE turnos SET estado = 'atendido', finalizado = NOW() WHERE estado = 'atendiendo'");

    if ($accion === "finalizar") {
        $_SESSION["aviso"] = "Atención finalizada.";
    } else {
        // 2. Llamar al primero de la fila
        $siguiente = $conexion->query("SELECT id, nombre FROM turnos WHERE estado = 'esperando' ORDER BY id LIMIT 1 FOR UPDATE")->fetch_assoc();

        if ($siguiente) {
            $stmt = $conexion->prepare("UPDATE turnos SET estado = 'atendiendo', llamado = NOW() WHERE id = ?");
            $stmt->bind_param("i", $siguiente["id"]);
            $stmt->execute();
            $stmt->close();
            $_SESSION["aviso"] = "Llamando a " . codigoTurno($siguiente["id"]) . " · " . $siguiente["nombre"] . ".";
        } else {
            $_SESSION["aviso"] = "No hay nadie más en la fila.";
        }
    }

    $conexion->commit();
}

$conexion->close();

// Volver al panel (así recargar la página no repite la acción)
header("Location: panel.php");
exit;
