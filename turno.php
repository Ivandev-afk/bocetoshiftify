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

if ($empresa) {
    $empresaId = (int) $empresa["id"];
    // Antes de mostrar cajas: una caja abandonada no se ofrece ni retiene a nadie
    liberarCajasInactivas($conexion, $empresaId);
    $esta = "turno.php?e=" . urlencode($empresa["slug"]);
    // Llegó desde «Probar demo» de la portada: se recuerda para mostrar el aviso
    // en todas las pantallas (los formularios vuelven sin ?demo=1)
    if (isset($_GET["demo"])) {
        $_SESSION["demo"][$empresaId] = true;
    }
    $esDemo = !empty($_SESSION["demo"][$empresaId]);
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
        } elseif ($accion === "confirmar" && $turno && $turno["estado"] === "atendiendo") {
            // «Ya voy»: se dejan de repetir los llamados y la caja ve «Viene en camino»
            consultar($conexion, "UPDATE turnos SET confirmado = NOW() WHERE id = ? AND confirmado IS NULL", "i", $turno["id"]);
            header("Location: $esta");
            exit;
        } elseif ($accion === "cancelar") {
            // Ya lo llamaron: no se cancela, se muestra lo que hay
            header("Location: $esta");
            exit;
        }
    }

    // Con caja elegida la atiende solo esa, a su ritmo; sin caja, las abiertas en paralelo
    $minutos = $turno
        ? minutosEstimados(minutosPorCaja($conexion, $empresaId), $turno["antes"] + 1, $turno["caja_id"] !== null ? (int) $turno["caja_id"] : null, minutosBase($conexion, $empresaId))
        : 0;

    // Clave pública para que el celular se suscriba a los avisos push
    $clavePush = "";
    if ($turno && $turno["estado"] !== "atendido") {
        require_once "includes/avisos.php";
        $clavePush = clavesVapid($conexion)["publica"];
    }

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
  <?php if ($empresa): ?>
    <!-- En iPhone, los avisos push solo funcionan si la página se añade a la pantalla de inicio -->
    <link rel="manifest" href="manifest.php?e=<?= urlencode($empresa["slug"]) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="assets/img/shiftify-logo.png">
  <?php endif; ?>
  <title><?= $empresa ? "Turno en " . htmlspecialchars($empresa["nombre"]) : "Tu turno" ?> | Shiftify</title>
  <link rel="stylesheet" href="assets/css/tokens.css?v=6">
  <link rel="stylesheet" href="assets/css/styles.css?v=15">
