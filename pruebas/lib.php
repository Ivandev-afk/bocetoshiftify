<?php
// Piezas compartidas por reglas_negocio.php y maquetas.php:
// una base de datos de prueba, la comprobación del servidor y un "navegador"
// con su cookie. Nada de esto toca la base real "shiftify".

const BASE_PRUEBAS = "shiftify_pruebas";
const PUERTO       = 8099;
const RAIZ         = __DIR__ . "/..";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Crea la base de prueba desde cero con el mismo script que usa el proyecto
function crearBasePrueba(): mysqli
{
    $db = new mysqli(getenv("DB_HOST") ?: "localhost", getenv("DB_USER") ?: "root", getenv("DB_PASSWORD") ?: "");
    $db->query("DROP DATABASE IF EXISTS " . BASE_PRUEBAS);

    $sql = file_get_contents(RAIZ . "/database/shiftify.sql");
    $sql = str_replace(["DATABASE IF NOT EXISTS shiftify ", "USE shiftify;"], ["DATABASE " . BASE_PRUEBAS . " ", "USE " . BASE_PRUEBAS . ";"], $sql);

    $db->multi_query($sql);
    while ($db->more_results()) {
        $db->next_result();
    }
    $db->select_db(BASE_PRUEBAS);
    $db->set_charset("utf8mb4");

    return $db;
}

function borrarBasePrueba(mysqli $db): void
{
    $db->query("DROP DATABASE IF EXISTS " . BASE_PRUEBAS);
    $db->close();
}

// El servidor de prueba se enciende aparte, en otra ventana (ver pruebas/servidor.ps1).
// Los scripts no lanzan procesos: los antivirus (Avast, por ejemplo) ponen en
// cuarentena un PHP que enciende otro PHP y luego lo cierra con taskkill.
//
// Antes de empezar se comprueba que el servidor use la base de prueba: se crea
// un negocio que solo existe en ella y se pide su página. Si el servidor estuviera
// conectado a la base real, ese negocio no existiría y las pruebas no arrancan.
function comprobarServidor(mysqli $db): void
{
    $s = @fsockopen("127.0.0.1", PUERTO, $codigo, $mensaje, 1);
    if (!$s) {
        fwrite(STDERR, "No hay servidor de prueba en http://127.0.0.1:" . PUERTO . "\n"
            . "Enciéndelo en otra ventana de PowerShell y deja esa ventana abierta:\n"
            . "    powershell -ExecutionPolicy Bypass -File pruebas\\servidor.ps1\n");
        borrarBasePrueba($db);
        exit(2);
    }
    fclose($s);

    $marca = "base-de-prueba-" . bin2hex(random_bytes(4));
    $db->query("INSERT INTO empresas (nombre, slug) VALUES ('Marca de prueba', '$marca')");
    $respuesta = (new Navegador())->get("turno.php?e=$marca");
    $db->query("DELETE FROM empresas WHERE slug = '$marca'");

    if ($respuesta["codigo"] !== 200) {
        fwrite(STDERR, "El servidor del puerto " . PUERTO . " no está conectado a la base " . BASE_PRUEBAS . ".\n"
            . "Ciérralo y vuelve a encenderlo con pruebas\\servidor.ps1. No se tocó ningún dato.\n");
        borrarBasePrueba($db);
        exit(2);
    }
}

// Un visitante con su propia sesión (su propia cookie PHPSESSID)
class Navegador
{
    private array $cookies = [];

    public function get(string $ruta): array
    {
        return $this->pedir("GET", $ruta);
    }

    public function post(string $ruta, array $datos): array
    {
        return $this->pedir("POST", $ruta, $datos);
    }

    // POST y, si responde con una redirección, la sigue como el navegador
    public function enviar(string $ruta, array $datos): array
    {
        $r = $this->post($ruta, $datos);
        return $r["ubicacion"] ? $this->get($r["ubicacion"]) : $r;
    }

    private function pedir(string $metodo, string $ruta, array $datos = []): array
    {
        $cabeceras = [];
        if ($this->cookies) {
            $pares = [];
            foreach ($this->cookies as $nombre => $valor) {
                $pares[] = "$nombre=$valor";
            }
            $cabeceras[] = "Cookie: " . implode("; ", $pares);
        }
        $cuerpo = "";
        if ($metodo === "POST") {
            $cuerpo = http_build_query($datos);
            $cabeceras[] = "Content-Type: application/x-www-form-urlencoded";
        }

        $contexto = stream_context_create(["http" => [
            "method"          => $metodo,
            "header"          => implode("\r\n", $cabeceras),
            "content"         => $cuerpo,
            "follow_location" => 0,
            "ignore_errors"   => true,
            "timeout"         => 10,
        ]]);

        $html = file_get_contents("http://127.0.0.1:" . PUERTO . "/" . ltrim($ruta, "/"), false, $contexto);
        $respuesta = ["codigo" => 0, "ubicacion" => null, "tipo" => "", "html" => $html === false ? "" : $html];

        foreach ($http_response_header ?? [] as $linea) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $linea, $m)) {
                $respuesta["codigo"] = (int) $m[1];
            } elseif (preg_match('/^Location:\s*(.+)$/i', $linea, $m)) {
                $respuesta["ubicacion"] = trim($m[1]);
            } elseif (preg_match('/^Content-Type:\s*(.+)$/i', $linea, $m)) {
                $respuesta["tipo"] = trim($m[1]);
            } elseif (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $linea, $m)) {
                if ($m[2] === "" || $m[2] === "deleted") {
                    unset($this->cookies[$m[1]]);
                } else {
                    $this->cookies[$m[1]] = $m[2];
                }
            }
        }

        return $respuesta;
    }
}

// Texto visible de una página, sin etiquetas y con los espacios normalizados
function texto(string $html): string
{
    $html = preg_replace('#<(script|style)\b.*?</\1>#s', "", $html);
    // Cada etiqueta se cambia por un espacio: <strong>3</strong><span>personas</span> → "3 personas"
    $html = preg_replace('/<[^>]+>/', " ", $html);
    $html = preg_replace('/\s+/u', " ", html_entity_decode($html, ENT_QUOTES, "UTF-8"));
    // Sin espacio antes de los signos que cierran: "Laura Castro ." → "Laura Castro."
    return trim(preg_replace('/ ([.,!?])/u', '$1', $html));
}
