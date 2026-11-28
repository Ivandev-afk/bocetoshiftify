<?php
// validar.php
// Uso:  php validar.php turnos.xml
// Paso 1: comprueba que el XML esté BIEN FORMADO.
// Paso 2: comprueba que sea VÁLIDO frente al DTD indicado en su DOCTYPE.

// Script de consola de la práctica: desde un navegador no se ejecuta
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Este script se usa desde la consola: php " . basename(__FILE__));
}

$archivo = $argv[1] ?? 'turnos.xml';

// Guardar los errores de libxml en una lista, en vez de mostrarlos como avisos
libxml_use_internal_errors(true);

$documento = new DOMDocument();

// --- Paso 1: bien formado -------------------------------------
// load() devuelve false si hay errores de sintaxis.
// LIBXML_DTDLOAD le pide que cargue también el DTD del DOCTYPE.
if (!$documento->load($archivo, LIBXML_DTDLOAD)) {
    echo "[ERROR] $archivo NO está bien formado:\n";
    foreach (libxml_get_errors() as $error) {
        echo "    línea {$error->line}: " . trim($error->message) . "\n";
    }
    exit(1);                       // si no está bien formado, no se valida
}
echo "[OK] $archivo está BIEN FORMADO.\n";
libxml_clear_errors();

// --- Paso 2: válido frente al DTD -----------------------------
if ($documento->validate()) {
    echo "[OK] $archivo es VÁLIDO según turnos.dtd.\n";
} else {
    echo "[ERROR] $archivo NO es válido según turnos.dtd:\n";
    foreach (libxml_get_errors() as $error) {
        echo "    línea {$error->line}: " . trim($error->message) . "\n";
    }
}