</head>
<body class="pagina-turno<?= $turno && $turno["estado"] === "atendiendo" && !$mostrarFormulario ? " pagina-turno--llamado" : "" ?>">
  <main class="turno">
    <article class="turno__tarjeta">
      <!-- Siempre a mano: salir sin pedir turno, o si la página no carga.
           Salir no cancela el turno: al volver con el QR sigue en su lugar. -->
      <div class="turno__arriba">
        <a class="turno__volver" href="./"><span aria-hidden="true">←</span> Volver al inicio</a>
      </div>
      <img class="marca__imagen" src="assets/img/shiftify-logo.png" alt="Shiftify">

      <?php if ($empresa): ?>
        <!-- En todas las pantallas: la persona siempre sabe en qué negocio está su turno -->
        <?php if (!empty($esDemo)): ?>
          <p class="turno__demo" role="note"><strong>Esto es una demostración.</strong> Así lo viven los clientes de tu negocio cuando escanean tu QR. <a href="./#planes">Ver planes</a></p>
        <?php endif; ?>
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

        <!-- Si aquí el aviso no puede sonar con el celular bloqueado, se dice antes de pedir el turno -->
        <p class="turno-ayuda" id="avisos-ayuda" role="note" hidden></p>

        <form class="app-formulario app-formulario--apilado" id="form-pedir" action="<?= htmlspecialchars($esta) ?>" method="POST">
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
          <p class="turno__nota">Al tomarlo, permite las notificaciones: así te avisamos aunque estés en otra app.</p>
        </form>

      <?php elseif ($turno["estado"] === "esperando"): ?>
        <h1>Tu turno, <?= htmlspecialchars($turno["nombre"]) ?></h1>
        <p class="turno__codigo"><?= codigoTurno($turno["numero"]) ?></p>
        <p class="turno__estado" id="turno-estado"><?= $turno["antes"] == 0 ? "¡Eres el siguiente!" : "En la fila" ?></p>
        <p class="turno__nota" id="turno-caja"><?= $turno["caja"] ? "Te atenderá la <strong>" . nombreCaja($turno["caja"]) . "</strong>" : "Te llamará la primera caja que se desocupe" ?></p>

        <div class="turno__datos">
          <div><strong id="turno-antes"><?= $turno["antes"] ?></strong><span id="turno-antes-texto"><?= $turno["antes"] == 1 ? "persona antes" : "personas antes" ?></span></div>
          <div><strong id="turno-minutos">~<?= $minutos ?></strong><span>minutos de espera</span></div>
        </div>

        <!-- Un toque del cliente es el permiso que piden los navegadores para sonar -->
        <div class="turno-aviso" id="turno-aviso">
          <button class="boton turno-aviso__boton" type="button" id="activar-aviso">🔔 Activar aviso con sonido</button>
          <button class="boton boton--claro turno-aviso__boton turno-aviso__prueba" type="button" id="probar-sonido" hidden>🔊 Probar sonido</button>
          <p class="turno__nota" id="aviso-estado">Te avisamos con sonido y vibración cuando estés cerca y cuando te llamen. Sube el volumen y deja la pantalla encendida.</p>
          <!-- Resultado del aviso push: si quedó activo con el celular bloqueado, o qué falló -->
          <p class="turno-aviso__push" id="aviso-push" role="status" hidden></p>
        </div>
        <p class="turno__nota">Esta página se actualiza sola, no la cierres.</p>

        <!-- Para salir de la fila: libera el lugar y deja pedir un turno nuevo -->
        <form class="turno__cancelar" action="<?= htmlspecialchars($esta) ?>" method="POST" onsubmit="return confirm('¿Cancelar tu turno <?= codigoTurno($turno["numero"]) ?>? Perderás tu lugar en la fila.')">
          <input type="hidden" name="accion" value="cancelar">
          <button type="submit">Cancelar mi turno</button>
        </form>

      <?php elseif ($turno["estado"] === "atendiendo"): ?>
        <h1 class="turno__llamado">¡TE TOCA!</h1>
        <p class="turno__codigo"><?= codigoTurno($turno["numero"]) ?></p>
        <p class="turno__estado turno__estado--ahora">Acércate a la <strong><?= $turno["caja"] ? nombreCaja($turno["caja"]) : "ventanilla" ?></strong></p>
        <?php if ($turno["confirmado"]): ?>
          <p class="turno__voy-ok">✓ Le avisamos a la caja que vas en camino.</p>
        <?php else: ?>
          <!-- Mientras no responda, la caja lo vuelve a llamar cada 30 s (hasta 3 veces) -->
          <form action="<?= htmlspecialchars($esta) ?>" method="POST">
            <input type="hidden" name="accion" value="confirmar">
            <button class="boton turno__voy" type="submit">✋ Ya voy</button>
          </form>
          <p class="turno__nota">Si no respondes, te volveremos a llamar.</p>
        <?php endif; ?>

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

  <?php if ($mostrarFormulario): ?>
    <script src="assets/js/turno-avisos.js?v=1"></script>
  <?php endif; ?>

  <?php if ($turno && !$mostrarFormulario && $turno["estado"] !== "atendido"): ?>
    <!-- Sigue la fila sin recargar: avisa con sonido, vibración y notificación -->
    <script src="assets/js/aviso-turno.js?v=7"
            data-estado-url="estado_turno.php?e=<?= urlencode($empresa["slug"]) ?>"
            data-turno-id="<?= (int) $turno["id"] ?>"
            data-estado="<?= htmlspecialchars($turno["estado"]) ?>"
            data-antes="<?= (int) $turno["antes"] ?>"
            data-llamadas="<?= (int) $turno["llamadas"] ?>"
            data-negocio="<?= htmlspecialchars($empresa["nombre"]) ?>"
            data-clave-push="<?= htmlspecialchars($clavePush) ?>"
            data-suscribir-url="suscribir.php?e=<?= urlencode($empresa["slug"]) ?>"></script>
  <?php endif; ?>
</body>
</html>
