<?php
// Código visible del turno a partir de su número en la empresa: 7 → "A-007"
function codigoTurno(int $numero): string
{
    return sprintf("A-%03d", $numero);
}

// Iniciales para el avatar: "Laura Castro" → "LC"
function iniciales(string $nombre): string
{
    $partes = preg_split("/\s+/", trim($nombre));
    $letras = "";

    foreach (array_slice($partes, 0, 2) as $parte) {
        $letras .= mb_strtoupper(mb_substr($parte, 0, 1));
    }

    return $letras;
}

// Ejecuta una consulta preparada; devuelve el resultado de un SELECT o false
function consultar(mysqli $conexion, string $sql, string $tipos, ...$valores): mysqli_result|false
{
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($tipos, ...$valores);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $stmt->close();

    return $resultado;
}

// Empresa a partir del slug de su QR ("salon-dona-cecilia"), o null si no existe
function empresaPorSlug(mysqli $conexion, string $slug): ?array
{
    return consultar($conexion, "SELECT id, nombre, slug FROM empresas WHERE slug = ?", "s", $slug)->fetch_assoc();
}

// Slug para la dirección del QR: "Salón Doña Cecilia" → "salon-dona-cecilia"
function crearSlug(string $nombre): string
{
    $texto = mb_strtolower(trim($nombre));
    $texto = strtr($texto, ["á" => "a", "é" => "e", "í" => "i", "ó" => "o", "ú" => "u", "ü" => "u", "ñ" => "n"]);
    $texto = trim(preg_replace("/[^a-z0-9]+/", "-", $texto), "-");

    return $texto !== "" ? substr($texto, 0, 50) : "negocio";
}

// Guarda un turno nuevo en la fila de la empresa y devuelve su id y su número.
// El número sigue la cuenta de esa empresa: cada negocio empieza en A-001.
// $cajaId: la caja que eligió la persona, o null si la atiende la primera caja libre.
function crearTurno(mysqli $conexion, int $empresaId, string $nombre, ?int $cajaId = null): array
{
    // Transacción: dos personas que piden turno a la vez no reciben el mismo número
    $conexion->begin_transaction();

    $numero = consultar($conexion, "SELECT COALESCE(MAX(numero), 0) + 1 AS siguiente FROM turnos WHERE empresa_id = ? FOR UPDATE", "i", $empresaId)->fetch_assoc()["siguiente"];

    $stmt = $conexion->prepare("INSERT INTO turnos (empresa_id, numero, nombre, caja_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $empresaId, $numero, $nombre, $cajaId);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    $conexion->commit();

    return ["id" => $id, "numero" => (int) $numero];
}

// Cajas (ventanillas) con las que nace cada empresa
const CAJAS_POR_EMPRESA = 4;

// Una atención más larga que esto se cuenta como de 60 min en el promedio:
// un turno que alguien olvidó finalizar no puede disparar la espera estimada
const MINUTOS_MAXIMOS_ATENCION = 60;

// Crea las cajas 1 a 4 de una empresa nueva
function crearCajas(mysqli $conexion, int $empresaId): void
{
    $stmt = $conexion->prepare("INSERT INTO cajas (empresa_id, numero) VALUES (?, ?)");
    for ($numero = 1; $numero <= CAJAS_POR_EMPRESA; $numero++) {
        $stmt->bind_param("ii", $empresaId, $numero);
        $stmt->execute();
    }
    $stmt->close();
}

// Cajas con un asesor atendiendo (al menos 1, para poder estimar la espera)
function cajasAbiertas(mysqli $conexion, int $empresaId): int
{
    $abiertas = consultar($conexion, "SELECT COUNT(*) AS n FROM cajas WHERE empresa_id = ? AND usuario_id IS NOT NULL", "i", $empresaId)->fetch_assoc()["n"];

    return max(1, (int) $abiertas);
}

// Duración media de una atención hoy en la empresa, en minutos (5 si aún no hay datos).
// Solo cuentan las atenciones que empezaron hoy, y cada una con un tope de 60 min.
function minutosPorTurno(mysqli $conexion, int $empresaId): int
{
    $segundos = consultar($conexion, "
        SELECT AVG(LEAST(TIMESTAMPDIFF(SECOND, llamado, finalizado), ?)) AS segundos
        FROM turnos
        WHERE empresa_id = ? AND estado = 'atendido' AND DATE(llamado) = CURDATE()
    ", "ii", MINUTOS_MAXIMOS_ATENCION * 60, $empresaId)->fetch_assoc()["segundos"];

    return $segundos !== null ? max(1, (int) round($segundos / 60)) : 5;
}

// Minutos que esperará quien tiene $personas por delante (incluida ella misma).
// Las cajas abiertas atienden en paralelo: con 2 cajas, 4 personas son 2 rondas.
function minutosDeEspera(int $personas, int $minutosPorTurno, int $cajas): int
{
    return (int) ceil($personas / max(1, $cajas)) * $minutosPorTurno;
}

// Código visible de la caja: 2 → "Caja 2"
function nombreCaja(int $numero): string
{
    return "Caja $numero";
}

// Cajas de la empresa para elegir al pedir turno: si están abiertas (tienen asesor)
// y cuántas personas esperan hoy en la fila propia de cada una
function cajasParaElegir(mysqli $conexion, int $empresaId): array
{
    return consultar($conexion, "
        SELECT c.id, c.numero, c.usuario_id IS NOT NULL AS abierta,
               (SELECT COUNT(*) FROM turnos t
                WHERE t.caja_id = c.id AND t.estado = 'esperando' AND DATE(t.creado) = CURDATE()) AS esperando
        FROM cajas c
        WHERE c.empresa_id = ?
        ORDER BY c.numero
    ", "i", $empresaId)->fetch_all(MYSQLI_ASSOC);
}

// Valida la caja elegida en un formulario: "" o "0" es «la primera caja libre» (null).
// Devuelve el id si es una caja abierta de la empresa, null si no eligió, o false si no vale.
function cajaElegida(mysqli $conexion, int $empresaId, string $valor): int|null|false
{
    $cajaId = (int) $valor;
    if ($cajaId === 0) {
        return null;
    }

    $abierta = consultar($conexion, "SELECT id FROM cajas WHERE id = ? AND empresa_id = ? AND usuario_id IS NOT NULL", "ii", $cajaId, $empresaId)->fetch_assoc();

    return $abierta ? $cajaId : false;
}
