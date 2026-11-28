<?php
// consultar.php
// Uso:  php consultar.php "expresión XPath"
// Ejemplo: php consultar.php "//turno/cliente/text()"

// Script de consola de la práctica: desde un navegador no se ejecuta
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Este script se usa desde la consola: php " . basename(__FILE__));
}

$expresion = $argv[1] ?? '';

$documento = new DOMDocument();
$documento->load('turnos.xml');

$xpath     = new DOMXPath($documento);     // motor de consultas XPath
$resultado = @$xpath->evaluate($expresion); // evalúa la expresión

echo "Consulta: $expresion\n";
if ($resultado === false) {                // expresión mal escrita
    echo "[ERROR] La expresión XPath no es válida.\n";
} elseif ($resultado instanceof DOMNodeList) {   // conjunto de nodos
    echo "Nodos encontrados: {$resultado->length}\n";
    foreach ($resultado as $nodo) {
        if ($nodo instanceof DOMElement) {
            echo "   " . $documento->saveXML($nodo) . "\n";   // elemento completo
        } else {
            echo "   " . $nodo->nodeValue . "\n";             // texto o atributo
        }
    }
} else {                                   // número, texto o booleano
    echo "Resultado: " . var_export($resultado, true) . "\n";
}
