<?php
session_start();

// Si ya inició sesión, no tiene sentido mostrarle el formulario
if (isset($_SESSION["usuario_id"])) {
    header("Location: panel.php");
    exit;
}

$correo = "";
$error  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require "includes/conexion.php";

    // 1. Recoger los datos
    $correo     = trim($_POST["correo"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";

    // 2. Validar
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || $contrasena === "") {
        $error = "Escribe un correo válido y tu contraseña.";
    } else {
        // 3. Buscar al usuario con una consulta preparada
        $stmt = $conexion->prepare("SELECT id, nombre, contrasena FROM usuarios WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // 4. Comparar la contraseña con el hash guardado
        if ($usuario && password_verify($contrasena, $usuario["contrasena"])) {
            session_regenerate_id(true);
            $_SESSION["usuario_id"]     = $usuario["id"];
            $_SESSION["usuario_nombre"] = $usuario["nombre"];
            header("Location: panel.php");
            exit;
        }

        // Mismo mensaje si falla el correo o la contraseña: no revelamos cuál
        $error = "Correo o contraseña incorrectos.";
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
  <meta name="description" content="Accede al espacio de trabajo de Shiftify.">
  <title>Iniciar sesión | Shiftify</title>
  <link rel="stylesheet" href="assets/css/tokens.css">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="pagina-login">
  <a class="salto" href="#acceso">Saltar al acceso</a>

  <main class="login">
    <section class="login__visual" aria-label="Shiftify, gestión simple de turnos">
      <a class="marca marca--inversa" href="index.html">
        <img class="marca__imagen" src="assets/img/shiftify-logo.png" alt="Shiftify">
      </a>

      <div class="login__mensaje">
        <p class="insignia insignia--oscura"><span class="insignia__punto"></span> Tu espacio de trabajo</p>
        <h1>La atención<br>empieza <span>en orden.</span></h1>
        <p>Administra los turnos de tu equipo desde un solo lugar.</p>
      </div>

      <div class="login__tarjeta" aria-hidden="true">
        <div class="login__tarjeta-cabecera"><span>Turnos de hoy</span><span class="estado"><span></span> Demo</span></div>
        <div class="login__grafico"><span style="--alto: 36%"></span><span style="--alto: 55%"></span><span style="--alto: 44%"></span><span style="--alto: 72%"></span><span style="--alto: 60%"></span><span style="--alto: 88%"></span><span style="--alto: 68%"></span><span style="--alto: 100%"></span><span style="--alto: 78%"></span><span style="--alto: 92%"></span><span style="--alto: 64%"></span><span style="--alto: 84%"></span></div>
        <div class="login__tarjeta-pie"><strong>Tu equipo, en sintonía.</strong><span>Una fila más clara para todos.</span></div>
      </div>
      <span class="login__decoracion" aria-hidden="true"></span>
    </section>

    <section class="login__panel" id="acceso">
      <div class="login__panel-interior">
        <a class="login__volver" href="index.html"><span aria-hidden="true">←</span> Volver al inicio</a>

        <div class="login__formulario">
          <p class="sobrelinea">Bienvenido de nuevo</p>
          <h2 id="login-titulo">Inicia sesión</h2>
          <p class="login__ayuda">Accede a tu gestión de turnos.</p>

          <form class="login__campos" action="login.php" method="POST" aria-labelledby="login-titulo">
            <label for="correo">Correo electrónico</label>
            <input id="correo" name="correo" type="email" autocomplete="username" placeholder="nombre@negocio.com" value="<?= htmlspecialchars($correo) ?>" required>

            <label for="contrasena">Contraseña</label>
            <input id="contrasena" name="contrasena" type="password" autocomplete="current-password" placeholder="Ingresa tu contraseña" required>

            <button class="boton login__boton" type="submit">Iniciar sesión <span aria-hidden="true">→</span></button>
            <p class="login__estado<?= $error ? " login__estado--error" : "" ?>" role="status" aria-live="polite"><?= htmlspecialchars($error) ?></p>
          </form>

          <p class="login__nota">¿Aún no tienes acceso? <a href="mailto:hola@shiftify.co">Contacta a tu administrador.</a></p>
        </div>

        <p class="login__copyright">© 2026 Shiftify. Todos los derechos reservados.</p>
      </div>
    </section>
  </main>
</body>
</html>
