-- Agrega las cajas (ventanillas) a una base de Shiftify que ya existía.
-- Ejecutar UNA vez en la pestaña SQL de phpMyAdmin, sobre la base de Shiftify
-- (en el hosting, quita la línea USE).

USE shiftify;

-- Cada empresa tiene 4 cajas; cada asesor toma una y solo él la opera
CREATE TABLE IF NOT EXISTS cajas (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    numero     INT NOT NULL,
    usuario_id INT NULL,
    UNIQUE (empresa_id, numero),
    UNIQUE (usuario_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas (id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
);

INSERT IGNORE INTO cajas (empresa_id, numero)
SELECT e.id, n.numero
FROM empresas e
CROSS JOIN (SELECT 1 AS numero UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) n;

-- Turnos: saben qué caja los llamó, y el cliente puede cancelar el suyo
ALTER TABLE turnos MODIFY estado ENUM('esperando', 'atendiendo', 'atendido', 'cancelado') NOT NULL DEFAULT 'esperando',
                   ADD COLUMN caja_id INT NULL AFTER estado,
                   ADD FOREIGN KEY (caja_id) REFERENCES cajas (id);

-- Las atenciones que quedaron abiertas antes de existir las cajas se cierran
-- sin duración, para que no inflen la espera estimada
UPDATE turnos SET estado = 'atendido', finalizado = llamado WHERE estado = 'atendiendo';
