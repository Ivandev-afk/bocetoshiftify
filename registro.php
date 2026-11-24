<?php
// Registro de un negocio nuevo: crea la empresa, su primera cuenta y entra al panel.
session_start();

$negocio = "";
$nombre  = "";
$correo  = "";
$error   = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require "includes/conexion.php";
    require "includes/funciones.php";

    // 1. Recoger los datos
    $negocio    = trim($_POST["negocio"] ?? "");
    $nombre     = trim($_POST["nombre"] ?? "");
    $correo     = trim($_POST["correo"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";
    $confirmar  = $_POST["confirmar"] ?? "";

    // 2. Validar
    if ($negocio === "" || mb_strlen($negocio) > 100) {
        $error = "Escribe el nombre del negocio (hasta 100 caracteres).";
    } elseif ($nombre === "" || mb_strlen($nombre) > 100) {
        $error = "Escribe tu nombre (hasta 100 caracteres).";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150) {
        $error = "El correo no es válido.";
    } elseif (strlen($contrasena) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } elseif ($contrasena !== $confirmar) {
        $error = "Las contraseñas no coinciden.";
    } elseif (consultar($conexion, "SELECT id FROM usuarios WHERE correo = ?", "s", $correo)->num_rows > 0) {
        $error = "Ya existe una cuenta con ese correo.";
    } else {
        // 3. Slug único para el QR: si "salon-dona-cecilia" ya existe, prueba "-2", "-3"…
        $base = crearSlug($negocio);
        $slug = $base;
        for ($n = 2; empresaPorSlug($conexion, $slug); $n++) {
            $slug = "$base-$n";
        }

        // 4. Empresa, cuenta y cajas juntas: o se crea todo, o nada
        $conexion->begin_transaction();

        $stmt = $conexion->prepare("INSERT INTO empresas (nombre, slug) VALUES (?, ?)");
        $stmt->bind_param("ss", $negocio, $slug);
        $stmt->execute();
        $empresaId = $stmt->insert_id;
        $stmt->close();

        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $stmt = $conexion->prepare("INSERT INTO usuarios (empresa_id, nombre, correo, contrasena) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $empresaId, $nombre, $correo, $hash);
        $stmt->execute();
        $usuarioId = $stmt->insert_id;
        $stmt->close();

        crearCajas($conexion, $empresaId);

        $conexion->commit();

        // 5. Entrar directo al panel del negocio nuevo
        session_regenerate_id(true);
        $_SESSION["usuario_id"]     = $usuarioId;
        $_SESSION["usuario_nombre"] = $nombre;
        $_SESSION["empresa_id"]     = $empresaId;
        $_SESSION["empresa_nombre"] = $negocio;
        $_SESSION["empresa_slug"]   = $slug;
        $_SESSION["aviso"] = "¡Listo! $negocio ya tiene su fila. Imprime el QR de abajo y ponlo en la entrada.";
        header("Location: panel.php");
        exit;
    }

    $conexion->close();
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0F172A">
  <meta name="description" content="Registra tu negocio en Shiftify.">
  <title>Registra tu negocio | Shiftify</title>
  <link rel="stylesheet" href="assets/css/tokens.css?v=6">
  <link rel="stylesheet" href="assets/css/styles.css?v=9">
</head>
<body class="pagina-login">
  <a class="salto" href="#acceso">Saltar al registro</a>

  <main class="login">
    <section class="login__visual" aria-label="Shiftify, gestión simple de turnos">
      <a class="marca marca--inversa" href="index.html">
        <img class="marca__imagen" src="assets/img/shiftify-logo.png" alt="Shiftify">
      </a>

      <div class="login__mensaje">
        <p class="insignia insignia--oscura"><span class="insignia__punto"></span> Tu negocio, tu fila</p>
        <h1>Cada negocio<br>con <span>su propia fila.</span></h1>
        <p>Tu QR, tus turnos y tu equipo, separados de los demás negocios.</p>
      </div>
      <span class="login__decoracion" aria-hidden="true"></span>
    </section>

    <section class="login__panel" id="acceso">
      <div class="login__panel-interior">
        <a class="login__volver" href="login.php"><span aria-hidden="true">←</span> Ya tengo cuenta</a>

        <div class="login__formulario">
          <p class="sobrelinea">Empieza gratis</p>
          <h2 id="registro-titulo">Registra tu negocio</h2>
          <p class="login__ayuda">Tú serás la primera cuenta; después puedes sumar a tu equipo.</p>

          <form class="login__campos" action="registro.php" method="POST" aria-labelledby="registro-titulo">
            <label for="negocio">Nombre del negocio</label>
            <input id="negocio" name="negocio" type="text" maxlength="100" autocomplete="organization" value="<?= htmlspecialchars($negocio) ?>" required>

            <label for="nombre">Tu nombre</label>
            <input id="nombre" name="nombre" type="text" maxlength="100" autocomplete="name" value="<?= htmlspecialchars($nombre) ?>" required>

            <label for="correo">Correo electrónico</label>
            <input id="correo" name="correo" type="email" maxlength="150" autocomplete="username" placeholder="nombre@negocio.com" value="<?= htmlspecialchars($correo) ?>" required>

            <label for="contrasena">Contraseña</label>
            <input id="contrasena" name="contrasena" type="password" minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres" required>

            <label for="confirmar">Repite la contraseña</label>
            <input id="confirmar" name="confirmar" type="password" minlength="8" autocomplete="new-password" required>

            <button class="boton login__boton" type="submit">Crear mi negocio <span aria-hidden="true">→</span></button>
            <p class="login__estado<?= $error ? " login__estado--error" : "" ?>" role="status" aria-live="polite"><?= htmlspecialchars($error) ?></p>
          </form>
        </div>

        <p class="login__copyright">© 2026 Shiftify. Todos los derechos reservados.</p>
      </div>
    </section>
  </main>
</body>
</html>
