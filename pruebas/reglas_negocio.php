<?php
// Verificación de las reglas de negocio de Shiftify.
//
// Uso (con MySQL de XAMPP encendido):
//   1. En una ventana:  powershell -ExecutionPolicy Bypass -File pruebas\servidor.ps1
//   2. En otra:         C:\xampp\php\php.exe pruebas\reglas_negocio.php
//
// Crea la base "shiftify_pruebas" y recorre cada regla como lo haría una
// persona: registrando negocios, pidiendo turnos desde el QR y atendiendo
// desde el panel. Al final borra la base de prueba y escribe el resultado en
// docs/reglas-resultado.md. La base real "shiftify" no se toca.
require __DIR__ . "/lib.php";

$resultados = [];

// Registra una regla: $prueba devuelve true si se cumple o un texto con lo que falló
function regla(string $id, string $descripcion, string $donde, callable $prueba): void
{
    global $resultados;
    try {
        $r = $prueba();
    } catch (Throwable $e) {
        $r = $e instanceof UnexpectedValueException ? $e->getMessage() : get_class($e) . ": " . $e->getMessage();
    }
    $ok = $r === true;
    $resultados[] = [$id, $descripcion, $donde, $ok, $ok ? "" : (string) $r];
    printf("%s %s %s%s\n", $ok ? "[OK]   " : "[FALLA]", $id, $descripcion, $ok ? "" : "\n          -> $r");
}

// Comprobación: si $condicion es falsa, la regla falla con $mensaje
function exigir(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new UnexpectedValueException($mensaje);
    }
}

function contiene(array $respuesta, string $buscado): bool
{
    return str_contains(texto($respuesta["html"]), $buscado);
}

function valor(mysqli $db, string $sql): mixed
{
    $fila = $db->query($sql)->fetch_row();
    return $fila ? $fila[0] : null;
}

$db = crearBasePrueba();
comprobarServidor($db);

$registro = fn(string $negocio, string $nombre, string $correo, string $clave, ?string $confirmar = null) => [
    "negocio" => $negocio, "nombre" => $nombre, "correo" => $correo,
    "contrasena" => $clave, "confirmar" => $confirmar ?? $clave,
];

$cecilia   = new Navegador();   // dueña del Salón Doña Cecilia
$drogueria = new Navegador();   // dueño de otro negocio
$cliente   = new Navegador();   // persona que escanea el QR

