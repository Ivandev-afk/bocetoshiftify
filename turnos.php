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
        $turno = crearTurno($conexion, $empresaId, $nombre);
        $_SESSION["aviso"] = "Turno " . codigoTurno($turno["numero"]) . " creado para $nombre.";
    }
} elseif ($accion === "siguiente" || $accion === "finalizar") {
    // Transacción: o se hacen los dos cambios, o ninguno
    $conexion->begin_transaction();

    // 1. Cerrar el turno que se está atendiendo en esta empresa (si hay)
    consultar($conexion, "UPDATE turnos SET estado = 'atendido', finalizado = NOW() WHERE empresa_id = ? AND estado = 'atendiendo'", "i", $empresaId);

    if ($accion === "finalizar") {
        $_SESSION["aviso"] = "Atención finalizada.";
    } else {
        // 2. Llamar al primero de la fila de hoy de esta empresa
        $siguiente = consultar($conexion, "SELECT id, numero, nombre FROM turnos WHERE empresa_id = ? AND estado = 'esperando' AND DATE(creado) = CURDATE() ORDER BY numero LIMIT 1 FOR UPDATE", "i", $empresaId)->fetch_assoc();

        if ($siguiente) {
            $stmt = $conexion->prepare("UPDATE turnos SET estado = 'atendiendo', llamado = NOW() WHERE id = ?");
            $stmt->bind_param("i", $siguiente["id"]);
            $stmt->execute();
            $stmt->close();
            $_SESSION["aviso"] = "Llamando a " . codigoTurno($siguiente["numero"]) . " · " . $siguiente["nombre"] . ".";
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
