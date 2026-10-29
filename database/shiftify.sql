-- Base de datos de Shiftify.
-- Ejecutar en la pestaña SQL de phpMyAdmin (http://localhost/phpmyadmin).

CREATE DATABASE IF NOT EXISTS shiftify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE shiftify;

-- Personas que pueden entrar al panel.
-- La contraseña nunca se guarda tal cual: se guarda el hash de password_hash().
CREATE TABLE IF NOT EXISTS usuarios (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(100) NOT NULL,
    correo     VARCHAR(150) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    creado     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Fila de atención. El código visible (A-001, A-002…) se calcula a partir del id.
-- estado: esperando → atendiendo → atendido
CREATE TABLE IF NOT EXISTS turnos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(100) NOT NULL,
    estado     ENUM('esperando', 'atendiendo', 'atendido') NOT NULL DEFAULT 'esperando',
    creado     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    llamado    TIMESTAMP NULL,
    finalizado TIMESTAMP NULL
);

-- Usuario de prueba: admin@shiftify.co / Shiftify2026
INSERT IGNORE INTO usuarios (nombre, correo, contrasena) VALUES
    ('Administrador', 'admin@shiftify.co', '$2y$10$YO.JEn3QOykEhAlD8KKUROuVnTRTSWtvv/HO20Zfh7i9GHKKFbtd.');
