<?php
// transformar.php
// Uso:  php transformar.php
// Aplica turnos.xsl a turnos.xml y guarda reporte_turnos.html

$datos = new DOMDocument();
$datos->load('turnos.xml');                // el documento XML (datos)

$hoja = new DOMDocument();
$hoja->load('turnos.xsl');                 // la hoja XSLT (receta)

$procesador = new XSLTProcessor();         // el procesador XSLT
$procesador->importStylesheet($hoja);

$html = $procesador->transformToXML($datos);   // ejecuta la transformación
file_put_contents('reporte_turnos.html', $html);

echo "[OK] Transformación terminada. Abra reporte_turnos.html en el navegador.\n";