try {
    echo "Reglas de negocio de Shiftify\n\n";

    // -- Registro y cuentas ---------------------------------------------

    regla("RN-01", "Registrar un negocio crea la empresa y su primera cuenta, y entra a su panel", "registro.php", function () use ($db, $cecilia, $registro) {
        $r = $cecilia->post("registro.php", $registro("Salón Doña Cecilia", "Cecilia Gómez", "cecilia@salon.co", "Cecilia2026"));
        exigir($r["codigo"] === 302 && $r["ubicacion"] === "panel.php", "no redirige al panel (HTTP {$r['codigo']})");
        $panel = $cecilia->get("panel.php");
        exigir(contiene($panel, "Fila de hoy") && contiene($panel, "Salón Doña Cecilia"), "el panel no muestra la fila del negocio");
        exigir(valor($db, "SELECT COUNT(*) FROM usuarios u JOIN empresas e ON e.id = u.empresa_id WHERE e.slug = 'salon-dona-cecilia' AND u.correo = 'cecilia@salon.co'") == 1, "la cuenta no quedó unida a su empresa");
        return true;
    });

    regla("RN-02", "Cada negocio recibe una dirección de QR única, aunque se llame igual que otro", "registro.php · crearSlug()", function () use ($db, $registro) {
        (new Navegador())->post("registro.php", $registro("Salón Doña Cecilia", "Otra Cecilia", "otra@salon.co", "Cecilia2026"));
        $slug = valor($db, "SELECT slug FROM empresas e JOIN usuarios u ON u.empresa_id = e.id WHERE u.correo = 'otra@salon.co'");
        exigir($slug === "salon-dona-cecilia-2", "el segundo negocio recibió el slug «{$slug}»");
        return true;
    });

    regla("RN-03", "El registro rechaza datos incompletos o inválidos y no crea nada", "registro.php", function () use ($db, $registro) {
        $antes = valor($db, "SELECT COUNT(*) FROM empresas");
        $casos = [
            "Escribe el nombre del negocio" => $registro("   ", "Ana", "ana@x.co", "Clave2026"),
            "Escribe tu nombre"             => $registro("Negocio", "", "ana@x.co", "Clave2026"),
            "El correo no es válido"        => $registro("Negocio", "Ana", "ana-sin-arroba", "Clave2026"),
            "al menos 8 caracteres"         => $registro("Negocio", "Ana", "ana@x.co", "corta"),
            "no coinciden"                  => $registro("Negocio", "Ana", "ana@x.co", "Clave2026", "Clave2027"),
            "Ya existe una cuenta"          => $registro("Negocio", "Ana", "cecilia@salon.co", "Clave2026"),
        ];
        foreach ($casos as $mensaje => $datos) {
            $r = (new Navegador())->post("registro.php", $datos);
            exigir($r["codigo"] === 200 && contiene($r, $mensaje), "no muestra «{$mensaje}»");
        }
        exigir(valor($db, "SELECT COUNT(*) FROM empresas") == $antes, "se creó una empresa con datos inválidos");
        return true;
    });

    regla("RN-04", "Las contraseñas se guardan cifradas, nunca en texto plano", "registro.php · usuarios.php · password_hash()", function () use ($db) {
        $hash = valor($db, "SELECT contrasena FROM usuarios WHERE correo = 'cecilia@salon.co'");
        exigir($hash !== "Cecilia2026" && password_verify("Cecilia2026", $hash), "la contraseña no está guardada como hash");
        return true;
    });

    regla("RN-05", "El acceso da el mismo mensaje si falla el correo o la contraseña", "login.php", function () {
        $malaClave = (new Navegador())->post("login.php", ["correo" => "cecilia@salon.co", "contrasena" => "otra-clave"]);
        $sinCuenta = (new Navegador())->post("login.php", ["correo" => "nadie@salon.co", "contrasena" => "otra-clave"]);
        exigir($malaClave["codigo"] === 200 && contiene($malaClave, "Correo o contraseña incorrectos."), "la contraseña errada no muestra el mensaje");
        exigir(contiene($sinCuenta, "Correo o contraseña incorrectos."), "el correo inexistente muestra otro mensaje");
        $bien = (new Navegador())->post("login.php", ["correo" => "cecilia@salon.co", "contrasena" => "Cecilia2026"]);
        exigir($bien["codigo"] === 302 && $bien["ubicacion"] === "panel.php", "la contraseña correcta no entra al panel");
        return true;
    });

    regla("RN-06", "Sin sesión no se entra al panel, al equipo ni a las acciones de la fila", "includes/sesion.php", function () use ($db) {
        $visitante = new Navegador();
        foreach (["panel.php", "usuarios.php"] as $pagina) {
            $r = $visitante->get($pagina);
            exigir($r["codigo"] === 302 && $r["ubicacion"] === "login.php", "$pagina se abre sin sesión");
        }
        $antes = valor($db, "SELECT COUNT(*) FROM turnos");
        $r = $visitante->post("turnos.php", ["accion" => "crear", "nombre" => "Sin sesión"]);
        exigir($r["ubicacion"] === "login.php" && valor($db, "SELECT COUNT(*) FROM turnos") == $antes, "turnos.php aceptó una acción sin sesión");
        return true;
    });

    // -- Fila y numeración ----------------------------------------------

    regla("RN-07", "Los turnos se numeran por negocio: cada uno empieza en A-001 y sigue sin saltos", "includes/funciones.php · crearTurno()", function () use ($cecilia, $drogueria, $registro) {
        $avisos = [];
        foreach (["Laura Castro", "Mateo Díaz", "Sofía Herrera"] as $nombre) {
            $avisos[] = $cecilia->enviar("turnos.php", ["accion" => "crear", "nombre" => $nombre]);
        }
        foreach (["A-001", "A-002", "A-003"] as $i => $codigo) {
            exigir(contiene($avisos[$i], "Turno $codigo creado"), "el turno " . ($i + 1) . " no recibió $codigo");
        }
        // Un negocio nuevo arranca su propia cuenta
        $drogueria->post("registro.php", $registro("Droguería La Esperanza", "Jorge Peña", "jorge@drogueria.co", "Drogueria2026"));
        exigir(contiene($drogueria->enviar("turnos.php", ["accion" => "crear", "nombre" => "Camila Ruiz"]), "Turno A-001 creado"), "el segundo negocio no empezó en A-001");
        return true;
    });

    regla("RN-08", "La base de datos rechaza un número repetido dentro del mismo negocio", "database/shiftify.sql · UNIQUE (empresa_id, numero)", function () use ($db) {
        try {
            $db->query("INSERT INTO turnos (empresa_id, numero, nombre) SELECT id, 1, 'Duplicado' FROM empresas WHERE slug = 'salon-dona-cecilia'");
        } catch (mysqli_sql_exception $e) {
            return true;
        }
        return "se guardó un segundo A-001 en el mismo negocio";
    });

    regla("RN-09", "El nombre es obligatorio y tiene máximo 100 caracteres (panel y QR)", "turnos.php · turno.php", function () use ($db, $cecilia) {
        $antes = valor($db, "SELECT COUNT(*) FROM turnos");
        foreach (["    ", str_repeat("x", 101)] as $nombre) {
            exigir(contiene($cecilia->enviar("turnos.php", ["accion" => "crear", "nombre" => $nombre]), "Escribe un nombre de hasta 100 caracteres."), "el panel aceptó «" . substr($nombre, 0, 10) . "…»");
            exigir(contiene((new Navegador())->post("turno.php?e=salon-dona-cecilia", ["accion" => "pedir", "nombre" => $nombre]), "Escribe tu nombre"), "el QR aceptó «" . substr($nombre, 0, 10) . "…»");
        }
        exigir(valor($db, "SELECT COUNT(*) FROM turnos") == $antes, "se guardó un turno con nombre inválido");
        return true;
    });

    regla("RN-10", "Desde el QR, el cliente ve su código, cuántas personas tiene antes y su espera", "turno.php", function () use ($cliente) {
        $r = $cliente->enviar("turno.php?e=salon-dona-cecilia", ["accion" => "pedir", "nombre" => "Valentina Ruiz"]);
        exigir(contiene($r, "Tu turno, Valentina Ruiz") && contiene($r, "A-004"), "no muestra el turno A-004");
        // 3 personas antes y 5 min por turno mientras no hay atenciones del día: (3 + 1) × 5
        exigir(contiene($r, "3 personas antes") && contiene($r, "~20 minutos de espera"), "posición o espera incorrecta: " . texto($r["html"]));
        exigir(contiene($r, "Estás en la fila de Salón Doña Cecilia"), "no dice en qué negocio está la fila");
        exigir(str_contains($r["html"], 'http-equiv="refresh"'), "la página no se actualiza sola mientras espera");
        return true;
    });

    regla("RN-11", "Cada persona tiene un solo turno activo por negocio", "turno.php", function () use ($db, $cliente) {
        $antes = valor($db, "SELECT COUNT(*) FROM turnos");
        $r = $cliente->enviar("turno.php?e=salon-dona-cecilia", ["accion" => "pedir", "nombre" => "Valentina otra vez"]);
        exigir(valor($db, "SELECT COUNT(*) FROM turnos") == $antes, "se creó un segundo turno activo");
        exigir(contiene($r, "A-004"), "dejó de mostrar el turno que ya tenía");
        return true;
    });

    regla("RN-12", "La misma persona puede estar a la vez en la fila de dos negocios distintos", "turno.php · \$_SESSION[\"mis_turnos\"]", function () use ($cliente) {
        $r = $cliente->enviar("turno.php?e=drogueria-la-esperanza", ["accion" => "pedir", "nombre" => "Valentina Ruiz"]);
        exigir(contiene($r, "A-002") && contiene($r, "Droguería La Esperanza"), "no recibió turno en el segundo negocio");
        exigir(contiene($cliente->get("turno.php?e=salon-dona-cecilia"), "A-004"), "perdió su turno en el primer negocio");
        return true;
    });

    // -- Atención -------------------------------------------------------

    regla("RN-13", "«Llamar siguiente» llama al turno más antiguo que espera", "turnos.php", function () use ($cecilia) {
        $r = $cecilia->enviar("turnos.php", ["accion" => "siguiente"]);
        exigir(contiene($r, "Llamando a A-001 · Laura Castro"), "no llamó a A-001");
        exigir(contiene($r, "Atendiendo ahora A-001 Laura Castro"), "el panel no muestra a A-001 en atención");
        return true;
    });

    regla("RN-14", "Solo hay un turno en atención: llamar al siguiente cierra el actual", "turnos.php · transacción", function () use ($db, $cecilia) {
        $cecilia->enviar("turnos.php", ["accion" => "siguiente"]);
        $e = "(SELECT id FROM empresas WHERE slug = 'salon-dona-cecilia')";
        exigir(valor($db, "SELECT estado FROM turnos WHERE empresa_id = $e AND numero = 1") === "atendido", "A-001 no quedó atendido");
        exigir(valor($db, "SELECT finalizado IS NOT NULL FROM turnos WHERE empresa_id = $e AND numero = 1") == 1, "A-001 no guardó la hora de fin");
        exigir(valor($db, "SELECT COUNT(*) FROM turnos WHERE empresa_id = $e AND estado = 'atendiendo'") == 1, "hay más de un turno en atención");
        exigir(valor($db, "SELECT numero FROM turnos WHERE empresa_id = $e AND estado = 'atendiendo'") == 2, "no pasó a A-002");
        return true;
    });

    regla("RN-15", "Un negocio nunca ve ni mueve la fila o el equipo de otro", "panel.php · turnos.php · usuarios.php (WHERE empresa_id = ?)", function () use ($db, $drogueria) {
        $panel = $drogueria->get("panel.php");
        // (Valentina pidió turno en los dos negocios, así que se buscan clientes que solo son del salón)
        exigir(!contiene($panel, "Mateo Díaz") && !contiene($panel, "Sofía Herrera"), "la droguería ve clientes del salón");
        exigir(!contiene($drogueria->get("usuarios.php"), "cecilia@salon.co"), "la droguería ve el equipo del salón");
        $estados = "SELECT GROUP_CONCAT(estado ORDER BY numero) FROM turnos t JOIN empresas e ON e.id = t.empresa_id WHERE e.slug = 'salon-dona-cecilia'";
        $salonAntes = valor($db, $estados);
        $r = $drogueria->enviar("turnos.php", ["accion" => "siguiente"]);
        exigir(contiene($r, "Llamando a A-001 · Camila Ruiz"), "la droguería no llamó a su propio A-001");
        exigir(valor($db, $estados) === $salonAntes, "la acción de la droguería cambió la fila del salón");
        return true;
    });

    regla("RN-16", "El cliente ve cuándo es su turno y cuándo terminó su atención", "turno.php", function () use ($cliente, $cecilia) {
        $cecilia->enviar("turnos.php", ["accion" => "siguiente"]);   // A-003: Valentina (A-004) queda primera
        exigir(contiene($cliente->get("turno.php?e=salon-dona-cecilia"), "¡Eres el siguiente!"), "con 0 personas antes no dice «¡Eres el siguiente!»");
        $cecilia->enviar("turnos.php", ["accion" => "siguiente"]);   // A-004, Valentina
        exigir(contiene($cliente->get("turno.php?e=salon-dona-cecilia"), "¡Es tu turno! A-004 Acércate a la ventanilla"), "no avisa que es su turno");
        $cecilia->enviar("turnos.php", ["accion" => "finalizar"]);
        $fin = $cliente->get("turno.php?e=salon-dona-cecilia");
        exigir(contiene($fin, "¡Gracias, Valentina Ruiz!") && contiene($fin, "Atención finalizada"), "no muestra la atención finalizada");
        exigir(!str_contains($fin["html"], 'http-equiv="refresh"'), "sigue recargándose después de ser atendido");
        return true;
    });

    regla("RN-17", "«Finalizar» cierra la atención sin llamar a nadie más", "turnos.php", function () use ($db, $cecilia) {
        exigir(contiene($cecilia->get("panel.php"), "Nadie en atención."), "el panel sigue mostrando a alguien en atención");
        exigir(valor($db, "SELECT COUNT(*) FROM turnos WHERE estado = 'atendiendo' AND empresa_id = (SELECT id FROM empresas WHERE slug = 'salon-dona-cecilia')") == 0, "quedó un turno en atención");
        return true;
    });

    regla("RN-18", "Con la fila vacía no se puede llamar a nadie", "panel.php · turnos.php", function () use ($cecilia) {
        $panel = $cecilia->get("panel.php");
        exigir(contiene($panel, "No hay nadie esperando."), "la fila no está vacía");
        exigir((bool) preg_match('#value="siguiente">\s*<button[^>]*disabled#', $panel["html"]), "el botón «Llamar siguiente» no está desactivado");
        exigir(contiene($cecilia->enviar("turnos.php", ["accion" => "siguiente"]), "No hay nadie más en la fila."), "no avisa que la fila está vacía");
        return true;
    });

    regla("RN-19", "La espera estimada usa la duración media de las atenciones de hoy", "includes/funciones.php · minutosPorTurno()", function () use ($db, $cecilia) {
        // Las 4 atenciones del salón duraron exactamente 10 minutos
        $db->query("UPDATE turnos t JOIN empresas e ON e.id = t.empresa_id SET t.llamado = NOW() - INTERVAL 10 MINUTE, t.finalizado = NOW() WHERE e.slug = 'salon-dona-cecilia' AND t.estado = 'atendido'");
        $cecilia->enviar("turnos.php", ["accion" => "crear", "nombre" => "Daniel Mora"]);
        $cecilia->enviar("turnos.php", ["accion" => "crear", "nombre" => "Paula Rincón"]);
        $panel = $cecilia->get("panel.php");
        exigir(contiene($panel, "Atendidos hoy 04"), "«Atendidos hoy» no cuenta las 4 atenciones");
        exigir(contiene($panel, "Espera estimada 20 min"), "2 personas × 10 min no da 20 min");
        $nuevo = (new Navegador())->enviar("turno.php?e=salon-dona-cecilia", ["accion" => "pedir", "nombre" => "Tomás Vega"]);
        exigir(contiene($nuevo, "2 personas antes") && contiene($nuevo, "~30 minutos de espera"), "el cliente no ve (2 + 1) × 10 = 30 min");
        return true;
    });

    regla("RN-20", "Después de ser atendido, el cliente puede pedir un turno nuevo", "turno.php", function () use ($cliente) {
        $cliente->post("turno.php?e=salon-dona-cecilia", ["accion" => "nuevo"]);
        exigir(contiene($cliente->get("turno.php?e=salon-dona-cecilia"), "Pide tu turno"), "no vuelve al formulario");
        $r = $cliente->enviar("turno.php?e=salon-dona-cecilia", ["accion" => "pedir", "nombre" => "Valentina Ruiz"]);
        exigir(contiene($r, "A-008"), "no recibió el siguiente número (A-008)");
        return true;
    });

    regla("RN-21", "Un turno que quedó esperando de un día anterior no entra en la fila de hoy", "panel.php · turnos.php · turno.php", function () use ($db, $cecilia) {
        $e = "(SELECT id FROM empresas WHERE slug = 'salon-dona-cecilia')";
        // Número 0: un turno de ayer siempre tiene un número menor que los de hoy
        $db->query("INSERT INTO turnos (empresa_id, numero, nombre, creado) SELECT id, 0, 'Turno de ayer', NOW() - INTERVAL 1 DAY FROM empresas WHERE slug = 'salon-dona-cecilia'");
        exigir(!contiene($cecilia->get("panel.php"), "Turno de ayer"), "el panel muestra en «Fila de hoy» un turno de ayer");
        exigir(!contiene($cecilia->enviar("turnos.php", ["accion" => "siguiente"]), "Turno de ayer"), "«Llamar siguiente» llamó al turno de ayer");
        $db->query("DELETE FROM turnos WHERE numero = 0 AND empresa_id = $e");
        // Quien pidió turno ayer y no fue atendido vuelve a ver el formulario
        $deAyer = new Navegador();
        $deAyer->post("turno.php?e=salon-dona-cecilia", ["accion" => "pedir", "nombre" => "Cliente de ayer"]);
        $db->query("UPDATE turnos SET creado = NOW() - INTERVAL 1 DAY WHERE nombre = 'Cliente de ayer'");
        exigir(contiene($deAyer->get("turno.php?e=salon-dona-cecilia"), "Pide tu turno"), "el cliente sigue viendo un turno de ayer como activo");
        return true;
    });

    // -- Equipo ---------------------------------------------------------

    regla("RN-22", "Un miembro nuevo entra al negocio de quien lo crea", "usuarios.php", function () use ($cecilia) {
        $r = $cecilia->enviar("usuarios.php", ["nombre" => "Andrés Rojas", "correo" => "andres@salon.co", "contrasena" => "Andres2026", "confirmar" => "Andres2026"]);
        exigir(contiene($r, "Cuenta creada para Andrés Rojas") && contiene($r, "andres@salon.co"), "no aparece en el equipo");
        $andres = new Navegador();
        $andres->post("login.php", ["correo" => "andres@salon.co", "contrasena" => "Andres2026"]);
        exigir(contiene($andres->get("panel.php"), "Salón Doña Cecilia"), "Andrés no entra al panel del salón");
        exigir(contiene($cecilia->enviar("usuarios.php", ["nombre" => "Otro", "correo" => "jorge@drogueria.co", "contrasena" => "Clave2026", "confirmar" => "Clave2026"]), "Ya existe una cuenta con ese correo."), "aceptó un correo que ya usa otro negocio");
        return true;
    });

    regla("RN-23", "Cerrar sesión impide volver a entrar al panel", "salir.php", function () {
        $andres = new Navegador();
        $andres->post("login.php", ["correo" => "andres@salon.co", "contrasena" => "Andres2026"]);
        $andres->get("salir.php");
        $r = $andres->get("panel.php");
        exigir($r["codigo"] === 302 && $r["ubicacion"] === "login.php", "el panel sigue abierto después de salir");
        return true;
    });

    // -- QR y seguridad -------------------------------------------------

    regla("RN-24", "Un QR viejo o mal escrito no suma a nadie a una fila", "turno.php · qr.php", function () use ($db) {
        $antes = valor($db, "SELECT COUNT(*) FROM turnos");
        $r = (new Navegador())->post("turno.php?e=no-existe", ["accion" => "pedir", "nombre" => "Perdido"]);
        exigir($r["codigo"] === 404 && contiene($r, "No encontramos este negocio"), "no responde 404 con el aviso");
        exigir(valor($db, "SELECT COUNT(*) FROM turnos") == $antes, "creó un turno sin negocio");
        exigir((new Navegador())->get("qr.php?e=../panel")["codigo"] === 400, "qr.php aceptó una dirección inválida");
        $qr = (new Navegador())->get("qr.php?e=salon-dona-cecilia");
        exigir($qr["codigo"] === 302 && str_contains(rawurldecode($qr["ubicacion"]), "/turno.php?e=salon-dona-cecilia"), "el QR no apunta a la fila del negocio");
        return true;
    });

    regla("RN-25", "Recargar la página no repite una acción (cada envío redirige)", "turnos.php · turno.php · login.php · registro.php", function () use ($cecilia) {
        exigir($cecilia->post("turnos.php", ["accion" => "finalizar"])["codigo"] === 302, "turnos.php no redirige");
        exigir((new Navegador())->post("turno.php?e=salon-dona-cecilia", ["accion" => "pedir", "nombre" => "Ana Prueba"])["codigo"] === 302, "turno.php no redirige");
        return true;
    });

    regla("RN-26", "Lo que escribe el cliente se muestra como texto, nunca como código", "htmlspecialchars() en todas las vistas", function () use ($cecilia) {
        // Un nombre con etiquetas HTML debe verse tal cual, escapado
        $nombre = "<b>Ana</b> & <i>Luis</i>";
        (new Navegador())->post("turno.php?e=salon-dona-cecilia", ["accion" => "pedir", "nombre" => $nombre]);
        $html = $cecilia->get("panel.php")["html"];
        exigir(!str_contains($html, $nombre) && str_contains($html, htmlspecialchars($nombre)), "el nombre se imprimió sin escapar");
        return true;
    });
} finally {
    borrarBasePrueba($db);
}

