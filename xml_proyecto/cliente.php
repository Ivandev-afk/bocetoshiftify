<?php
// cliente.php
// Simula el CLIENTE de Shiftify (por ejemplo, la pantalla de la sala de espera).
// Uso (con el servidor encendido):  php cliente.php
//   Otra URL:  php cliente.php http://localhost:8000/servicio.php
// 1) pide el XML al servidor por HTTP, 2) lo valida con el DTD local,
// 3) lo consulta con XPath.

$url = $argv[1] ?? 'http://localhost:8000/api_xml.php';

// --- 1. Solicitud HTTP al servidor ---------------------------
$respuesta = @file_get_contents($url);
if ($respuesta === false) {
    echo "[ERROR] No se pudo conectar con $url. ¿Está encendido el servidor?\n";
    exit(1);
}
echo "[OK] Respuesta recibida: " . strlen($respuesta) . " bytes.\n";

// --- 2. Analizar y validar la respuesta ----------------------
libxml_use_internal_errors(true);
$documento = new DOMDocument();
if (!$documento->loadXML($respuesta, LIBXML_DTDLOAD)) {
    echo "[ERROR] La respuesta NO está bien formada.\n";
    exit(1);
}
if (!$documento->validate()) {
    echo "[ERROR] La respuesta NO cumple el contrato (DTD). No se procesa.\n";
    foreach (libxml_get_errors() as $error) {
        echo "    línea {$error->line}: " . trim($error->message) . "\n";
    }
    exit(1);
}
echo "[OK] La respuesta es válida según turnos.dtd.\n";

// --- 3. Consultar con XPath -----------------------------------
$xpath = new DOMXPath($documento);

$ventanilla = $xpath->evaluate("string(//turno[@estado='atendiendo']/codigo)");
echo "En ventanilla: " . ($ventanilla ?: "nadie") . "\n";

echo "Turnos en la fila:\n";
foreach ($xpath->query("//turno[@estado='esperando']") as $turno) {
    $codigo  = $xpath->evaluate('string(codigo)', $turno);
    $cliente = $xpath->evaluate('string(cliente)', $turno);
    $espera  = $xpath->evaluate('string(espera)', $turno);
    echo "   - $codigo $cliente (espera: $espera min)\n";
}
