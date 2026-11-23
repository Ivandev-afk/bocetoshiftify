<?php
// Imagen del código QR que abre la fila de una empresa: qr.php?e=salon-dona-cecilia
// La usan la ventana del QR en index.html y la tarjeta "QR de tu fila" del panel.
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

// El QR cambia cuando cambia el enlace: que el navegador no lo guarde
header("Cache-Control: no-store");

// El dibujo del QR lo genera el servicio público api.qrserver.com
header("Location: https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=" . rawurlencode($direccion));
