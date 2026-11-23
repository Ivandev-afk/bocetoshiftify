<?php
// Incluir al principio de cada página privada: si no hay sesión, vuelve al login.
session_start();

if (!isset($_SESSION["usuario_id"], $_SESSION["empresa_id"])) {
    header("Location: login.php");
    exit;
}

// Empresa de quien inició sesión: todas las consultas del panel se filtran con ella
$empresaId = (int) $_SESSION["empresa_id"];
