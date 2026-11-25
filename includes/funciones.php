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
    if ($tipos !== "") {
        $stmt->bind_param($tipos, ...$valores);
    }
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

// Límites de los minutos que escribe la gente
const MINUTOS_MINIMOS_ATENCION = 1;
const MINUTOS_MAXIMOS_EDITABLES = 120;

// Con cuántas atenciones terminadas hoy una caja ya usa su promedio real
const ATENCIONES_PARA_PROMEDIO = 3;

// Minutos por atención de cada caja abierta, y de dónde sale ese número:
//   1. "real"   → promedio de sus atenciones de hoy, si ya tiene al menos 3
//   2. "asesor" → lo que el asesor dijo al tomar la caja
//   3. "base"   → el valor inicial del negocio
// Devuelve [caja_id => ["minutos" => int, "origen" => string]]
function minutosPorCaja(mysqli $conexion, int $empresaId): array
{
    $filas = consultar($conexion, "
        SELECT c.id, c.minutos, e.minutos_base,
               COUNT(t.id) AS atenciones,
               AVG(LEAST(TIMESTAMPDIFF(SECOND, t.llamado, t.finalizado), ?)) AS segundos
        FROM cajas c
        JOIN empresas e ON e.id = c.empresa_id
        LEFT JOIN turnos t ON t.caja_id = c.id AND t.estado = 'atendido' AND t.llamado >= CURDATE() AND t.llamado < CURDATE() + INTERVAL 1 DAY
        WHERE c.empresa_id = ? AND c.usuario_id IS NOT NULL
        GROUP BY c.id, c.minutos, e.minutos_base
    ", "ii", MINUTOS_MAXIMOS_ATENCION * 60, $empresaId)->fetch_all(MYSQLI_ASSOC);

    $resultado = [];
    foreach ($filas as $fila) {
        if ($fila["atenciones"] >= ATENCIONES_PARA_PROMEDIO) {
            $resultado[(int) $fila["id"]] = ["minutos" => max(1, (int) round($fila["segundos"] / 60)), "origen" => "real"];
        } elseif ($fila["minutos"] !== null) {
            $resultado[(int) $fila["id"]] = ["minutos" => (int) $fila["minutos"], "origen" => "asesor"];
        } else {
            $resultado[(int) $fila["id"]] = ["minutos" => (int) $fila["minutos_base"], "origen" => "base"];
        }
    }

    return $resultado;
}

// Valor inicial del negocio (se usa si no hay ninguna caja abierta)
function minutosBase(mysqli $conexion, int $empresaId): int
{
    return (int) consultar($conexion, "SELECT minutos_base FROM empresas WHERE id = ?", "i", $empresaId)->fetch_assoc()["minutos_base"];
}

// Minutos que esperará quien tiene $personas por delante (incluida ella misma).
// - Con caja elegida: solo esa caja la atiende, a su ritmo.
// - Sin caja: las cajas abiertas atienden en paralelo, cada una a su ritmo.
//   Una caja de 5 min y otra de 10 atienden juntas 1/5 + 1/10 = 0,3 personas por minuto.
function minutosEstimados(array $porCaja, int $personas, ?int $cajaId, int $minutosBase): int
{
    if ($personas <= 0) {
        return 0;
    }
    if ($cajaId !== null && isset($porCaja[$cajaId])) {
        return $personas * $porCaja[$cajaId]["minutos"];
    }
    if (!$porCaja) {
        // Nadie atiende ahora: se estima como si abriera una caja
        return $personas * $minutosBase;
    }

    $personasPorMinuto = 0;
    foreach ($porCaja as $caja) {
        $personasPorMinuto += 1 / max(1, $caja["minutos"]);
    }

    return (int) ceil($personas / $personasPorMinuto);
}

// Minutos válidos escritos en un formulario, o null si no valen
function minutosValidos(string $valor): ?int
{
    $minutos = filter_var($valor, FILTER_VALIDATE_INT);

    return $minutos !== false && $minutos >= MINUTOS_MINIMOS_ATENCION && $minutos <= MINUTOS_MAXIMOS_EDITABLES ? $minutos : null;
}

// Código visible de la caja: 2 → "Caja 2"
function nombreCaja(int $numero): string
{
    return "Caja $numero";
}

// Una caja cuyo panel lleva este tiempo cerrado se libera sola.
// El panel abierto se recarga cada 20 s, así que un asesor presente nunca llega.
const MINUTOS_CAJA_INACTIVA = 15;

// El asesor sigue en su caja: el panel avisa en cada recarga
function marcarActividad(mysqli $conexion, int $usuarioId): void
{
    consultar($conexion, "UPDATE cajas SET actividad = NOW() WHERE usuario_id = ?", "i", $usuarioId);
}

// Suelta una caja: cierra la atención en curso, deja la caja sin asesor y pasa
// a «la primera libre» a quien esperaba en ella, sin perder su lugar en la fila.
// Usar dentro de una transacción que ya bloqueó la empresa.
function soltarCaja(mysqli $conexion, int $cajaId): void
{
    consultar($conexion, "UPDATE turnos SET estado = 'atendido', finalizado = NOW() WHERE caja_id = ? AND estado = 'atendiendo'", "i", $cajaId);
    consultar($conexion, "UPDATE turnos SET caja_id = NULL WHERE caja_id = ? AND estado = 'esperando'", "i", $cajaId);
    consultar($conexion, "UPDATE cajas SET usuario_id = NULL, actividad = NULL, minutos = NULL WHERE id = ?", "i", $cajaId);
}

// Libera las cajas de la empresa cuyo asesor se fue sin pulsar «Dejar caja»
// (cerró el celular, se le acabó la batería…). Devuelve cuántas liberó.
function liberarCajasInactivas(mysqli $conexion, int $empresaId): int
{
    $sql = "
        SELECT id FROM cajas
        WHERE empresa_id = ? AND usuario_id IS NOT NULL
          AND (actividad IS NULL OR actividad < NOW() - INTERVAL ? MINUTE)
    ";

    // Consulta rápida sin bloquear: casi siempre no hay ninguna
    if (!consultar($conexion, $sql, "ii", $empresaId, MINUTOS_CAJA_INACTIVA)->fetch_assoc()) {
        return 0;
    }

    // Se vuelve a mirar con la empresa bloqueada: el asesor pudo volver justo ahora
    $conexion->begin_transaction();
    consultar($conexion, "SELECT id FROM empresas WHERE id = ? FOR UPDATE", "i", $empresaId);
    $inactivas = consultar($conexion, $sql, "ii", $empresaId, MINUTOS_CAJA_INACTIVA)->fetch_all(MYSQLI_ASSOC);
    foreach ($inactivas as $caja) {
        soltarCaja($conexion, (int) $caja["id"]);
    }
    $conexion->commit();

    return count($inactivas);
}

// Cajas de la empresa para elegir al pedir turno: si están abiertas (tienen asesor)
// y cuántas personas esperan hoy en la fila propia de cada una
function cajasParaElegir(mysqli $conexion, int $empresaId): array
{
    return consultar($conexion, "
        SELECT c.id, c.numero, c.usuario_id IS NOT NULL AS abierta,
               (SELECT COUNT(*) FROM turnos t
                WHERE t.caja_id = c.id AND t.estado = 'esperando' AND t.creado >= CURDATE() AND t.creado < CURDATE() + INTERVAL 1 DAY) AS esperando
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

// Turno guardado en la sesión del visitante para esta empresa (lo usan turno.php y
// estado_turno.php), con cuántas personas tiene delante
// Un turno con su caja, si ya venció y cuántas personas tiene delante.
// $empresaId = null: no se filtra por empresa (lo usa el aviso push, que solo conoce el turno).
function turnoPorId(mysqli $conexion, int $turnoId, ?int $empresaId = null): ?array
{
    // Antes de la persona va quien llegó primero y lo puede atender su misma caja:
    // los de su caja y los que esperan «la primera caja libre».
    // Si ella no eligió caja, cualquiera de las cajas la puede llamar: cuentan todos.
    return consultar($conexion, "
        SELECT t.id, t.empresa_id, t.numero, t.nombre, t.estado, t.caja_id, c.numero AS caja, e.nombre AS negocio,
               t.estado = 'esperando' AND t.creado < CURDATE() AS vencido,
               (SELECT COUNT(*) FROM turnos f
                WHERE f.empresa_id = t.empresa_id AND f.estado = 'esperando'
                  AND f.creado >= CURDATE() AND f.creado < CURDATE() + INTERVAL 1 DAY AND f.numero < t.numero
                  AND (t.caja_id IS NULL OR f.caja_id IS NULL OR f.caja_id = t.caja_id)) AS antes
        FROM turnos t
        JOIN empresas e ON e.id = t.empresa_id
        LEFT JOIN cajas c ON c.id = t.caja_id
        WHERE t.id = ? AND (? IS NULL OR t.empresa_id = ?)
    ", "iii", $turnoId, $empresaId, $empresaId)->fetch_assoc();
}

function turnoDeLaSesion(mysqli $conexion, int $empresaId): ?array
{
    if (!isset($_SESSION["mis_turnos"][$empresaId])) {
        return null;
    }

    $turno = turnoPorId($conexion, (int) $_SESSION["mis_turnos"][$empresaId], $empresaId);

    // Un turno cancelado, o que quedó esperando otro día, ya no está en la fila:
    // la persona puede pedir uno nuevo
    if (!$turno || $turno["vencido"] || $turno["estado"] === "cancelado") {
        unset($_SESSION["mis_turnos"][$empresaId]);
        return null;
    }

    return $turno;
}
