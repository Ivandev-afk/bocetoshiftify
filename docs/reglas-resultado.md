# Resultado de la verificación de reglas

Generado por `pruebas/reglas_negocio.php` el 23/11/2026 con PHP 8.0.30 y MySQL 10.4.32-MariaDB.

**34 de 34 reglas se cumplen.** Qué dice cada regla y por qué: [reglas-negocio.md](reglas-negocio.md).

| Regla | Descripción | Dónde se aplica | Resultado |
|---|---|---|---|
| RN-01 | Registrar un negocio crea la empresa y su primera cuenta, y entra a su panel | registro.php | ✅ Se cumple |
| RN-02 | Cada negocio recibe una dirección de QR única, aunque se llame igual que otro | registro.php · crearSlug() | ✅ Se cumple |
| RN-03 | El registro rechaza datos incompletos o inválidos y no crea nada | registro.php | ✅ Se cumple |
| RN-04 | Las contraseñas se guardan cifradas, nunca en texto plano | registro.php · usuarios.php · password_hash() | ✅ Se cumple |
| RN-05 | El acceso da el mismo mensaje si falla el correo o la contraseña | login.php | ✅ Se cumple |
| RN-06 | Sin sesión no se entra al panel, al equipo ni a las acciones de la fila | includes/sesion.php | ✅ Se cumple |
| RN-07 | Los turnos se numeran por negocio: cada uno empieza en A-001 y sigue sin saltos | includes/funciones.php · crearTurno() | ✅ Se cumple |
| RN-08 | La base de datos rechaza un número repetido dentro del mismo negocio | database/shiftify.sql · UNIQUE (empresa_id, numero) | ✅ Se cumple |
| RN-09 | El nombre es obligatorio y tiene máximo 100 caracteres (panel y QR) | turnos.php · turno.php | ✅ Se cumple |
| RN-10 | Desde el QR, el cliente ve su código, cuántas personas tiene antes y su espera | turno.php | ✅ Se cumple |
| RN-11 | Cada persona tiene un solo turno activo por negocio | turno.php | ✅ Se cumple |
| RN-12 | La misma persona puede estar a la vez en la fila de dos negocios distintos | turno.php · $_SESSION["mis_turnos"] | ✅ Se cumple |
| RN-13 | «Llamar siguiente» llama al turno más antiguo que espera | turnos.php | ✅ Se cumple |
| RN-14 | Cada caja atiende a una persona a la vez: llamar al siguiente cierra la actual | turnos.php · transacción | ✅ Se cumple |
| RN-15 | Un negocio nunca ve ni mueve la fila o el equipo de otro | panel.php · turnos.php · usuarios.php (WHERE empresa_id = ?) | ✅ Se cumple |
| RN-16 | El cliente ve cuándo es su turno, a qué caja ir y cuándo terminó su atención | turno.php | ✅ Se cumple |
| RN-17 | «Finalizar» cierra la atención sin llamar a nadie más | turnos.php | ✅ Se cumple |
| RN-18 | Con la fila vacía no se puede llamar a nadie | panel.php · turnos.php | ✅ Se cumple |
| RN-19 | La espera estimada usa la duración media de las atenciones de hoy | includes/funciones.php · minutosPorTurno() | ✅ Se cumple |
| RN-20 | Después de ser atendido, el cliente puede pedir un turno nuevo | turno.php | ✅ Se cumple |
| RN-21 | Un turno que quedó esperando de un día anterior no entra en la fila de hoy | panel.php · turnos.php · turno.php | ✅ Se cumple |
| RN-22 | Un miembro nuevo entra al negocio de quien lo crea | usuarios.php | ✅ Se cumple |
| RN-23 | Cerrar sesión impide volver a entrar al panel | salir.php | ✅ Se cumple |
| RN-27 | Cada negocio nace con 4 cajas libres, y el panel las muestra | registro.php · crearCajas() · panel.php | ✅ Se cumple |
| RN-28 | Un asesor atiende en una sola caja, y cada caja tiene un solo asesor | turnos.php · UNIQUE (usuario_id) | ✅ Se cumple |
| RN-29 | Solo quien tomó una caja puede llamar o finalizar desde ella | turnos.php | ✅ Se cumple |
| RN-30 | Varias cajas atienden a la vez, en orden de llegada y nunca a la misma persona | turnos.php · bloqueo de la empresa | ✅ Se cumple |
| RN-31 | Un asesor no deja su caja con alguien en atención | turnos.php | ✅ Se cumple |
| RN-32 | Cerrar sesión libera la caja del asesor y cierra su atención | salir.php | ✅ Se cumple |
| RN-33 | El cliente puede cancelar su turno mientras espera y pedir otro | turno.php | ✅ Se cumple |
| RN-34 | La espera no se dispara por una atención que quedó abierta desde otro día | includes/funciones.php · minutosPorTurno() | ✅ Se cumple |
| RN-24 | Un QR viejo o mal escrito no suma a nadie a una fila | turno.php · qr.php | ✅ Se cumple |
| RN-25 | Recargar la página no repite una acción (cada envío redirige) | turnos.php · turno.php · login.php · registro.php | ✅ Se cumple |
| RN-26 | Lo que escribe el cliente se muestra como texto, nunca como código | htmlspecialchars() en todas las vistas | ✅ Se cumple |
