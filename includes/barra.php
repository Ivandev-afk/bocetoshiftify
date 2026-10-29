<?php
// Barra superior de las páginas privadas.
// Antes de incluirla, cada página define $paginaActual ("panel" o "usuarios").
$pestanas = [
    "panel"    => ["panel.php", "Fila de atención"],
    "usuarios" => ["usuarios.php", "Equipo"],
];
?>
<header class="barra">
  <div class="contenedor barra__interior">
    <a class="marca" href="index.html" aria-label="Shiftify, inicio">
      <img class="marca__imagen" src="assets/img/shiftify-logo.png" alt="Shiftify">
    </a>

    <div class="barra__acciones">
      <span class="app-usuario">Hola, <strong><?= htmlspecialchars($_SESSION["usuario_nombre"]) ?></strong></span>
      <a class="boton boton--claro boton--pequeno" href="salir.php">Cerrar sesión</a>
    </div>
  </div>
</header>

<nav class="contenedor app-pestanas" aria-label="Secciones del panel">
  <?php foreach ($pestanas as $clave => [$enlace, $texto]): ?>
    <a href="<?= $enlace ?>"<?= $clave === $paginaActual ? ' aria-current="page"' : "" ?>><?= $texto ?></a>
  <?php endforeach; ?>
  <a href="turno.php" target="_blank" rel="noopener">Vista del cliente <span aria-hidden="true">↗</span></a>
</nav>
