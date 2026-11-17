<?php
// Página pública que abre el QR: el cliente pide su turno y sigue la fila.
// El QR de cada empresa trae su slug: turno.php?e=salon-dona-cecilia
// El turno de cada visitante se recuerda en su sesión, uno por empresa.
session_start();
require "includes/conexion.php";
require "includes/funciones.php";

$nombre = "";
$error  = "";

$empresa = empresaPorSlug($conexion, $_GET["e"] ?? "");
$turno   = null;

// Busca el turno guardado en la sesión para esta empresa, con cuántas personas tiene delante
function turnoDeLaSesion(mysqli $conexion, int $empresaId): ?array
{
    if (!isset($_SESSION["mis_turnos"][$empresaId])) {
        return null;
    }

    return consultar($conexion, "
        SELECT t.numero, t.nombre, t.estado,
               (SELECT COUNT(*) FROM turnos f
                WHERE f.empresa_id = t.empresa_id AND f.estado = 'esperando' AND f.numero < t.numero) AS antes
        FROM turnos t
        WHERE t.id = ? AND t.empresa_id = ?
    ", "ii", $_SESSION["mis_turnos"][$empresaId], $empresaId)->fetch_assoc();
}

if ($empresa) {
    $empresaId = (int) $empresa["id"];
    $esta = "turno.php?e=" . urlencode($empresa["slug"]);
    $turno = turnoDeLaSesion($conexion, $empresaId);

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $accion = $_POST["accion"] ?? "";

        if ($accion === "pedir" && (!$turno || $turno["estado"] === "atendido")) {
            // Solo un turno activo por persona en cada empresa
            $nombre = trim($_POST["nombre"] ?? "");

            if ($nombre === "" || mb_strlen($nombre) > 100) {
                $error = "Escribe tu nombre (hasta 100 caracteres).";
            } else {
                $_SESSION["mis_turnos"][$empresaId] = crearTurno($conexion, $empresaId, $nombre)["id"];
                header("Location: $esta");
                exit;
            }
        } elseif ($accion === "nuevo" && $turno && $turno["estado"] === "atendido") {
            unset($_SESSION["mis_turnos"][$empresaId]);
            header("Location: $esta");
            exit;
        }
    }

    $minutos = $turno ? ($turno["antes"] + 1) * minutosPorTurno($conexion, $empresaId) : 0;
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
  <link rel="stylesheet" href="assets/css/tokens.css?v=4">
  <link rel="stylesheet" href="assets/css/styles.css?v=4">
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
        <?php endif; ?>

        <form class="app-formulario app-formulario--apilado" action="<?= htmlspecialchars($esta) ?>" method="POST">
          <input type="hidden" name="accion" value="pedir">
          <label for="nombre">Tu nombre</label>
          <input id="nombre" name="nombre" type="text" maxlength="100" autocomplete="name" placeholder="Tu nombre" value="<?= htmlspecialchars($nombre) ?>" required>
          <button class="boton" type="submit">Tomar turno <span aria-hidden="true">→</span></button>
        </form>

      <?php elseif ($turno["estado"] === "esperando"): ?>
        <h1>Tu turno, <?= htmlspecialchars($turno["nombre"]) ?></h1>
        <p class="turno__codigo"><?= codigoTurno($turno["numero"]) ?></p>
        <p class="turno__estado"><?= $turno["antes"] == 0 ? "¡Eres el siguiente!" : "En la fila" ?></p>

        <div class="turno__datos">
          <div><strong><?= $turno["antes"] ?></strong><span><?= $turno["antes"] == 1 ? "persona antes" : "personas antes" ?></span></div>
          <div><strong>~<?= $minutos ?></strong><span>minutos de espera</span></div>
        </div>
        <p class="turno__nota">Esta página se actualiza sola, no la cierres.</p>

      <?php elseif ($turno["estado"] === "atendiendo"): ?>
        <h1>¡Es tu turno!</h1>
        <p class="turno__codigo"><?= codigoTurno($turno["numero"]) ?></p>
        <p class="turno__estado turno__estado--ahora">Acércate a la ventanilla</p>

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
