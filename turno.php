<?php
// Página pública que abre el QR: el cliente pide su turno y sigue la fila.
// El QR de cada empresa trae su slug: turno.php?e=salon-dona-cecilia
// El turno de cada visitante se recuerda en su sesión, uno por empresa.
session_start();
require "includes/conexion.php";
require "includes/funciones.php";

$nombre = "";
$error  = "";

// Aviso de una sola vez (por ejemplo, después de cancelar)
$avisoTurno = $_SESSION["turno_aviso"] ?? "";
unset($_SESSION["turno_aviso"]);

$empresa = empresaPorSlug($conexion, $_GET["e"] ?? "");
$turno   = null;

// Busca el turno guardado en la sesión para esta empresa, con cuántas personas tiene delante
function turnoDeLaSesion(mysqli $conexion, int $empresaId): ?array
{
    if (!isset($_SESSION["mis_turnos"][$empresaId])) {
        return null;
    }

    // Antes de la persona va quien llegó primero y lo puede atender su misma caja:
    // los de su caja y los que esperan «la primera caja libre».
    // Si ella no eligió caja, cualquiera de las cajas la puede llamar: cuentan todos.
    $turno = consultar($conexion, "
        SELECT t.id, t.numero, t.nombre, t.estado, t.caja_id, c.numero AS caja,
               t.estado = 'esperando' AND DATE(t.creado) < CURDATE() AS vencido,
               (SELECT COUNT(*) FROM turnos f
                WHERE f.empresa_id = t.empresa_id AND f.estado = 'esperando'
                  AND DATE(f.creado) = CURDATE() AND f.numero < t.numero
                  AND (t.caja_id IS NULL OR f.caja_id IS NULL OR f.caja_id = t.caja_id)) AS antes
        FROM turnos t
        LEFT JOIN cajas c ON c.id = t.caja_id
        WHERE t.id = ? AND t.empresa_id = ?
    ", "ii", $_SESSION["mis_turnos"][$empresaId], $empresaId)->fetch_assoc();

    // Un turno cancelado, o que quedó esperando otro día, ya no está en la fila:
    // la persona puede pedir uno nuevo
    if (!$turno || $turno["vencido"] || $turno["estado"] === "cancelado") {
        unset($_SESSION["mis_turnos"][$empresaId]);
        return null;
    }

    return $turno;
}

if ($empresa) {
    $empresaId = (int) $empresa["id"];
    // Antes de mostrar cajas: una caja abandonada no se ofrece ni retiene a nadie
    liberarCajasInactivas($conexion, $empresaId);
    $esta = "turno.php?e=" . urlencode($empresa["slug"]);
    $turno = turnoDeLaSesion($conexion, $empresaId);

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $accion = $_POST["accion"] ?? "";

        if ($accion === "pedir" && (!$turno || $turno["estado"] === "atendido")) {
            // Solo un turno activo por persona en cada empresa
            $nombre = trim($_POST["nombre"] ?? "");
            $cajaId = cajaElegida($conexion, $empresaId, (string) ($_POST["caja"] ?? ""));

            if ($nombre === "" || mb_strlen($nombre) > 100) {
                $error = "Escribe tu nombre (hasta 100 caracteres).";
            } elseif ($cajaId === false) {
                // La cerraron mientras la persona elegía
                $error = "Esa caja acaba de cerrar. Elige otra.";
            } else {
                $_SESSION["mis_turnos"][$empresaId] = crearTurno($conexion, $empresaId, $nombre, $cajaId)["id"];
                header("Location: $esta");
                exit;
            }
        } elseif ($accion === "nuevo" && $turno && $turno["estado"] === "atendido") {
            unset($_SESSION["mis_turnos"][$empresaId]);
            header("Location: $esta");
            exit;
        } elseif ($accion === "cancelar" && $turno && $turno["estado"] === "esperando") {
            // Solo mientras espera: si ya lo llamaron, la caja lo está atendiendo.
            // La condición va también en el UPDATE por si una caja lo llama en este instante.
            consultar($conexion, "UPDATE turnos SET estado = 'cancelado', finalizado = NOW() WHERE id = ? AND estado = 'esperando'", "i", $turno["id"]);
            unset($_SESSION["mis_turnos"][$empresaId]);
            $_SESSION["turno_aviso"] = "Cancelaste el turno " . codigoTurno($turno["numero"]) . ". Puedes pedir uno nuevo cuando quieras.";
            header("Location: $esta");
            exit;
        } elseif ($accion === "cancelar") {
            // Ya lo llamaron: no se cancela, se muestra lo que hay
            header("Location: $esta");
            exit;
        }
    }

    // Con caja elegida la atiende solo esa; sin caja, las abiertas atienden en paralelo
    $cajasQueAtienden = $turno && $turno["caja_id"] !== null ? 1 : cajasAbiertas($conexion, $empresaId);
    $minutos = $turno ? minutosDeEspera($turno["antes"] + 1, minutosPorTurno($conexion, $empresaId), $cajasQueAtienden) : 0;

    // Para el formulario: cada caja con su estado y su fila
    $cajas = cajasParaElegir($conexion, $empresaId);
    $cajaPedida = (int) ($_POST["caja"] ?? 0);
} else {
    // QR viejo o mal escrito: no sabemos a qué fila sumarlo
    http_response_code(404);
}

