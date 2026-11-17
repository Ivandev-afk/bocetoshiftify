<?php
// Genera los mockups de las pantallas operativas a partir del sistema real.
//
// Uso (con MySQL de XAMPP encendido):
//   1. En una ventana:  powershell -ExecutionPolicy Bypass -File pruebas\servidor.ps1
//   2. En otra:         C:\xampp\php\php.exe pruebas\maquetas.php
//
// Recorre un día de trabajo del "Salón Doña Cecilia" en la base de prueba y
// guarda cada pantalla, tal como la entrega PHP, en pantallas/*.html.
// Después ajusta cada copia para que funcione sin servidor (GitHub Pages):
// rutas de assets, enlaces y formularios llevan a la siguiente maqueta.
// pantallas/index.html (la galería) está escrita a mano y no se toca.
require __DIR__ . "/lib.php";

// Dirección pública de las maquetas: el QR de la maqueta del panel apunta aquí
const PAGES = "https://ivandev-afk.github.io/bocetoshiftify/pantallas/";
const DESTINO = RAIZ . "/pantallas";

// Enlaces de la aplicación → maqueta equivalente
const ENLACES = [
    "index.html"   => "../index.html",
    "login.php"    => "login.html",
    "registro.php" => "registro.html",
    "panel.php"    => "panel.html",
    "usuarios.php" => "equipo.html",
    "salir.php"    => "login.html",
];

// Convierte una página PHP en una maqueta estática que se navega sola
function maqueta(string $html): string
{
    // Estilos e imágenes viven un nivel arriba
    $html = str_replace(['href="assets/', 'src="assets/'], ['href="../assets/', 'src="../assets/'], $html);

    // La maqueta no se recarga sola
    $html = preg_replace('#\s*<!--[^>]*-->\s*<meta http-equiv="refresh"[^>]*>#', "", $html);

    // Formularios: van a la pantalla que el sistema mostraría después, por GET y sin datos
    $html = preg_replace_callback('#<form\b([^>]*)>(.*?)</form>#s', function ($m) {
        preg_match('/action="([^"?]*)/', $m[1], $accion);
        preg_match('/name="accion" value="([^"]+)"/', $m[2], $tipo);
        $destino = match ([$accion[1] ?? "", $tipo[1] ?? ""]) {
            ["turno.php", "pedir"] => "turno-esperando.html",
            ["turno.php", "nuevo"] => "turno-pedir.html",
            ["registro.php", ""]   => "panel-nuevo.html",
            ["login.php", ""]      => "panel.html",
            ["usuarios.php", ""]   => "equipo.html",
            default                => "panel.html",
        };
        $atributos = preg_replace(['/action="[^"]*"/', '/method="POST"/'], ['action="' . $destino . '"', 'method="get"'], $m[1]);
        // Sin name no viaja nada en la dirección (ni contraseñas) y sin required se recorre con un clic
        $cuerpo = preg_replace('#<input type="hidden"[^>]*>\s*#', "", $m[2]);
        $cuerpo = preg_replace('/ (name="[^"]*"|required|minlength="\d+")/', "", $cuerpo);
        return "<form$atributos>$cuerpo</form>";
    }, $html);

    // Enlaces entre pantallas
    foreach (ENLACES as $php => $estatica) {
        $html = str_replace('href="' . $php . '"', 'href="' . $estatica . '"', $html);
    }
    $html = preg_replace('#href="turno\.php\?e=[^"]*"#', 'href="turno-pedir.html"', $html);

    // El QR de la maqueta abre la maqueta del cliente
    $html = preg_replace('#src="qr\.php\?e=[^"]*"#', 'src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&amp;margin=8&amp;data=' . rawurlencode(PAGES . "turno-pedir.html") . '"', $html);

    // Aviso visible: es una maqueta con datos de ejemplo
    $aviso = "\n  <p class=\"maqueta-aviso\">Maqueta con datos de ejemplo · <a href=\"index.html\">Ver todas las pantallas</a></p>";
    $html = preg_replace('#(<body[^>]*>)#', '$1' . $aviso, $html, 1);

    // Sin espacios sueltos al final de las líneas que dejan los bloques de PHP
    return preg_replace("/[ \t]+\n/", "\n", $html);
}

