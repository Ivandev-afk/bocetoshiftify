<?php
// Incluir al principio de cada página privada: si no hay sesión, vuelve al login.
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}
