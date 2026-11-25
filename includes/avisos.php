<?php
// Notificaciones push (Web Push con VAPID), sin librerías externas.
//
// Para que el aviso llegue aunque el cliente esté en otra app o con el celular
// bloqueado, el servidor le pide al servicio de push del navegador (Google para
// Chrome, Apple para Safari, Mozilla para Firefox) que despierte al celular.
//
// Se manda un aviso SIN contenido (así no hace falta cifrar nada): al recibirlo,
// sw.js pregunta a estado_push.php cómo va el turno y muestra la notificación.
// Lo único que firma el servidor es un JWT (ES256) con su clave VAPID, que se
// crea sola la primera vez y queda en la tabla ajustes.

// Codificación base64 para URL, sin relleno
function b64url(string $datos): string
{
    return rtrim(strtr(base64_encode($datos), "+/", "-_"), "=");
}

// Claves VAPID del servidor: las lee de la base o las crea la primera vez
function clavesVapid(mysqli $conexion): array
{
    $fila = consultar($conexion, "SELECT valor FROM ajustes WHERE clave = 'vapid_privada'", "")->fetch_assoc();
    if ($fila) {
        $privada = openssl_pkey_get_private($fila["valor"]);
    } else {
        $opciones = ["curve_name" => "prime256v1", "private_key_type" => OPENSSL_KEYTYPE_EC];
        // XAMPP en Windows necesita saber dónde está openssl.cnf
        if (PHP_OS_FAMILY === "Windows" && is_file("C:/xampp/php/extras/ssl/openssl.cnf")) {
            $opciones["config"] = "C:/xampp/php/extras/ssl/openssl.cnf";
        }
        $privada = openssl_pkey_new($opciones);
        openssl_pkey_export($privada, $pem, null, $opciones);
        // INSERT IGNORE: si dos peticiones la crean a la vez, gana la primera
        consultar($conexion, "INSERT IGNORE INTO ajustes (clave, valor) VALUES ('vapid_privada', ?)", "s", $pem);
        $pem = consultar($conexion, "SELECT valor FROM ajustes WHERE clave = 'vapid_privada'", "")->fetch_assoc()["valor"];
        $privada = openssl_pkey_get_private($pem);
    }

    $ec = openssl_pkey_get_details($privada)["ec"];
    $publica = "\x04" . str_pad($ec["x"], 32, "\0", STR_PAD_LEFT) . str_pad($ec["y"], 32, "\0", STR_PAD_LEFT);

    return ["privada" => $privada, "publica" => b64url($publica)];
}

// Firma ES256: openssl entrega la firma en DER; JWT la quiere como r||s (64 bytes)
function firmaEs256(string $datos, $privada): string
{
    openssl_sign($datos, $der, $privada, OPENSSL_ALGO_SHA256);
    $pos = 3;
    $largoR = ord($der[$pos]);
    $r = substr($der, $pos + 1, $largoR);
    $pos += 1 + $largoR + 1;
    $largoS = ord($der[$pos]);
    $s = substr($der, $pos + 1, $largoS);
    $ajustar = fn(string $n) => str_pad(ltrim($n, "\0"), 32, "\0", STR_PAD_LEFT);

    return $ajustar($r) . $ajustar($s);
}

// Manda un aviso (sin contenido) a una suscripción. Devuelve el código HTTP:
// 201 = entregado al servicio de push; 404/410 = la suscripción ya no existe.
function enviarPush(mysqli $conexion, string $endpoint): int
{
    $claves = clavesVapid($conexion);
    $partes = parse_url($endpoint);
    if (!$partes || ($partes["scheme"] ?? "") !== "https") {
        return 0;
    }

    $cabecera = b64url(json_encode(["typ" => "JWT", "alg" => "ES256"]));
    $cuerpo = b64url(json_encode([
        "aud" => "https://" . $partes["host"],
        "exp" => time() + 12 * 3600,
        "sub" => "https://shiftify.kyracloud.com",
    ]));
    $jwt = "$cabecera.$cuerpo." . b64url(firmaEs256("$cabecera.$cuerpo", $claves["privada"]));

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => "",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_HTTPHEADER => [
            "Authorization: vapid t=$jwt, k={$claves['publica']}",
            "TTL: 600",
            "Urgency: high",
            "Content-Length: 0",
        ],
    ]);
    // En el hosting (Linux) los certificados vienen con el sistema. En XAMPP para
    // Windows el paquete de certificados está viejo: se puede indicar otro aquí.
    if (getenv("SHIFTIFY_CA_BUNDLE")) {
        curl_setopt($ch, CURLOPT_CAINFO, getenv("SHIFTIFY_CA_BUNDLE"));
    }
    curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($codigo === 0) {
        error_log("Shiftify push: no se pudo conectar con el servicio de push: " . curl_error($ch));
    }
    curl_close($ch);

    return $codigo;
}

// Avisa a todos los celulares suscritos a un turno, una sola vez por tipo
// ("preaviso" o "llamado"). Borra las suscripciones que ya no existen.
function avisarTurno(mysqli $conexion, int $turnoId, string $tipo): void
{
    if (!in_array($tipo, ["preaviso", "llamado"], true) || !function_exists("curl_init")) {
        return;
    }
    $suscripciones = consultar($conexion, "SELECT id, endpoint FROM suscripciones WHERE turno_id = ? AND $tipo = 0", "i", $turnoId)->fetch_all(MYSQLI_ASSOC);

    foreach ($suscripciones as $s) {
        $codigo = enviarPush($conexion, $s["endpoint"]);
        if ($codigo === 404 || $codigo === 410) {
            consultar($conexion, "DELETE FROM suscripciones WHERE id = ?", "i", $s["id"]);
        } elseif ($codigo >= 200 && $codigo < 300) {
            consultar($conexion, "UPDATE suscripciones SET $tipo = 1 WHERE id = ?", "i", $s["id"]);
        } else {
            error_log("Shiftify push: el servicio respondió $codigo para la suscripción {$s['id']}");
        }
    }
}
