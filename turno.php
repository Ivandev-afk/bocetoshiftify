<?php
// Página pública que abre el QR: el cliente pide su turno y sigue la fila.
// El turno de cada visitante se recuerda en su sesión.
session_start();
require "includes/conexion.php";
require "includes/funciones.php";

$nombre = "";
$error  = "";

// Busca el turno guardado en la sesión, con cuántas personas tiene delante
function turnoDeLaSesion(mysqli $conexion): ?array
{
    if (!isset($_SESSION["mi_turno"])) {
        return null;
    }

    $stmt = $conexion->prepare("
        SELECT t.id, t.nombre, t.estado,
               (SELECT COUNT(*) FROM turnos f WHERE f.estado = 'esperando' AND f.id < t.id) AS antes
        FROM turnos t
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $_SESSION["mi_turno"]);
    $stmt->execute();
    $turno = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $turno;
}

$turno = turnoDeLaSesion($conexion);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    if ($accion === "pedir" && (!$turno || $turno["estado"] === "atendido")) {
        // Solo un turno activo por persona
        $nombre = trim($_POST["nombre"] ?? "");

        if ($nombre === "" || mb_strlen($nombre) > 100) {
            $error = "Escribe tu nombre (hasta 100 caracteres).";
        } else {
            $_SESSION["mi_turno"] = crearTurno($conexion, $nombre);
            header("Location: turno.php");
            exit;
        }
    } elseif ($accion === "nuevo" && $turno && $turno["estado"] === "atendido") {
        unset($_SESSION["mi_turno"]);
        header("Location: turno.php");
        exit;
    }
}

// Formulario si no tiene turno, o si pidió otro tras ser atendido y hubo un error
$mostrarFormulario = !$turno || ($turno["estado"] === "atendido" && $error);
$minutos = $turno ? ($turno["antes"] + 1) * minutosPorTurno($conexion) : 0;
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
  <title>Tu turno | Shiftify</title>
  <link rel="stylesheet" href="assets/css/tokens.css">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="pagina-turno">
  <main class="turno">
    <article class="turno__tarjeta">
      <img class="marca__imagen" src="assets/img/shiftify-logo.png" alt="Shiftify">

      <?php if ($mostrarFormulario): ?>
        <h1>Pide tu turno</h1>
        <p class="turno__nota">Sin filas ni papeles: espera donde quieras y sigue la fila desde aquí.</p>

        <?php if ($error): ?>
          <p class="app-aviso app-aviso--error" role="alert"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form class="app-formulario app-formulario--apilado" action="turno.php" method="POST">
          <input type="hidden" name="accion" value="pedir">
          <label for="nombre">Tu nombre</label>
          <input id="nombre" name="nombre" type="text" maxlength="100" autocomplete="name" placeholder="Ej. Laura Castro" value="<?= htmlspecialchars($nombre) ?>" required>
          <button class="boton" type="submit">Tomar turno <span aria-hidden="true">→</span></button>
        </form>

      <?php elseif ($turno["estado"] === "esperando"): ?>
        <h1>Tu turno, <?= htmlspecialchars($turno["nombre"]) ?></h1>
        <p class="turno__codigo"><?= codigoTurno($turno["id"]) ?></p>
        <p class="turno__estado"><?= $turno["antes"] == 0 ? "¡Eres el siguiente!" : "En la fila" ?></p>

        <div class="turno__datos">
          <div><strong><?= $turno["antes"] ?></strong><span><?= $turno["antes"] == 1 ? "persona antes" : "personas antes" ?></span></div>
          <div><strong>~<?= $minutos ?></strong><span>minutos de espera</span></div>
        </div>
        <p class="turno__nota">Esta página se actualiza sola. No la cierres.</p>

      <?php elseif ($turno["estado"] === "atendiendo"): ?>
        <h1>¡Es tu turno!</h1>
        <p class="turno__codigo"><?= codigoTurno($turno["id"]) ?></p>
        <p class="turno__estado turno__estado--ahora">Acércate a la ventanilla</p>

      <?php else: ?>
        <h1>¡Gracias, <?= htmlspecialchars($turno["nombre"]) ?>!</h1>
        <p class="turno__codigo turno__codigo--fin"><?= codigoTurno($turno["id"]) ?></p>
        <p class="turno__estado turno__estado--fin">Atención finalizada</p>

        <form action="turno.php" method="POST">
          <input type="hidden" name="accion" value="nuevo">
          <button class="boton boton--claro turno__boton" type="submit">Pedir otro turno</button>
        </form>
      <?php endif; ?>
    </article>
  </main>
</body>
</html>
