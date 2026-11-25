<?php
// Manifiesto web de la página del turno: permite «Añadir a pantalla de inicio»,
// que en iPhone es requisito para recibir avisos push. Abre la fila del negocio.
$slug = preg_match('/^[a-z0-9-]{1,60}$/', $_GET["e"] ?? "") ? $_GET["e"] : "";
header("Content-Type: application/manifest+json; charset=utf-8");
echo json_encode([
    "name"             => "Shiftify · Tu turno",
    "short_name"       => "Shiftify",
    "start_url"        => $slug !== "" ? "turno.php?e=$slug" : "./",
    "scope"            => "./",
    "display"          => "standalone",
    "background_color" => "#0F172A",
    "theme_color"      => "#0F172A",
    "icons"            => [["src" => "assets/img/shiftify-logo.png", "sizes" => "231x154", "type" => "image/png"]],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
