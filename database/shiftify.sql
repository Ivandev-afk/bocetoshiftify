-- Base de datos de Shiftify.
-- Ejecutar en la pestaña SQL de phpMyAdmin (http://localhost/phpmyadmin).
-- Si ya tenías la base de antes (sin empresas), ejecuta multiempresa.sql en su lugar.

CREATE DATABASE IF NOT EXISTS shiftify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE shiftify;

-- Cada negocio que usa Shiftify. El slug va en la dirección de su QR:
-- turno.php?e=salon-dona-cecilia
CREATE TABLE IF NOT EXISTS empresas (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    slug   VARCHAR(60)  NOT NULL UNIQUE,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Personas que pueden entrar al panel; cada una pertenece a una empresa.
-- La contraseña nunca se guarda tal cual: se guarda el hash de password_hash().
CREATE TABLE IF NOT EXISTS usuarios (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nombre     VARCHAR(100) NOT NULL,
    correo     VARCHAR(150) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    creado     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas (id)
);

-- Cajas (ventanillas) de cada empresa: cada una atiende a una persona a la vez.
-- usuario_id: el asesor que la tiene tomada, o NULL si está libre.
-- UNIQUE (usuario_id): un asesor no puede tener dos cajas a la vez.
CREATE TABLE IF NOT EXISTS cajas (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    numero     INT NOT NULL,
    usuario_id INT NULL,
    -- Última vez que el panel del asesor estuvo abierto (para liberar cajas olvidadas)
    actividad  TIMESTAMP NULL,
    UNIQUE (empresa_id, numero),
    UNIQUE (usuario_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas (id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
);

-- Fila de atención de cada empresa.
-- numero: la cuenta propia de la empresa; el código visible (A-001…) sale de él.
-- estado: esperando → atendiendo → atendido, o esperando → cancelado
-- caja_id: la caja que lo llamó
CREATE TABLE IF NOT EXISTS turnos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    numero     INT NOT NULL,
    nombre     VARCHAR(100) NOT NULL,
    estado     ENUM('esperando', 'atendiendo', 'atendido', 'cancelado') NOT NULL DEFAULT 'esperando',
    caja_id    INT NULL,
    creado     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    llamado    TIMESTAMP NULL,
    finalizado TIMESTAMP NULL,
    UNIQUE (empresa_id, numero),
    FOREIGN KEY (empresa_id) REFERENCES empresas (id),
    FOREIGN KEY (caja_id) REFERENCES cajas (id)
);

-- Empresa y usuario de prueba: admin@shiftify.co / Shiftify2026
INSERT IGNORE INTO empresas (id, nombre, slug) VALUES (1, 'Shiftify', 'shiftify');

INSERT IGNORE INTO usuarios (empresa_id, nombre, correo, contrasena) VALUES
    (1, 'Administrador', 'admin@shiftify.co', '$2y$10$YO.JEn3QOykEhAlD8KKUROuVnTRTSWtvv/HO20Zfh7i9GHKKFbtd.');

-- Las 4 cajas de la empresa de prueba
INSERT IGNORE INTO cajas (empresa_id, numero) VALUES (1, 1), (1, 2), (1, 3), (1, 4);
