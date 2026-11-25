-- Notificaciones push: avisar al cliente aunque esté en otra app o con el
-- celular bloqueado. Ejecutar UNA vez sobre la base de Shiftify
-- (en el hosting, quita la línea USE).

USE shiftify;

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
