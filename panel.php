<?php
require "includes/sesion.php";
require "includes/conexion.php";
require "includes/funciones.php";

// Avisos que deja turnos.php; se muestran una sola vez
$aviso = $_SESSION["aviso"] ?? "";
$error = $_SESSION["error"] ?? "";
unset($_SESSION["aviso"], $_SESSION["error"]);

// Turno que se está atendiendo
$actual = $conexion->query("SELECT id, nombre, DATE_FORMAT(llamado, '%H:%i') AS hora FROM turnos WHERE estado = 'atendiendo' LIMIT 1")->fetch_assoc();

// Personas en fila, la más antigua primero
$fila = $conexion->query("SELECT id, nombre, DATE_FORMAT(creado, '%H:%i') AS hora FROM turnos WHERE estado = 'esperando' ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Resumen del día
$hoy = $conexion->query("
    SELECT DATE_FORMAT(CURDATE(), '%d/%m/%Y') AS fecha, COUNT(*) AS atendidos
    FROM turnos
    WHERE estado = 'atendido' AND DATE(finalizado) = CURDATE()
")->fetch_assoc();

$minutosPorTurno = minutosPorTurno($conexion);
$conexion->close();

$esperando = count($fila);
$paginaActual = "panel";
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0F172A">
  <title>Panel de atención | Shiftify</title>
  <link rel="stylesheet" href="assets/css/tokens.css">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="pagina-panel">
  <a class="salto" href="#contenido">Saltar al contenido</a>

  <?php require "includes/barra.php"; ?>

  <main class="contenedor app" id="contenido">
    <div class="app-cabecera">
      <p class="sobrelinea">Panel de atención</p>
      <h1>Fila de hoy <span><?= $hoy["fecha"] ?></span></h1>
    </div>

    <?php if ($aviso): ?>
      <p class="app-aviso" role="status"><?= htmlspecialchars($aviso) ?></p>
    <?php elseif ($error): ?>
      <p class="app-aviso app-aviso--error" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <section class="app-metricas" aria-label="Resumen de la fila">
      <div class="app-metrica app-metrica--principal">
        <span>Personas esperando</span>
        <strong><?= sprintf("%02d", $esperando) ?></strong>
      </div>
      <div class="app-metrica">
        <span>Atendidos hoy</span>
        <strong><?= sprintf("%02d", $hoy["atendidos"]) ?></strong>
      </div>
      <div class="app-metrica">
        <span>Espera estimada</span>
        <strong><?= $esperando * $minutosPorTurno ?><small> min</small></strong>
      </div>
    </section>

    <div class="app-columnas">
      <div class="app-columna">
        <section class="app-tarjeta" aria-labelledby="actual-titulo">
          <h2 id="actual-titulo">Atendiendo ahora</h2>

          <?php if ($actual): ?>
            <p class="app-actual__codigo"><?= codigoTurno($actual["id"]) ?></p>
            <p class="app-actual__nombre"><?= htmlspecialchars($actual["nombre"]) ?></p>
            <p class="app-nota">Llamado a las <?= $actual["hora"] ?></p>
          <?php else: ?>
            <p class="app-nota app-vacio">Nadie en atención.</p>
          <?php endif; ?>

          <div class="app-acciones">
            <form action="turnos.php" method="POST">
              <input type="hidden" name="accion" value="siguiente">
              <button class="boton" type="submit"<?= $esperando ? "" : " disabled" ?>>Llamar siguiente <span aria-hidden="true">→</span></button>
            </form>
            <?php if ($actual): ?>
              <form action="turnos.php" method="POST">
                <input type="hidden" name="accion" value="finalizar">
                <button class="boton boton--claro" type="submit">Finalizar</button>
              </form>
            <?php endif; ?>
          </div>
        </section>

        <section class="app-tarjeta" aria-labelledby="nuevo-titulo">
          <h2 id="nuevo-titulo">Nuevo turno</h2>
          <form class="app-formulario" action="turnos.php" method="POST">
            <input type="hidden" name="accion" value="crear">
            <label for="nombre">Nombre de la persona</label>
            <div class="app-formulario__fila">
              <input id="nombre" name="nombre" type="text" maxlength="100" placeholder="Ej. Laura Castro" required>
              <button class="boton" type="submit">Dar turno</button>
            </div>
          </form>
        </section>
      </div>

      <section class="app-tarjeta" aria-labelledby="fila-titulo">
        <div class="app-tarjeta__titulo">
          <h2 id="fila-titulo">Siguiente en fila</h2>
          <span class="app-contador"><?= $esperando ?></span>
        </div>

        <?php if (!$fila): ?>
          <p class="app-nota app-vacio">No hay nadie esperando.</p>
        <?php else: ?>
          <ol class="app-fila">
            <?php foreach ($fila as $posicion => $turno): ?>
              <li class="app-turno">
                <span class="app-turno__codigo"><?= codigoTurno($turno["id"]) ?></span>
                <span class="app-turno__persona"><i aria-hidden="true"><?= htmlspecialchars(iniciales($turno["nombre"])) ?></i> <?= htmlspecialchars($turno["nombre"]) ?></span>
                <span class="app-turno__tiempo"><?= $turno["hora"] ?> · ~<?= ($posicion + 1) * $minutosPorTurno ?> min</span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </section>
    </div>
  </main>
</body>
</html>
