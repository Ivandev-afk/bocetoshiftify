<?php
// Generador de códigos QR propio, sin librerías ni servicios externos.
// Antes el QR lo dibujaba api.qrserver.com: si ese servicio fallaba, no había QR.
//
// Cubre lo que necesita Shiftify: texto en modo byte, corrección de errores M
// (aguanta ~15 % del código dañado) y versiones 1 a 10 (hasta 213 caracteres).
// Sigue el estándar ISO/IEC 18004; la estructura es la del generador de
// referencia de Project Nayuki (https://www.nayuki.io/page/qr-code-generator-library).

// Por versión: [codewords totales, codewords de corrección por bloque, bloques] (nivel M)
const QR_VERSIONES_M = [
    1 => [26, 10, 1], 2 => [44, 16, 1], 3 => [70, 26, 1], 4 => [100, 18, 2], 5 => [134, 24, 2],
    6 => [172, 16, 4], 7 => [196, 18, 4], 8 => [242, 22, 4], 9 => [292, 22, 5], 10 => [346, 26, 5],
];

// Centros de los patrones de alineación por versión
const QR_ALINEACION = [
    1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
    6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
];

// Matriz del QR: $matriz[y][x] = true si el módulo es oscuro.
// $mascara = null elige la mejor máscara (la de menor penalización), como pide el estándar.
function qrMatriz(string $texto, ?int $mascara = null): array
{
    $bytes = array_values(unpack("C*", $texto) ?: []);
    $largo = count($bytes);

    // 1. La versión más pequeña donde cabe el texto
    $version = null;
    foreach (QR_VERSIONES_M as $v => [$total, $ecc, $bloques]) {
        $bitsConteo = $v <= 9 ? 8 : 16;
        if (4 + $bitsConteo + $largo * 8 <= ($total - $ecc * $bloques) * 8) {
            $version = $v;
            break;
        }
    }
    if ($version === null) {
        throw new InvalidArgumentException("Texto demasiado largo para el QR");
    }
    [$total, $eccPorBloque, $numBloques] = QR_VERSIONES_M[$version];
    $capacidad = ($total - $eccPorBloque * $numBloques) * 8;

    // 2. Bits de datos: modo byte (0100), cuántos bytes, los bytes, terminador y relleno
    $bits = [];
    $poner = function (int $valor, int $cuantos) use (&$bits) {
        for ($i = $cuantos - 1; $i >= 0; $i--) {
            $bits[] = ($valor >> $i) & 1;
        }
    };
    $poner(0b0100, 4);
    $poner($largo, $version <= 9 ? 8 : 16);
    foreach ($bytes as $b) {
        $poner($b, 8);
    }
    $poner(0, min(4, $capacidad - count($bits)));
    $poner(0, (8 - count($bits) % 8) % 8);
    for ($relleno = 0xEC; count($bits) < $capacidad; $relleno ^= 0xEC ^ 0x11) {
        $poner($relleno, 8);
    }
    $datos = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $byte = 0;
        for ($j = 0; $j < 8; $j++) {
            $byte = ($byte << 1) | $bits[$i + $j];
        }
        $datos[] = $byte;
    }

    // 3. Corrección de errores Reed-Solomon por bloque, y bloques intercalados
    $divisor = qrDivisorRS($eccPorBloque);
    $cortos = $numBloques - $total % $numBloques;
    $largoCorto = intdiv($total, $numBloques);
    $bloques = [];
    for ($i = 0, $k = 0; $i < $numBloques; $i++) {
        $tamano = $largoCorto - $eccPorBloque + ($i < $cortos ? 0 : 1);
        $dat = array_slice($datos, $k, $tamano);
        $k += $tamano;
        $ecc = qrRestoRS($dat, $divisor);
        if ($i < $cortos) {
            $dat[] = 0; // hueco para igualar largos al intercalar
        }
        $bloques[] = array_merge($dat, $ecc);
    }
    $codewords = [];
    for ($i = 0; $i < count($bloques[0]); $i++) {
        foreach ($bloques as $j => $bloque) {
            if ($i !== $largoCorto - $eccPorBloque || $j >= $cortos) {
                $codewords[] = $bloque[$i];
            }
        }
    }

    // 4. Patrones fijos (buscadores, tiempos, alineación, formato y versión)
    $lado = 17 + 4 * $version;
    $m = array_fill(0, $lado, array_fill(0, $lado, false));
    $fijo = array_fill(0, $lado, array_fill(0, $lado, false));
    $marcar = function (int $x, int $y, bool $oscuro) use (&$m, &$fijo) {
        $m[$y][$x] = $oscuro;
        $fijo[$y][$x] = true;
    };

    for ($i = 0; $i < $lado; $i++) {
        $marcar(6, $i, $i % 2 === 0);
        $marcar($i, 6, $i % 2 === 0);
    }
    foreach ([[3, 3], [$lado - 4, 3], [3, $lado - 4]] as [$cx, $cy]) {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x >= 0 && $x < $lado && $y >= 0 && $y < $lado) {
                    $d = max(abs($dx), abs($dy));
                    $marcar($x, $y, $d !== 2 && $d !== 4);
                }
            }
        }
    }
    $pos = QR_ALINEACION[$version];
    $n = count($pos);
    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $n; $j++) {
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                continue; // ahí están los buscadores
            }
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $marcar($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                }
            }
        }
    }
    qrFormato($marcar, $lado, 0); // reserva el área del formato
    if ($version >= 7) {
        $resto = $version;
        for ($i = 0; $i < 12; $i++) {
            $resto = ($resto << 1) ^ (($resto >> 11) * 0x1F25);
        }
        $bitsVersion = ($version << 12) | $resto;
        for ($i = 0; $i < 18; $i++) {
            $oscuro = (($bitsVersion >> $i) & 1) === 1;
            $a = $lado - 11 + $i % 3;
            $b = intdiv($i, 3);
            $marcar($a, $b, $oscuro);
            $marcar($b, $a, $oscuro);
        }
    }

    // 5. Datos en zigzag, de dos en dos columnas, de derecha a izquierda
    $i = 0;
    $totalBits = count($codewords) * 8;
    for ($derecha = $lado - 1; $derecha >= 1; $derecha -= 2) {
        if ($derecha === 6) {
            $derecha = 5; // salta la columna de tiempos
        }
        for ($vert = 0; $vert < $lado; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $derecha - $j;
                $subiendo = (($derecha + 1) & 2) === 0;
                $y = $subiendo ? $lado - 1 - $vert : $vert;
                if (!$fijo[$y][$x] && $i < $totalBits) {
                    $m[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                    $i++;
                }
            }
        }
    }

    // 6. Máscara: la indicada, o la de menor penalización
    $aplicar = function (array $matriz, int $mk) use ($fijo, $lado): array {
        for ($y = 0; $y < $lado; $y++) {
            for ($x = 0; $x < $lado; $x++) {
                if ($fijo[$y][$x]) {
                    continue;
                }
                $invertir = match ($mk) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    7 => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($invertir) {
                    $matriz[$y][$x] = !$matriz[$y][$x];
                }
            }
        }
        return $matriz;
    };
    $conFormato = function (array $matriz, int $mk) use ($lado): array {
        $poner = function (int $x, int $y, bool $oscuro) use (&$matriz) {
            $matriz[$y][$x] = $oscuro;
        };
        qrFormato($poner, $lado, $mk);
        return $matriz;
    };

    if ($mascara !== null) {
        return $conFormato($aplicar($m, $mascara), $mascara);
    }
    $mejor = null;
    $mejorPuntaje = PHP_INT_MAX;
    for ($mk = 0; $mk < 8; $mk++) {
        $candidata = $conFormato($aplicar($m, $mk), $mk);
        $puntaje = qrPenalizacion($candidata);
        if ($puntaje < $mejorPuntaje) {
            $mejor = $candidata;
            $mejorPuntaje = $puntaje;
        }
    }
    return $mejor;
}

