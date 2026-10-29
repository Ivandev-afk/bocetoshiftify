<?php
// Código visible del turno a partir de su id: 7 → "A-007"
function codigoTurno(int $id): string
{
    return sprintf("A-%03d", $id);
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

// Guarda un turno nuevo en la fila y devuelve su id
function crearTurno(mysqli $conexion, string $nombre): int
{
    $stmt = $conexion->prepare("INSERT INTO turnos (nombre) VALUES (?)");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    return $id;
}

// Duración media de una atención hoy, en minutos (5 si aún no hay datos)
function minutosPorTurno(mysqli $conexion): int
{
    $segundos = $conexion->query("
        SELECT AVG(TIMESTAMPDIFF(SECOND, llamado, finalizado)) AS segundos
        FROM turnos
        WHERE estado = 'atendido' AND DATE(finalizado) = CURDATE()
    ")->fetch_assoc()["segundos"];

    return $segundos ? max(1, (int) round($segundos / 60)) : 5;
}
