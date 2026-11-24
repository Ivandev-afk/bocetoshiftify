-- Agrega a las cajas la hora de la última actividad de su asesor.
-- Con ella el sistema libera sola una caja cuyo asesor se fue sin pulsar
-- «Dejar caja», para que los clientes no esperen en una caja vacía.
-- Ejecutar UNA vez sobre la base de Shiftify (en el hosting, quita la línea USE).

USE shiftify;

ALTER TABLE cajas ADD COLUMN actividad TIMESTAMP NULL AFTER usuario_id;

-- Las cajas que ya estaban tomadas cuentan como activas desde ahora
UPDATE cajas SET actividad = CURRENT_TIMESTAMP WHERE usuario_id IS NOT NULL;