// Bits de formato (nivel M + máscara) en sus dos copias, y el módulo oscuro fijo
function qrFormato(callable $marcar, int $lado, int $mascara): void
{
    $dato = (0b00 << 3) | $mascara; // M = 00
    $resto = $dato;
    for ($i = 0; $i < 10; $i++) {
        $resto = ($resto << 1) ^ (($resto >> 9) * 0x537);
    }
    $bits = (($dato << 10) | $resto) ^ 0x5412;
    $bit = fn(int $i) => (($bits >> $i) & 1) === 1;

    for ($i = 0; $i <= 5; $i++) {
        $marcar(8, $i, $bit($i));
    }
    $marcar(8, 7, $bit(6));
    $marcar(8, 8, $bit(7));
    $marcar(7, 8, $bit(8));
    for ($i = 9; $i < 15; $i++) {
        $marcar(14 - $i, 8, $bit($i));
    }
    for ($i = 0; $i < 8; $i++) {
        $marcar($lado - 1 - $i, 8, $bit($i));
    }
    for ($i = 8; $i < 15; $i++) {
        $marcar(8, $lado - 15 + $i, $bit($i));
    }
    $marcar(8, $lado - 8, true);
}

// Multiplicación en GF(256) con el polinomio 0x11D
function qrMultiplicar(int $x, int $y): int
{
    $z = 0;
    for ($i = 7; $i >= 0; $i--) {
        $z = ($z << 1) ^ (($z >> 7) * 0x11D);
        $z ^= (($y >> $i) & 1) * $x;
    }
    return $z;
}

