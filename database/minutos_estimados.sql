-- Tiempo estimado que pone la gente (en vez de un 5 fijo al empezar el día):
--   empresas.minutos_base → valor inicial del negocio
--   cajas.minutos         → lo que dice el asesor de su caja
-- Ejecutar UNA vez sobre la base de Shiftify (en el hosting, quita la línea USE).

USE shiftify;

ALTER TABLE empresas ADD COLUMN minutos_base INT NOT NULL DEFAULT 10 AFTER slug;
ALTER TABLE cajas ADD COLUMN minutos INT NULL AFTER actividad;
