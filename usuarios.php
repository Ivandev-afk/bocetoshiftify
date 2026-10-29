<?php
// Equipo: solo alguien con sesión puede dar de alta nuevas cuentas.
require "includes/sesion.php";
require "includes/conexion.php";
require "includes/funciones.php";

$nombre = "";
$correo = "";
$error  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. Recoger los datos
    $nombre     = trim($_POST["nombre"] ?? "");
    $correo     = trim($_POST["correo"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";
    $confirmar  = $_POST["confirmar"] ?? "";

    // 2. Validar
    if ($nombre === "" || mb_strlen($nombre) > 100) {
        $error = "Escribe un nombre de hasta 100 caracteres.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150) {
        $error = "El correo no es válido.";
    } elseif (strlen($contrasena) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } elseif ($contrasena !== $confirmar) {
        $error = "Las contraseñas no coinciden.";
    } else {
        // 3. El correo no puede estar repetido
        $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $existe = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($existe) {
            $error = "Ya existe una cuenta con ese correo.";
        } else {
            // 4. Guardar la contraseña cifrada, nunca en texto plano
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, correo, contrasena) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nombre, $correo, $hash);
            $stmt->execute();
            $stmt->close();

            $_SESSION["aviso"] = "Cuenta creada para $nombre. Ya puede iniciar sesión con $correo.";
            header("Location: usuarios.php");
            exit;
        }
    }
}

$aviso = $_SESSION["aviso"] ?? "";
unset($_SESSION["aviso"]);

$equipo = $conexion->query("SELECT id, nombre, correo, DATE_FORMAT(creado, '%d/%m/%Y') AS alta FROM usuarios ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$conexion->close();

$paginaActual = "usuarios";
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0F172A">
  <title>Equipo | Shiftify</title>
  <link rel="stylesheet" href="assets/css/tokens.css">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="pagina-panel">
  <a class="salto" href="#contenido">Saltar al contenido</a>

  <?php require "includes/barra.php"; ?>

  <main class="contenedor app" id="contenido">
    <div class="app-cabecera">
      <p class="sobrelinea">Equipo</p>
      <h1>Cuentas de acceso</h1>
    </div>

    <?php if ($aviso): ?>
      <p class="app-aviso" role="status"><?= htmlspecialchars($aviso) ?></p>
    <?php elseif ($error): ?>
      <p class="app-aviso app-aviso--error" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <div class="app-columnas">
      <section class="app-tarjeta" aria-labelledby="nuevo-titulo">
        <h2 id="nuevo-titulo">Nuevo miembro</h2>
        <form class="app-formulario app-formulario--apilado" action="usuarios.php" method="POST">
          <label for="nombre">Nombre</label>
          <input id="nombre" name="nombre" type="text" maxlength="100" autocomplete="off" value="<?= htmlspecialchars($nombre) ?>" required>

          <label for="correo">Correo electrónico</label>
          <input id="correo" name="correo" type="email" maxlength="150" autocomplete="off" placeholder="nombre@negocio.com" value="<?= htmlspecialchars($correo) ?>" required>

          <label for="contrasena">Contraseña</label>
          <input id="contrasena" name="contrasena" type="password" minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres" required>

          <label for="confirmar">Repite la contraseña</label>
          <input id="confirmar" name="confirmar" type="password" minlength="8" autocomplete="new-password" required>

          <button class="boton" type="submit">Crear cuenta</button>
        </form>
      </section>

      <section class="app-tarjeta" aria-labelledby="equipo-titulo">
        <div class="app-tarjeta__titulo">
          <h2 id="equipo-titulo">Equipo</h2>
          <span class="app-contador"><?= count($equipo) ?></span>
        </div>

        <ul class="app-fila">
          <?php foreach ($equipo as $miembro): ?>
            <li class="app-miembro">
              <span class="app-turno__persona">
                <i aria-hidden="true"><?= htmlspecialchars(iniciales($miembro["nombre"])) ?></i>
                <span>
                  <strong><?= htmlspecialchars($miembro["nombre"]) ?><?= $miembro["id"] == $_SESSION["usuario_id"] ? " (tú)" : "" ?></strong>
                  <small><?= htmlspecialchars($miembro["correo"]) ?></small>
                </span>
              </span>
              <span class="app-turno__tiempo">Desde <?= $miembro["alta"] ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>
  </main>
</body>
</html>