function guardar(string $archivo, array $respuesta): void
{
    if ($respuesta["codigo"] !== 200 && $respuesta["codigo"] !== 404) {
        throw new RuntimeException("$archivo: la página respondió HTTP {$respuesta['codigo']}");
    }
    file_put_contents(DESTINO . "/$archivo", maqueta($respuesta["html"]));
    echo "  pantallas/$archivo\n";
}

$db = crearBasePrueba();
comprobarServidor($db);
@mkdir(DESTINO);
$salon = "turno.php?e=salon-dona-cecilia";

try {
    echo "Generando pantallas…\n";
    $visitante = new Navegador();
    guardar("login.html", $visitante->get("login.php"));
    guardar("registro.html", $visitante->get("registro.php"));

    // 1. Cecilia registra su negocio: el panel recién creado, sin nadie en la fila
    $cecilia = new Navegador();
    $cecilia->post("registro.php", ["negocio" => "Salón Doña Cecilia", "nombre" => "Cecilia Gómez", "correo" => "cecilia@salon.co", "contrasena" => "Cecilia2026", "confirmar" => "Cecilia2026"]);
    guardar("panel-nuevo.html", $cecilia->get("panel.php"));

    // 2. Suma a su equipo
    $cecilia->post("usuarios.php", ["nombre" => "Andrés Rojas", "correo" => "andres@salon.co", "contrasena" => "Andres2026", "confirmar" => "Andres2026"]);
    guardar("equipo.html", $cecilia->enviar("usuarios.php", ["nombre" => "Marta Lizcano", "correo" => "marta@salon.co", "contrasena" => "Marta2026", "confirmar" => "Marta2026"]));

    // 3. Llegan clientes: tres se anotan en el mostrador y una escanea el QR
    foreach (["Laura Castro", "Mateo Díaz", "Sofía Herrera"] as $nombre) {
        $cecilia->post("turnos.php", ["accion" => "crear", "nombre" => $nombre]);
    }
    $valentina = new Navegador();
    guardar("turno-pedir.html", $valentina->get($salon));
    guardar("turno-esperando.html", $valentina->enviar($salon, ["accion" => "pedir", "nombre" => "Valentina Ruiz"]));

    // 4. Atiende a los dos primeros (unos 8 minutos cada uno) y llama a Sofía
    $cecilia->post("turnos.php", ["accion" => "siguiente"]);
    $cecilia->post("turnos.php", ["accion" => "siguiente"]);
    $db->query("UPDATE turnos SET llamado = NOW() - INTERVAL 17 MINUTE, finalizado = NOW() - INTERVAL 9 MINUTE WHERE numero = 1");
    $db->query("UPDATE turnos SET llamado = NOW() - INTERVAL 9 MINUTE, finalizado = NOW() - INTERVAL 1 MINUTE WHERE numero = 2");
    foreach (["Daniel Mora", "Paula Rincón"] as $nombre) {
        $cecilia->post("turnos.php", ["accion" => "crear", "nombre" => $nombre]);
    }
    (new Navegador())->post($salon, ["accion" => "pedir", "nombre" => "Tomás Vega"]);
    guardar("panel.html", $cecilia->enviar("turnos.php", ["accion" => "siguiente"]));

    // 5. Le toca a Valentina, y después termina su atención
    $cecilia->post("turnos.php", ["accion" => "siguiente"]);
    guardar("turno-ahora.html", $valentina->get($salon));
    $cecilia->post("turnos.php", ["accion" => "finalizar"]);
    guardar("turno-fin.html", $valentina->get($salon));

    // 6. Alguien escanea un QR que ya no existe
    guardar("turno-no-existe.html", (new Navegador())->get("turno.php?e=negocio-cerrado"));
} finally {
    borrarBasePrueba($db);
}

echo "Listo. Galería: pantallas/index.html\n";
