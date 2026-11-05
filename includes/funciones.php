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
function crearTurno(mysqli $conexion, int $empresaId, string $nombre): array
{
    // Transacción: dos personas que piden turno a la vez no reciben el mismo número
    $conexion->begin_transaction();

    $numero = consultar($conexion, "SELECT COALESCE(MAX(numero), 0) + 1 AS siguiente FROM turnos WHERE empresa_id = ? FOR UPDATE", "i", $empresaId)->fetch_assoc()["siguiente"];

    $stmt = $conexion->prepare("INSERT INTO turnos (empresa_id, numero, nombre) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $empresaId, $numero, $nombre);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    $conexion->commit();

    return ["id" => $id, "numero" => (int) $numero];
}

// Duración media de una atención hoy en la empresa, en minutos (5 si aún no hay datos)
function minutosPorTurno(mysqli $conexion, int $empresaId): int
{
    $segundos = consultar($conexion, "
        SELECT AVG(TIMESTAMPDIFF(SECOND, llamado, finalizado)) AS segundos
        FROM turnos
        WHERE empresa_id = ? AND estado = 'atendido' AND DATE(finalizado) = CURDATE()
    ", "i", $empresaId)->fetch_assoc()["segundos"];

    return $segundos ? max(1, (int) round($segundos / 60)) : 5;
}
