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
    -- Minutos por atención para estimar la espera mientras no hay datos reales del día
    minutos_base INT NOT NULL DEFAULT 10,
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
    -- Minutos que el asesor dice que tarda cada atención en su caja (null = el del negocio)
    minutos    INT NULL,
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
    -- Volver a llamar: veces que se le llamó, la última, y si respondió «Ya voy»
    llamadas       INT NOT NULL DEFAULT 0,
    ultimo_llamado TIMESTAMP NULL,
    confirmado     TIMESTAMP NULL,
    finalizado TIMESTAMP NULL,
    UNIQUE (empresa_id, numero),
    FOREIGN KEY (empresa_id) REFERENCES empresas (id),
    FOREIGN KEY (caja_id) REFERENCES cajas (id),
    -- Para que la fila de hoy no revise todo el historial (ver indices.sql)
    INDEX idx_turnos_fila (empresa_id, estado, creado),
    INDEX idx_turnos_fin  (empresa_id, estado, finalizado),
    INDEX idx_turnos_caja (caja_id, estado, llamado)
);

-- Cada celular que activó el aviso para un turno. El endpoint es la dirección
-- que da Google/Apple/Mozilla para hacerle llegar avisos a ese navegador.
CREATE TABLE IF NOT EXISTS suscripciones (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    turno_id  INT NOT NULL,
    endpoint  VARCHAR(600) NOT NULL,
    -- Para no repetir el mismo aviso
    preaviso  TINYINT NOT NULL DEFAULT 0,
    llamado   TINYINT NOT NULL DEFAULT 0,
    creado    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_suscripcion (turno_id, endpoint(255)),
    KEY idx_endpoint (endpoint(255)),
    FOREIGN KEY (turno_id) REFERENCES turnos (id) ON DELETE CASCADE
);

-- Ajustes del sistema. Aquí se guardan solas las claves VAPID con las que el
-- servidor firma los avisos (se crean la primera vez; nadie las escribe a mano).
CREATE TABLE IF NOT EXISTS ajustes (
    clave VARCHAR(50) PRIMARY KEY,
    valor TEXT NOT NULL
);


-- Empresa y usuario de prueba: admin@shiftify.co / Shiftify2026
INSERT IGNORE INTO empresas (id, nombre, slug) VALUES (1, 'Shiftify', 'shiftify');

INSERT IGNORE INTO usuarios (empresa_id, nombre, correo, contrasena) VALUES
    (1, 'Administrador', 'admin@shiftify.co', '$2y$10$YO.JEn3QOykEhAlD8KKUROuVnTRTSWtvv/HO20Zfh7i9GHKKFbtd.');

-- Las 4 cajas de la empresa de prueba
INSERT IGNORE INTO cajas (empresa_id, numero) VALUES (1, 1), (1, 2), (1, 3), (1, 4);
