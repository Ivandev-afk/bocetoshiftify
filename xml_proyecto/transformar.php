<?php
// transformar.php
// Uso:  php transformar.php
// Aplica turnos.xsl a turnos.xml y guarda reporte_turnos.html

// Script de consola de la práctica: desde un navegador no se ejecuta
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Este script se usa desde la consola: php " . basename(__FILE__));
}

$datos = new DOMDocument();
$datos->load('turnos.xml');                // el documento XML (datos)

$hoja = new DOMDocument();
$hoja->load('turnos.xsl');                 // la hoja XSLT (receta)

$procesador = new XSLTProcessor();         // el procesador XSLT
$procesador->importStylesheet($hoja);

$html = $procesador->transformToXML($datos);   // ejecuta la transformación
file_put_contents('reporte_turnos.html', $html);

echo "[OK] Transformación terminada. Abra reporte_turnos.html en el navegador.\n";
