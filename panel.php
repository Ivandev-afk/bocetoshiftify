<?php
require "includes/sesion.php";
require "includes/conexion.php";
require "includes/funciones.php";

// Avisos que deja turnos.php; se muestran una sola vez
$aviso = $_SESSION["aviso"] ?? "";
$error = $_SESSION["error"] ?? "";
unset($_SESSION["aviso"], $_SESSION["error"]);

// Todo se filtra por la empresa de la sesión: cada negocio ve solo su fila

// El asesor sigue aquí (el panel se recarga cada 20 s); y las cajas de quien se
// fue sin pulsar «Dejar caja» se liberan para que nadie espere en una caja vacía
marcarActividad($conexion, (int) $_SESSION["usuario_id"]);
if ($liberadas = liberarCajasInactivas($conexion, $empresaId)) {
    $aviso = trim("$aviso " . ($liberadas == 1 ? "Se liberó 1 caja" : "Se liberaron $liberadas cajas") . " sin actividad en " . MINUTOS_CAJA_INACTIVA . " min; su fila pasó a «la primera libre».");
}

// Las cajas, con su asesor, el turno que atienden y cuántos atendieron hoy
$cajas = consultar($conexion, "
    SELECT c.id, c.numero, c.usuario_id, c.minutos, u.nombre AS asesor,
           t.numero AS turno, t.nombre, DATE_FORMAT(t.llamado, '%H:%i') AS hora,
           (SELECT COUNT(*) FROM turnos a
            WHERE a.caja_id = c.id AND a.estado = 'atendido' AND a.finalizado >= CURDATE() AND a.finalizado < CURDATE() + INTERVAL 1 DAY) AS atendidos,
           (SELECT COUNT(*) FROM turnos e
            WHERE e.caja_id = c.id AND e.estado = 'esperando' AND e.creado >= CURDATE() AND e.creado < CURDATE() + INTERVAL 1 DAY) AS en_fila
    FROM cajas c
    LEFT JOIN usuarios u ON u.id = c.usuario_id
    LEFT JOIN turnos t   ON t.caja_id = c.id AND t.estado = 'atendiendo'
    WHERE c.empresa_id = ?
    ORDER BY c.numero
", "i", $empresaId)->fetch_all(MYSQLI_ASSOC);

$usuarioId = (int) $_SESSION["usuario_id"];
$miCaja = null;
foreach ($cajas as $caja) {
    if ((int) $caja["usuario_id"] === $usuarioId) {
        $miCaja = $caja;
    }
}

// Personas en fila hoy, la más antigua primero.
// Quien quedó esperando otro día ya se fue: no entra en la fila de hoy.
// Cada turno trae la caja que eligió (null = la primera libre).
$fila = consultar($conexion, "
    SELECT t.numero, t.nombre, DATE_FORMAT(t.creado, '%H:%i') AS hora, c.numero AS caja
    FROM turnos t
    LEFT JOIN cajas c ON c.id = t.caja_id
    WHERE t.empresa_id = ? AND t.estado = 'esperando' AND t.creado >= CURDATE() AND t.creado < CURDATE() + INTERVAL 1 DAY
    ORDER BY t.numero
", "i", $empresaId)->fetch_all(MYSQLI_ASSOC);

// Resumen del día
$hoy = consultar($conexion, "
    SELECT DATE_FORMAT(CURDATE(), '%d/%m/%Y') AS fecha, COUNT(*) AS atendidos
    FROM turnos
    WHERE empresa_id = ? AND estado = 'atendido' AND finalizado >= CURDATE() AND finalizado < CURDATE() + INTERVAL 1 DAY
", "i", $empresaId)->fetch_assoc();

// Minutos por atención de cada caja abierta (real, del asesor o del negocio)
$porCaja = minutosPorCaja($conexion, $empresaId);
$minutosBase = minutosBase($conexion, $empresaId);
$conexion->close();

$esperando = count($fila);
// Quienes esperan «la primera libre»: cualquier caja abierta los puede llamar
$sinCaja = count(array_filter($fila, fn($t) => $t["caja"] === null));
$enAtencion = count(array_filter($cajas, fn($c) => $c["turno"] !== null));
$paginaActual = "panel";
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0F172A">
  <title>Panel de atención | Shiftify</title>
  <link rel="stylesheet" href="assets/css/tokens.css?v=6">
  <link rel="stylesheet" href="assets/css/styles.css?v=11">
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
    <?php endif; ?>
    <?php if ($error): ?>
      <p class="app-aviso app-aviso--error" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <section class="app-metricas" aria-label="Resumen de la fila" id="vivo-metricas" data-vivo>
      <div class="app-metrica app-metrica--principal">
        <span>Personas esperando</span>
        <strong><?= sprintf("%02d", $esperando) ?></strong>
      </div>
      <div class="app-metrica">
        <span>En atención</span>
        <strong><?= sprintf("%02d", $enAtencion) ?></strong>
      </div>
      <div class="app-metrica">
        <span>Atendidos hoy</span>
        <strong><?= sprintf("%02d", $hoy["atendidos"]) ?></strong>
      </div>
      <div class="app-metrica">
        <span>Espera estimada</span>
        <strong><?= minutosEstimados($porCaja, $esperando, null, $minutosBase) ?><small> min</small></strong>
      </div>
    </section>

    <section class="app-cajas" aria-labelledby="cajas-titulo" id="vivo-cajas" data-vivo>
      <div class="app-cajas__cabecera">
        <h2 id="cajas-titulo">Cajas</h2>
        <p class="app-nota">
          <?php if ($miCaja): ?>
            Atiendes en <strong><?= nombreCaja($miCaja["numero"]) ?></strong>. Cada caja llama a una persona a la vez y nunca a la misma que otra.
          <?php else: ?>
            Toma una caja libre para empezar a llamar turnos. Cada asesor atiende en una sola caja.
          <?php endif; ?>
        </p>
      </div>

      <div class="app-cajas__grilla">
        <?php foreach ($cajas as $caja):
            $mia   = (int) $caja["usuario_id"] === $usuarioId;
            $libre = $caja["usuario_id"] === null;
            $clase = $mia ? " app-caja--mia" : ($libre ? " app-caja--libre" : "");
        ?>
          <article class="app-caja<?= $clase ?>" aria-labelledby="caja-<?= $caja["id"] ?>">
            <header class="app-caja__cabecera">
              <h3 id="caja-<?= $caja["id"] ?>"><?= nombreCaja($caja["numero"]) ?></h3>
              <span class="app-caja__estado"><?= $mia ? "Tu caja" : ($libre ? "Libre" : "Ocupada") ?></span>
            </header>
            <p class="app-caja__asesor"><?= $libre ? "Sin asesor" : htmlspecialchars($caja["asesor"]) ?></p>

            <?php if ($caja["turno"] !== null): ?>
              <p class="app-actual__codigo"><?= codigoTurno($caja["turno"]) ?></p>
              <p class="app-actual__nombre"><?= htmlspecialchars($caja["nombre"]) ?></p>
              <p class="app-nota">Llamado a las <?= $caja["hora"] ?></p>
            <?php else: ?>
              <p class="app-caja__vacia"><?= $libre ? "Cerrada" : "Nadie en atención." ?></p>
            <?php endif; ?>

            <?php if (isset($porCaja[$caja["id"]])):
                $ritmo = $porCaja[$caja["id"]];
                $origen = ["real" => "promedio real de hoy", "asesor" => "lo puso el asesor", "base" => "valor del negocio"][$ritmo["origen"]];
            ?>
              <p class="app-nota app-caja__ritmo">~<?= $ritmo["minutos"] ?> min por atención · <?= $origen ?></p>
            <?php endif; ?>

            <?php if ($mia): ?>
              <!-- El asesor dice cuánto tarda; con 3 atenciones hoy manda el promedio real -->
              <form class="app-caja__minutos" action="turnos.php" method="POST">
                <input type="hidden" name="accion" value="minutos_caja">
                <input type="hidden" name="caja" value="<?= $caja["id"] ?>">
                <label for="minutos-<?= $caja["id"] ?>">Mi atención dura</label>
                <input id="minutos-<?= $caja["id"] ?>" name="minutos" type="number" min="<?= MINUTOS_MINIMOS_ATENCION ?>" max="<?= MINUTOS_MAXIMOS_EDITABLES ?>" value="<?= $caja["minutos"] ?? $minutosBase ?>" required>
                <span>min</span>
                <button class="boton boton--claro boton--pequeno" type="submit">Guardar</button>
              </form>
            <?php endif; ?>

            <p class="app-caja__pie"><?= $caja["en_fila"] ?> en su fila · <?= $caja["atendidos"] ?> <?= $caja["atendidos"] == 1 ? "atendido" : "atendidos" ?> hoy</p>

            <?php if ($mia): ?>
              <div class="app-acciones">
                <form action="turnos.php" method="POST">
                  <input type="hidden" name="accion" value="siguiente">
                  <input type="hidden" name="caja" value="<?= $caja["id"] ?>">
                  <button class="boton boton--pequeno" type="submit"<?= $caja["en_fila"] + $sinCaja ? "" : " disabled" ?>>Llamar siguiente <span aria-hidden="true">→</span></button>
                </form>
                <?php if ($caja["turno"] !== null): ?>
                  <form action="turnos.php" method="POST">
                    <input type="hidden" name="accion" value="finalizar">
                    <input type="hidden" name="caja" value="<?= $caja["id"] ?>">
                    <button class="boton boton--claro boton--pequeno" type="submit">Finalizar</button>
                  </form>
                <?php else: ?>
                  <form action="turnos.php" method="POST">
                    <input type="hidden" name="accion" value="dejar">
                    <input type="hidden" name="caja" value="<?= $caja["id"] ?>">
                    <button class="boton boton--claro boton--pequeno" type="submit">Dejar caja</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php elseif (!$libre): ?>
              <!-- Para una caja que quedó tomada por alguien que ya no está -->
              <div class="app-acciones">
                <form action="turnos.php" method="POST" onsubmit="return confirm('¿Liberar <?= nombreCaja($caja["numero"]) ?> de <?= htmlspecialchars(addslashes($caja["asesor"]), ENT_QUOTES) ?>? Su atención en curso se cerrará y quienes esperaban en ella pasarán a «la primera libre».')">
                  <input type="hidden" name="accion" value="liberar">
                  <input type="hidden" name="caja" value="<?= $caja["id"] ?>">
                  <button class="boton boton--claro boton--pequeno" type="submit">Liberar caja</button>
                </form>
              </div>
            <?php elseif ($libre && !$miCaja): ?>
              <div class="app-acciones">
                <form action="turnos.php" method="POST">
                  <input type="hidden" name="accion" value="tomar">
                  <input type="hidden" name="caja" value="<?= $caja["id"] ?>">
                  <button class="boton boton--claro boton--pequeno" type="submit">Tomar esta caja</button>
                </form>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <div class="app-columnas">
      <div class="app-columna">
        <section class="app-tarjeta" aria-labelledby="nuevo-titulo">
          <h2 id="nuevo-titulo">Nuevo turno</h2>
          <form class="app-formulario" action="turnos.php" method="POST">
            <input type="hidden" name="accion" value="crear">
            <label for="nombre">Nombre de la persona</label>
            <div class="app-formulario__fila">
              <input id="nombre" name="nombre" type="text" maxlength="100" placeholder="Ej. Laura Castro" required>
              <button class="boton" type="submit">Dar turno</button>
            </div>
            <label for="caja-nuevo">Caja</label>
            <select id="caja-nuevo" name="caja">
              <option value="0">La primera libre</option>
              <?php foreach ($cajas as $caja): if ($caja["usuario_id"] === null) continue; ?>
                <option value="<?= $caja["id"] ?>"><?= nombreCaja($caja["numero"]) ?> · <?= $caja["en_fila"] ?> en fila</option>
              <?php endforeach; ?>
            </select>
          </form>
        </section>

        <section class="app-tarjeta" aria-labelledby="ritmo-titulo">
          <h2 id="ritmo-titulo">Tiempo por atención</h2>
          <p class="app-nota">Valor inicial del negocio para estimar la espera. Cada asesor puede poner el suyo en su caja, y con <?= ATENCIONES_PARA_PROMEDIO ?> atenciones en el día la caja usa su promedio real.</p>
          <form class="app-formulario" action="turnos.php" method="POST">
            <input type="hidden" name="accion" value="minutos_base">
            <label for="minutos-base">Minutos por atención</label>
            <div class="app-formulario__fila">
              <input id="minutos-base" name="minutos" type="number" min="<?= MINUTOS_MINIMOS_ATENCION ?>" max="<?= MINUTOS_MAXIMOS_EDITABLES ?>" value="<?= $minutosBase ?>" required>
              <button class="boton boton--claro" type="submit">Guardar</button>
            </div>
          </form>
        </section>

        <section class="app-tarjeta app-qr" aria-labelledby="qr-titulo">
          <h2 id="qr-titulo">QR de tu fila</h2>
          <p class="app-nota">Imprímelo y ponlo en la entrada: quien lo escanee entra a la fila de <?= htmlspecialchars($_SESSION["empresa_nombre"]) ?>.</p>
          <img src="qr.php?e=<?= urlencode($_SESSION["empresa_slug"]) ?>" width="200" height="200" alt="Código QR de la fila de <?= htmlspecialchars($_SESSION["empresa_nombre"]) ?>">
          <a class="boton boton--claro boton--pequeno app-qr__descargar" href="qr.php?e=<?= urlencode($_SESSION["empresa_slug"]) ?>&amp;descargar=1">Descargar QR para imprimir</a>
        </section>
      </div>

      <section class="app-tarjeta" aria-labelledby="fila-titulo" id="vivo-fila" data-vivo>
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
                <span class="app-turno__codigo"><?= codigoTurno($turno["numero"]) ?></span>
                <span class="app-turno__persona"><i aria-hidden="true"><?= htmlspecialchars(iniciales($turno["nombre"])) ?></i> <?= htmlspecialchars($turno["nombre"]) ?></span>
                <span class="app-turno__tiempo"><?= $turno["caja"] ? nombreCaja($turno["caja"]) : "Primera libre" ?> · <?= $turno["hora"] ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <!-- Otros asesores mueven la fila: cada 10 s se traen solo las partes que cambian
       (métricas, cajas y fila), sin recargar ni mover la página -->
  <script src="assets/js/panel-vivo.js?v=1"></script>
</body>
</html>