// Formulario si no tiene turno, o si pidió otro tras ser atendido y hubo un error
$mostrarFormulario = $empresa && (!$turno || ($turno["estado"] === "atendido" && $error));
$conexion->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0F172A">
  <?php if ($turno && $turno["estado"] !== "atendido"): ?>
    <!-- Recarga cada 15 s para que la posición en la fila esté al día -->
    <meta http-equiv="refresh" content="15">
  <?php endif; ?>
  <title><?= $empresa ? "Turno en " . htmlspecialchars($empresa["nombre"]) : "Tu turno" ?> | Shiftify</title>
  <link rel="stylesheet" href="assets/css/tokens.css?v=6">
  <link rel="stylesheet" href="assets/css/styles.css?v=7">
</head>
<body class="pagina-turno">
  <main class="turno">
    <article class="turno__tarjeta">
      <img class="marca__imagen" src="assets/img/shiftify-logo.png" alt="Shiftify">

      <?php if ($empresa): ?>
        <!-- En todas las pantallas: la persona siempre sabe en qué negocio está su turno -->
        <p class="turno__negocio"><span>Estás en la fila de</span> <strong><?= htmlspecialchars($empresa["nombre"]) ?></strong></p>
      <?php endif; ?>

      <?php if (!$empresa): ?>
        <h1>No encontramos este negocio</h1>
        <p class="turno__nota">Escanea otra vez el código QR que está en la entrada del lugar.</p>

      <?php elseif ($mostrarFormulario): ?>
        <h1>Pide tu turno</h1>
        <p class="turno__nota">Sin filas ni papeles: espera donde quieras y sigue la fila desde aquí.</p>

        <?php if ($error): ?>
          <p class="app-aviso app-aviso--error" role="alert"><?= htmlspecialchars($error) ?></p>
        <?php elseif ($avisoTurno): ?>
          <p class="app-aviso" role="status"><?= htmlspecialchars($avisoTurno) ?></p>
        <?php endif; ?>

        <form class="app-formulario app-formulario--apilado" action="<?= htmlspecialchars($esta) ?>" method="POST">
          <input type="hidden" name="accion" value="pedir">
          <label for="nombre">Tu nombre</label>
          <input id="nombre" name="nombre" type="text" maxlength="100" autocomplete="name" placeholder="Tu nombre" value="<?= htmlspecialchars($nombre) ?>" required>

          <!-- Cada caja abierta con su fila: la persona elige dónde esperar -->
          <fieldset class="turno-cajas">
            <legend>Elige tu caja</legend>
            <label class="turno-caja">
              <input type="radio" name="caja" value="0"<?= $cajaPedida === 0 ? " checked" : "" ?>>
              <span class="turno-caja__nombre">La primera libre</span>
              <span class="turno-caja__fila">Te llama la que se desocupe antes</span>
            </label>
            <?php foreach ($cajas as $caja): ?>
              <label class="turno-caja<?= $caja["abierta"] ? "" : " turno-caja--cerrada" ?>">
                <input type="radio" name="caja" value="<?= $caja["id"] ?>"<?= $caja["abierta"] ? "" : " disabled" ?><?= $cajaPedida === (int) $caja["id"] && $caja["abierta"] ? " checked" : "" ?>>
                <span class="turno-caja__nombre"><?= nombreCaja($caja["numero"]) ?></span>
                <span class="turno-caja__fila">
                  <?php if (!$caja["abierta"]): ?>
                    Cerrada
                  <?php elseif ($caja["esperando"] == 0): ?>
                    Nadie esperando
                  <?php else: ?>
                    <?= $caja["esperando"] ?> <?= $caja["esperando"] == 1 ? "persona esperando" : "personas esperando" ?>
                  <?php endif; ?>
                </span>
              </label>
            <?php endforeach; ?>
          </fieldset>

          <button class="boton" type="submit">Tomar turno <span aria-hidden="true">→</span></button>
        </form>

      <?php elseif ($turno["estado"] === "esperando"): ?>
        <h1>Tu turno, <?= htmlspecialchars($turno["nombre"]) ?></h1>
        <p class="turno__codigo"><?= codigoTurno($turno["numero"]) ?></p>
        <p class="turno__estado"><?= $turno["antes"] == 0 ? "¡Eres el siguiente!" : "En la fila" ?></p>
        <p class="turno__nota"><?= $turno["caja"] ? "Te atenderá la <strong>" . nombreCaja($turno["caja"]) . "</strong>" : "Te llamará la primera caja que se desocupe" ?></p>

        <div class="turno__datos">
          <div><strong><?= $turno["antes"] ?></strong><span><?= $turno["antes"] == 1 ? "persona antes" : "personas antes" ?></span></div>
          <div><strong>~<?= $minutos ?></strong><span>minutos de espera</span></div>
        </div>
        <p class="turno__nota">Esta página se actualiza sola, no la cierres.</p>

        <!-- Para salir de la fila: libera el lugar y deja pedir un turno nuevo -->
        <form class="turno__cancelar" action="<?= htmlspecialchars($esta) ?>" method="POST" onsubmit="return confirm('¿Cancelar tu turno <?= codigoTurno($turno["numero"]) ?>? Perderás tu lugar en la fila.')">
          <input type="hidden" name="accion" value="cancelar">
          <button type="submit">Cancelar mi turno</button>
        </form>

      <?php elseif ($turno["estado"] === "atendiendo"): ?>
        <h1>¡Es tu turno!</h1>
        <p class="turno__codigo"><?= codigoTurno($turno["numero"]) ?></p>
        <p class="turno__estado turno__estado--ahora">Acércate a la <?= $turno["caja"] ? nombreCaja($turno["caja"]) : "ventanilla" ?></p>

      <?php else: ?>
        <h1>¡Gracias, <?= htmlspecialchars($turno["nombre"]) ?>!</h1>
        <p class="turno__codigo turno__codigo--fin"><?= codigoTurno($turno["numero"]) ?></p>
        <p class="turno__estado turno__estado--fin">Atención finalizada</p>

        <form action="<?= htmlspecialchars($esta) ?>" method="POST">
          <input type="hidden" name="accion" value="nuevo">
          <button class="boton boton--claro turno__boton" type="submit">Pedir otro turno</button>
        </form>
      <?php endif; ?>
    </article>
  </main>
</body>
</html>
