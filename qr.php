<?php
// Imagen del código QR que abre la fila de una empresa: qr.php?e=salon-dona-cecilia
// La usan la ventana del QR en index.html y la tarjeta "QR de tu fila" del panel.
// Devuelve un SVG dibujado en el propio servidor (ver includes/qr.php).
// 1. Si publicar.ps1 está abierto, el QR usa el enlace público de internet:
//    funciona desde cualquier celular, con datos o con cualquier wifi.
// 2. Si no, y la página se abrió desde localhost, usa la IP del equipo en la red:
//    solo funciona con el celular conectado al mismo wifi.
$slug = $_GET["e"] ?? "";

if (!preg_match('/^[a-z0-9-]{1,60}$/', $slug)) {
    http_response_code(400);
    exit;
}

// publicar.ps1 escribe aquí el enlace público y renueva la fecha del archivo
// cada 5 segundos; si lleva más de 30 sin tocarse, el enlace ya se apagó.
$archivoPublico = __DIR__ . "/includes/url_publica.txt";
clearstatcache();
$vivo = is_file($archivoPublico) && filemtime($archivoPublico) > time() - 30;
$publica = $vivo ? trim(file_get_contents($archivoPublico)) : "";

if (preg_match('#^https://[a-z0-9-]+\.trycloudflare\.com$#', $publica)) {
    $direccion = "$publica/turno.php?e=$slug";
    $esquema = "https";
} else {
    $host = $_SERVER["HTTP_HOST"];

    if (preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/', $host, $partes)) {
        $host = gethostbyname(gethostname()) . ($partes[2] ?? "");
    }

    // En el hosting la página va por https (a veces detrás de un proxy que lo avisa)
    $seguro = ($_SERVER["HTTPS"] ?? "off") !== "off"
        || ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https";
    $esquema = $seguro ? "https" : "http";

    $carpeta = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\");
    $direccion = "$esquema://$host$carpeta/turno.php?e=$slug";
}

// El QR se dibuja aquí mismo (includes/qr.php), sin depender de servicios externos
require __DIR__ . "/includes/qr.php";

header("Content-Type: image/svg+xml; charset=utf-8");
// En el hosting el enlace es fijo: se puede guardar una hora. Con el túnel temporal
// o la IP de la red local cambia, así que ahí no se guarda.
header($vivo || $esquema === "http" ? "Cache-Control: no-store" : "Cache-Control: public, max-age=3600");
// ?descargar=1 → se guarda como archivo para imprimir
if (isset($_GET["descargar"])) {
    header('Content-Disposition: attachment; filename="qr-' . $slug . '.svg"');
}
echo qrSvg($direccion);
