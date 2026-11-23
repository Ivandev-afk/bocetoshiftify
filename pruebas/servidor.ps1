# Servidor de prueba de Shiftify, conectado a la base "shiftify_pruebas".
#
# Uso: en una ventana de PowerShell, desde la carpeta del proyecto:
#     powershell -ExecutionPolicy Bypass -File pruebas\servidor.ps1
# Deja la ventana abierta y, en otra, ejecuta:
#     C:\xampp\php\php.exe pruebas\reglas_negocio.php
#     C:\xampp\php\php.exe pruebas\maquetas.php
# Para apagarlo, Ctrl+C. Requisito: MySQL encendido en XAMPP.
#
# conexion.php lee DB_NAME del entorno: este servidor nunca toca la base real.

$proyecto = Split-Path $PSScriptRoot -Parent
$php      = "C:\xampp\php\php.exe"

$env:DB_NAME = "shiftify_pruebas"
Write-Host "Servidor de prueba en http://127.0.0.1:8099 (base shiftify_pruebas). Ctrl+C para apagarlo."
& $php -S 127.0.0.1:8099 -t $proyecto
