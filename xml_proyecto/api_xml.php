<?php
// api_xml.php
// Opción B: genera la fila de atención del día en XML a partir de la
// tabla turnos de MySQL, en vez de leer un archivo fijo.
// Usa la misma conexión que el resto de Shiftify (includes/conexion.php).

// Carpeta includes de Shiftify: al lado de xml_proyecto si está dentro del
// proyecto; si la carpeta se copió a otro lugar, la del proyecto original.
$includes = is_dir(__DIR__ . '/../includes')
    ? __DIR__ . '/../includes'
    : 'C:/Users/ivant/OneDrive/Documentos/Desarrollo en redes/shiftify/bocetoshiftify/includes';

require $includes . '/conexion.php';
require $includes . '/funciones.php';

// 1. Datos: turnos de hoy en orden de llegada.
//    espera   = minutos desde que pidió el turno hasta que lo llamaron (o hasta ahora)
//    duracion = minutos de atención; NULL si todavía no ha terminado
$registros = $conexion->query("
    SELECT id, nombre, estado,
           DATE_FORMAT(creado, '%H:%i')                            AS hora,
           TIMESTAMPDIFF(MINUTE, creado, COALESCE(llamado, NOW())) AS espera,
           TIMESTAMPDIFF(MINUTE, llamado, finalizado)              AS duracion
    FROM turnos
    WHERE DATE(creado) = CURDATE()
    ORDER BY id
")->fetch_all(MYSQLI_ASSOC);

// La fecha la da MySQL, igual que CURDATE() arriba (PHP puede estar en otra zona horaria)
$fecha = $conexion->query("SELECT CURDATE() AS hoy")->fetch_assoc()["hoy"];
$conexion->close();

// El DTD exige al menos un turno (turno+): sin turnos no hay respuesta válida
if (!$registros) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Todavía no hay turnos hoy. Pide uno en turno.php.";
    exit;
}

header('Content-Type: application/xml; charset=UTF-8');

// 2. Documento con su DOCTYPE (el contrato que el cliente usará para validar)
$implementacion = new DOMImplementation();
$doctype   = $implementacion->createDocumentType('turnos', '', 'turnos.dtd');
$documento = $implementacion->createDocument('', '', $doctype);
$documento->encoding     = 'UTF-8';
$documento->formatOutput = true;           // sangría para que sea legible

// 3. Elemento raíz con sus atributos
$raiz = $documento->createElement('turnos');
$raiz->setAttribute('sede', 'Sucursal Centro');
$raiz->setAttribute('fecha', $fecha);
$documento->appendChild($raiz);

// 4. Un elemento <turno> por cada fila, respetando el ORDEN que exige el DTD
foreach ($registros as $fila) {
    $turno = $documento->createElement('turno');
    // Un atributo ID no puede empezar por número: 7 → "T007"
    $turno->setAttribute('id', sprintf('T%03d', $fila['id']));
    $turno->setAttribute('estado', $fila['estado']);

    $campos = [
        'codigo'  => codigoTurno((int) $fila['id']),
        'cliente' => $fila['nombre'],
        'hora'    => $fila['hora'],
        'espera'  => $fila['espera'],
    ];

    // createElement + textContent escapa automáticamente los caracteres especiales (& < >)
    foreach ($campos as $campo => $valor) {
        $hijo = $documento->createElement($campo);
        $hijo->textContent = (string) $valor;
        if ($campo === 'espera') {
            $hijo->setAttribute('unidad', 'min');
        }
        $turno->appendChild($hijo);
    }

    if ($fila['duracion'] !== null) {      // elemento opcional (duracion?)
        $hijo = $documento->createElement('duracion');
        $hijo->textContent = (string) $fila['duracion'];
        $hijo->setAttribute('unidad', 'min');
        $turno->appendChild($hijo);
    }

    $raiz->appendChild($turno);
}

// 5. Enviar el XML como respuesta
echo $documento->saveXML();