// -- Resumen ------------------------------------------------------------
$cumplidas = count(array_filter($resultados, fn($r) => $r[3]));
$total = count($resultados);
printf("\n%d de %d reglas se cumplen.\n", $cumplidas, $total);

// La fecha la da MySQL, como en la aplicación (PHP de XAMPP puede estar en otra zona horaria)
$mysql = new mysqli(getenv("DB_HOST") ?: "localhost", getenv("DB_USER") ?: "root", getenv("DB_PASSWORD") ?: "");
$fecha = $mysql->query("SELECT DATE_FORMAT(CURDATE(), '%d/%m/%Y')")->fetch_row()[0];
$md  = "# Resultado de la verificación de reglas\n\n";
$md .= "Generado por `pruebas/reglas_negocio.php` el $fecha con PHP " . PHP_VERSION . " y MySQL {$mysql->server_info}.\n\n";
$md .= "**$cumplidas de $total reglas se cumplen.** Qué dice cada regla y por qué: [reglas-negocio.md](reglas-negocio.md).\n\n";
$md .= "| Regla | Descripción | Dónde se aplica | Resultado |\n|---|---|---|---|\n";
foreach ($resultados as [$id, $descripcion, $donde, $ok, $motivo]) {
    $md .= "| $id | $descripcion | " . str_replace("|", "\\|", $donde) . " | " . ($ok ? "✅ Se cumple" : "❌ Falla: " . str_replace("|", "\\|", $motivo)) . " |\n";
}
file_put_contents(RAIZ . "/docs/reglas-resultado.md", $md);

exit($cumplidas === $total ? 0 : 1);
