-- Pasa una base de Shiftify de antes (una sola fila para todos) a varias empresas.
-- Ejecutar UNA vez en la pestaña SQL de phpMyAdmin.
-- Todo lo que ya existía queda en la empresa 1, "Shiftify".

USE shiftify;

CREATE TABLE IF NOT EXISTS empresas (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    slug   VARCHAR(60)  NOT NULL UNIQUE,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO empresas (id, nombre, slug) VALUES (1, 'Shiftify', 'shiftify');

-- Usuarios: cada uno pasa a la empresa 1
ALTER TABLE usuarios ADD COLUMN empresa_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE usuarios ALTER COLUMN empresa_id DROP DEFAULT;
ALTER TABLE usuarios ADD FOREIGN KEY (empresa_id) REFERENCES empresas (id);

-- Turnos: empresa 1, y su número de empresa es el mismo id que tenían
ALTER TABLE turnos ADD COLUMN empresa_id INT NOT NULL DEFAULT 1 AFTER id,
                   ADD COLUMN numero     INT NOT NULL DEFAULT 0 AFTER empresa_id;
UPDATE turnos SET numero = id;
ALTER TABLE turnos ALTER COLUMN empresa_id DROP DEFAULT,
                   ALTER COLUMN numero     DROP DEFAULT;
ALTER TABLE turnos ADD UNIQUE (empresa_id, numero),
                   ADD FOREIGN KEY (empresa_id) REFERENCES empresas (id);
