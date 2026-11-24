<?php
// Recibe las acciones del panel y vuelve a panel.php con un aviso:
//   crear               → un turno nuevo en la fila
//   tomar / dejar       → el asesor ocupa o libera una caja
//   siguiente/finalizar → la caja del asesor llama al próximo o cierra su atención
//
// Reglas de las cajas (para que varios asesores atiendan sin pisarse):
//   - cada caja la opera solo el asesor que la tomó, y cada asesor tiene una sola caja
//   - cada caja atiende a una persona a la vez
//   - llamar al siguiente bloquea la empresa: dos cajas nunca llaman a la misma persona
require "includes/sesion.php";
require "includes/conexion.php";
require "includes/funciones.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: panel.php");
    exit;
}

// Cada acción deja solo su propio mensaje: uno viejo sin mostrar no tapa al nuevo
unset($_SESSION["aviso"], $_SESSION["error"]);

$accion    = $_POST["accion"] ?? "";
$usuarioId = (int) $_SESSION["usuario_id"];
$cajaId    = (int) ($_POST["caja"] ?? 0);

// Caja de esta empresa, bloqueada hasta el final de la transacción
function cajaBloqueada(mysqli $conexion, int $cajaId, int $empresaId): ?array
{
    return consultar($conexion, "SELECT id, numero, usuario_id FROM cajas WHERE id = ? AND empresa_id = ? FOR UPDATE", "ii", $cajaId, $empresaId)->fetch_assoc();
}

// Turno que atiende ahora esa caja, si hay
function atendiendoEn(mysqli $conexion, int $cajaId): ?array
{
    return consultar($conexion, "SELECT id, numero, nombre FROM turnos WHERE caja_id = ? AND estado = 'atendiendo' FOR UPDATE", "i", $cajaId)->fetch_assoc();
}

if ($accion === "crear") {
    $nombre = trim($_POST["nombre"] ?? "");
    $cajaId = cajaElegida($conexion, $empresaId, (string) ($_POST["caja"] ?? ""));

    if ($nombre === "" || mb_strlen($nombre) > 100) {
        $_SESSION["error"] = "Escribe un nombre de hasta 100 caracteres.";
    } elseif ($cajaId === false) {
        $_SESSION["error"] = "Esa caja está cerrada. Elige una abierta o «la primera libre».";
    } else {
        $turno = crearTurno($conexion, $empresaId, $nombre, $cajaId);
        $_SESSION["aviso"] = "Turno " . codigoTurno($turno["numero"]) . " creado para $nombre.";
    }
} elseif (in_array($accion, ["tomar", "dejar", "liberar", "siguiente", "finalizar"], true)) {
    // Todo o nada. Bloquear la fila de la empresa ordena a los asesores que
    // actúan al mismo tiempo: uno termina antes de que empiece el otro.
    $conexion->begin_transaction();
    consultar($conexion, "SELECT id FROM empresas WHERE id = ? FOR UPDATE", "i", $empresaId);

    $caja = cajaBloqueada($conexion, $cajaId, $empresaId);
    $esMia = $caja && (int) $caja["usuario_id"] === $usuarioId;

    if (!$caja) {
        $_SESSION["error"] = "Esa caja no existe.";
    } elseif ($accion === "tomar") {
        $otra = consultar($conexion, "SELECT numero FROM cajas WHERE usuario_id = ?", "i", $usuarioId)->fetch_assoc();

        if ($esMia) {
            $_SESSION["aviso"] = "Ya estás en " . nombreCaja($caja["numero"]) . ".";
        } elseif ($caja["usuario_id"] !== null) {
            $_SESSION["error"] = nombreCaja($caja["numero"]) . " ya la atiende otro asesor.";
        } elseif ($otra) {
            $_SESSION["error"] = "Ya atiendes en " . nombreCaja($otra["numero"]) . ". Déjala antes de tomar otra.";
        } else {
            consultar($conexion, "UPDATE cajas SET usuario_id = ?, actividad = NOW() WHERE id = ?", "ii", $usuarioId, $caja["id"]);
            $_SESSION["aviso"] = "Tomaste " . nombreCaja($caja["numero"]) . ". Ya puedes llamar turnos.";
        }
    } elseif ($accion === "liberar") {
        // Para la caja de un asesor que se fue sin dejarla (otro la libera desde su panel)
        if ($caja["usuario_id"] === null) {
            $_SESSION["aviso"] = nombreCaja($caja["numero"]) . " ya estaba libre.";
        } else {
            soltarCaja($conexion, $caja["id"]);
            $_SESSION["aviso"] = "Liberaste " . nombreCaja($caja["numero"]) . ". Quienes esperaban en ella pasaron a «la primera libre».";
        }
    } elseif (!$esMia) {
        // Solo quien tomó la caja la opera
        $_SESSION["error"] = "Toma " . nombreCaja($caja["numero"]) . " antes de atender desde ella.";
    } elseif ($accion === "dejar") {
        if (atendiendoEn($conexion, $caja["id"])) {
            $_SESSION["error"] = "Finaliza la atención antes de dejar " . nombreCaja($caja["numero"]) . ".";
        } else {
            // Quien esperaba en esta caja no se queda atascado: pasa a «la primera libre»,
            // sin perder su lugar (el orden es por número de turno)
            soltarCaja($conexion, $caja["id"]);
            $_SESSION["aviso"] = "Dejaste " . nombreCaja($caja["numero"]) . ".";
        }
    } else {
        // 1. Cerrar la atención de esta caja (si hay)
        consultar($conexion, "UPDATE turnos SET estado = 'atendido', finalizado = NOW() WHERE caja_id = ? AND estado = 'atendiendo'", "i", $caja["id"]);

        if ($accion === "finalizar") {
            $_SESSION["aviso"] = "Atención finalizada en " . nombreCaja($caja["numero"]) . ".";
        } else {
            // 2. Llamar al primero de hoy que puede atender esta caja: quien la eligió
            //    o quien espera «la primera libre». Nunca a quien eligió otra caja.
            $siguiente = consultar($conexion, "SELECT id, numero, nombre FROM turnos WHERE empresa_id = ? AND estado = 'esperando' AND DATE(creado) = CURDATE() AND (caja_id = ? OR caja_id IS NULL) ORDER BY numero LIMIT 1 FOR UPDATE", "ii", $empresaId, $caja["id"])->fetch_assoc();

            if ($siguiente) {
                consultar($conexion, "UPDATE turnos SET estado = 'atendiendo', caja_id = ?, llamado = NOW() WHERE id = ?", "ii", $caja["id"], $siguiente["id"]);
                $_SESSION["aviso"] = nombreCaja($caja["numero"]) . " llama a " . codigoTurno($siguiente["numero"]) . " · " . $siguiente["nombre"] . ".";
            } else {
                $_SESSION["aviso"] = "No hay nadie más en la fila.";
            }
        }
    }

    $conexion->commit();
}

// Cualquier acción cuenta como actividad del asesor en su caja
marcarActividad($conexion, $usuarioId);

$conexion->close();

// Volver al panel (así recargar la página no repite la acción)
header("Location: panel.php");
exit;
