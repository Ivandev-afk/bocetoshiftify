-- Índices para que la fila de hoy no revise todo el historial de turnos.
-- Sin ellos el panel se pone más lento cada día (con 60.000 turnos: 0,7 s → 0,3 s).
-- Ejecutar UNA vez sobre la base de Shiftify (en el hosting, quita la línea USE).

USE shiftify;

-- Fila de hoy de una empresa, atendidos del día y próximo a llamar
CREATE INDEX idx_turnos_fila ON turnos (empresa_id, estado, creado);
CREATE INDEX idx_turnos_fin  ON turnos (empresa_id, estado, finalizado);
-- Fila propia de cada caja, sus atendidos y su promedio del día
CREATE INDEX idx_turnos_caja ON turnos (caja_id, estado, llamado);