function qrDivisorRS(int $grado): array
{
    $resultado = array_fill(0, $grado, 0);
    $resultado[$grado - 1] = 1;
    $raiz = 1;
    for ($i = 0; $i < $grado; $i++) {
        for ($j = 0; $j < $grado; $j++) {
            $resultado[$j] = qrMultiplicar($resultado[$j], $raiz);
            if ($j + 1 < $grado) {
                $resultado[$j] ^= $resultado[$j + 1];
            }
        }
        $raiz = qrMultiplicar($raiz, 0x02);
    }
    return $resultado;
}

function qrRestoRS(array $datos, array $divisor): array
{
    $resultado = array_fill(0, count($divisor), 0);
    foreach ($datos as $b) {
        $factor = $b ^ array_shift($resultado);
        $resultado[] = 0;
        foreach ($divisor as $i => $coef) {
            $resultado[$i] ^= qrMultiplicar($coef, $factor);
        }
    }
    return $resultado;
}

// Penalización del estándar: rachas, bloques 2x2, patrones parecidos a buscadores
// y desequilibrio entre oscuros y claros. Gana la máscara con menos puntos.
function qrPenalizacion(array $m): int
{
    $lado = count($m);
    $puntos = 0;
    $oscuros = 0;

    for ($eje = 0; $eje < 2; $eje++) {
        for ($a = 0; $a < $lado; $a++) {
            $linea = [];
            for ($b = 0; $b < $lado; $b++) {
                $linea[] = $eje === 0 ? $m[$a][$b] : $m[$b][$a];
            }
            // Rachas de 5 o más del mismo color
            $racha = 1;
            for ($b = 1; $b <= $lado; $b++) {
                if ($b < $lado && $linea[$b] === $linea[$b - 1]) {
                    $racha++;
                } else {
                    if ($racha >= 5) {
                        $puntos += 3 + ($racha - 5);
                    }
                    $racha = 1;
                }
            }
            // 1011101 con 4 claros a un lado
            $cadena = implode("", array_map(fn($v) => $v ? "1" : "0", $linea));
            $cadena = "0000" . $cadena . "0000";
            $puntos += 40 * (substr_count($cadena, "00001011101") + substr_count($cadena, "10111010000"));
        }
    }
    for ($y = 0; $y < $lado; $y++) {
        for ($x = 0; $x < $lado; $x++) {
            if ($m[$y][$x]) {
                $oscuros++;
            }
            if ($x < $lado - 1 && $y < $lado - 1) {
                $c = $m[$y][$x];
                if ($m[$y][$x + 1] === $c && $m[$y + 1][$x] === $c && $m[$y + 1][$x + 1] === $c) {
                    $puntos += 3;
                }
            }
        }
    }
    $total = $lado * $lado;
    $k = (int) ceil(abs($oscuros * 20 - $total * 10) / $total) - 1;
    return $puntos + max(0, $k) * 10;
}

// El QR como imagen SVG (nítido a cualquier tamaño, ideal para imprimir)
function qrSvg(string $texto, int $margen = 4): string
{
    $m = qrMatriz($texto);
    $lado = count($m);
    $total = $lado + 2 * $margen;
    $camino = "";
    for ($y = 0; $y < $lado; $y++) {
        for ($x = 0; $x < $lado; $x++) {
            if ($m[$y][$x]) {
                $camino .= "M" . ($x + $margen) . "," . ($y + $margen) . "h1v1h-1z";
            }
        }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges">'
        . '<rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="' . $camino . '"/></svg>';
}
