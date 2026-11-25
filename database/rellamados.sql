-- Volver a llamar a quien no responde: cuántas veces se le llamó, cuándo fue
-- la última y si contestó «Ya voy».
-- Ejecutar UNA vez sobre la base de Shiftify (en el hosting, quita la línea USE).

USE shiftify;

ALTER TABLE turnos
    ADD COLUMN llamadas       INT NOT NULL DEFAULT 0 AFTER llamado,
    ADD COLUMN ultimo_llamado TIMESTAMP NULL AFTER llamadas,
    ADD COLUMN confirmado     TIMESTAMP NULL AFTER ultimo_llamado;
