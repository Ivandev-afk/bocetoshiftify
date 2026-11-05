-- Base de datos de Shiftify para el hosting (KyraCloud).
-- La base ya la crea el hosting: abre su phpMyAdmin o consola, elige esa base
-- y ejecuta este archivo. Es igual a shiftify.sql sin CREATE DATABASE ni USE.



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

-- Fila de atención de cada empresa.
-- numero: la cuenta propia de la empresa; el código visible (A-001…) sale de él.
-- estado: esperando → atendiendo → atendido
CREATE TABLE IF NOT EXISTS turnos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    numero     INT NOT NULL,
    nombre     VARCHAR(100) NOT NULL,
    estado     ENUM('esperando', 'atendiendo', 'atendido') NOT NULL DEFAULT 'esperando',
    creado     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    llamado    TIMESTAMP NULL,
    finalizado TIMESTAMP NULL,
    UNIQUE (empresa_id, numero),
    FOREIGN KEY (empresa_id) REFERENCES empresas (id)
);

-- Empresa y usuario de prueba: admin@shiftify.co / Shiftify2026
INSERT IGNORE INTO empresas (id, nombre, slug) VALUES (1, 'Shiftify', 'shiftify');

INSERT IGNORE INTO usuarios (empresa_id, nombre, correo, contrasena) VALUES
    (1, 'Administrador', 'admin@shiftify.co', '$2y$10$YO.JEn3QOykEhAlD8KKUROuVnTRTSWtvv/HO20Zfh7i9GHKKFbtd.');
