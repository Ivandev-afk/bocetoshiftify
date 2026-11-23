# Publica Shiftify en internet con un enlace temporal de Cloudflare.
# Asi el QR funciona desde cualquier celular, con datos o con cualquier wifi.
#
# Uso: clic derecho sobre este archivo > "Ejecutar con PowerShell".
# Requisito: MySQL encendido en XAMPP.
# Para apagar el enlace, cierra esta ventana o pulsa Ctrl+C.
#
# Solo se publica la carpeta del proyecto (servidor de PHP en el puerto 8080):
# phpMyAdmin y el resto de XAMPP no quedan en internet.

$proyecto = $PSScriptRoot
$php      = "C:\xampp\php\php.exe"
$archivo  = Join-Path $proyecto "includes\url_publica.txt"
$log      = Join-Path $env:TEMP "shiftify-tunel.log"
$puerto   = 8080

$cloudflared = (Get-Command cloudflared -ErrorAction SilentlyContinue).Source
if (-not $cloudflared) {
    $cloudflared = @(
        "C:\Program Files (x86)\cloudflared\cloudflared.exe",
        "C:\Program Files\cloudflared\cloudflared.exe"
    ) | Where-Object { Test-Path $_ } | Select-Object -First 1
}
if (-not $cloudflared) {
    Write-Host "No encuentro cloudflared. Instalalo con: winget install Cloudflare.cloudflared --source winget" -ForegroundColor Red
    Read-Host "Pulsa Enter para salir"
    exit 1
}

Remove-Item $archivo, $log -ErrorAction SilentlyContinue

Write-Host "Encendiendo el servidor de Shiftify..."
$servidor = Start-Process $php -ArgumentList "-S 127.0.0.1:$puerto -t `"$proyecto`"" -WindowStyle Hidden -PassThru

Write-Host "Creando el enlace publico (tarda unos segundos)..."
$tunel = Start-Process $cloudflared -ArgumentList "tunnel --no-autoupdate --url http://127.0.0.1:$puerto" -RedirectStandardError $log -WindowStyle Hidden -PassThru

try {
    $url = $null
    for ($i = 0; $i -lt 60 -and -not $url; $i++) {
        Start-Sleep -Seconds 1
        if ($tunel.HasExited) { break }
        if (Test-Path $log) {
            $hallado = Select-String -Path $log -Pattern 'https://[a-z0-9-]+\.trycloudflare\.com' | Select-Object -First 1
            if ($hallado) { $url = $hallado.Matches[0].Value }
        }
    }

    if (-not $url) {
        Write-Host "No se pudo crear el enlace. Revisa tu conexion a internet." -ForegroundColor Red
        if (Test-Path $log) { Get-Content $log -Tail 10 }
        Read-Host "Pulsa Enter para salir"
        exit 1
    }

    Set-Content -Path $archivo -Value $url -Encoding ascii -NoNewline

    Write-Host ""
    Write-Host "Listo. Shiftify esta en internet:" -ForegroundColor Green
    Write-Host "  Inicio:           $url/"
    Write-Host "  Pedir turno:      $url/turno.php?e=shiftify"
    Write-Host "  Panel (login):    $url/login.php"
    Write-Host ""
    Write-Host "Recarga la pagina en el PC: el QR ya apunta a este enlace."
    Write-Host "No cierres esta ventana mientras lo uses. Ctrl+C para apagarlo."

    # Mientras el enlace vive, se renueva la fecha del archivo; qr.php la revisa
    # para no dibujar un QR hacia un enlace que ya se apago.
    while (-not $tunel.HasExited -and -not $servidor.HasExited) {
        (Get-Item $archivo).LastWriteTime = Get-Date
        Start-Sleep -Seconds 5
    }
    Write-Host "El enlace se cerro." -ForegroundColor Yellow
}
finally {
    Stop-Process -Id $tunel.Id, $servidor.Id -Force -ErrorAction SilentlyContinue
    Remove-Item $archivo -ErrorAction SilentlyContinue
}
