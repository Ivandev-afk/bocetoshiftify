# Reglas de negocio de Shiftify

Qué debe cumplir el sistema, por qué, y dónde lo hace cumplir el código.
Cada regla tiene una prueba automática en
[pruebas/reglas_negocio.php](../pruebas/reglas_negocio.php) que la recorre
contra el sistema real (servidor PHP + MySQL), como lo haría una persona.

**Último resultado: 34 de 34 reglas se cumplen** →
[reglas-resultado.md](reglas-resultado.md).

---

## Cómo verificarlas

Con MySQL encendido en XAMPP:

```powershell
# Ventana 1: servidor de prueba (usa la base shiftify_pruebas, nunca la real)
powershell -ExecutionPolicy Bypass -File pruebas\servidor.ps1

# Ventana 2: pruebas
C:\xampp\php\php.exe pruebas\reglas_negocio.php
```

El script crea la base `shiftify_pruebas` desde
[database/shiftify.sql](../database/shiftify.sql), recorre las 34 reglas,
borra la base y escribe [reglas-resultado.md](reglas-resultado.md). Antes de
empezar comprueba que el servidor esté conectado a la base de prueba; si no lo
está, se detiene sin tocar ningún dato.

---

## Registro y cuentas

| Regla | Qué se exige | Por qué | Dónde |
|---|---|---|---|
| RN-01 | Registrar un negocio crea la empresa **y** su primera cuenta, y entra directo a su panel. | Un negocio sin cuenta, o una cuenta sin negocio, quedaría inutilizable. Por eso las dos se crean en una transacción. | [registro.php](../registro.php) |
| RN-02 | Cada negocio recibe una dirección de QR única (`salon-dona-cecilia`, `salon-dona-cecilia-2`…). | Dos negocios con el mismo nombre no pueden compartir fila. | [registro.php](../registro.php), `crearSlug()` |
| RN-03 | El registro rechaza datos vacíos, correos inválidos, contraseñas de menos de 8 caracteres, confirmaciones distintas y correos ya usados, sin crear nada. | La validación del navegador se puede saltar; la del servidor no. | [registro.php](../registro.php) |
| RN-04 | Las contraseñas se guardan con `password_hash()`, nunca en texto plano. | Si la base se filtra, las contraseñas no quedan expuestas. | [registro.php](../registro.php), [usuarios.php](../usuarios.php) |
| RN-05 | El acceso responde «Correo o contraseña incorrectos.» en los dos casos. | No revelar qué correos tienen cuenta. | [login.php](../login.php) |
| RN-06 | Sin sesión no se abre el panel, el equipo ni las acciones de la fila. | El panel maneja datos de los clientes del negocio. | [includes/sesion.php](../includes/sesion.php) |

## Fila y numeración

| Regla | Qué se exige | Por qué | Dónde |
|---|---|---|---|
| RN-07 | Los turnos se numeran por negocio: cada uno empieza en A-001 y sigue sin saltos. | El código del turno es lo que la persona escucha al ser llamada. | `crearTurno()` en [includes/funciones.php](../includes/funciones.php) |
| RN-08 | La base de datos rechaza un número repetido dentro del mismo negocio. | Segunda barrera: aunque el código fallara, nunca habría dos A-004. | `UNIQUE (empresa_id, numero)` en [database/shiftify.sql](../database/shiftify.sql) |
| RN-09 | El nombre es obligatorio y tiene como máximo 100 caracteres, tanto en el panel como desde el QR. | Es lo único que identifica a la persona en la fila. | [turnos.php](../turnos.php), [turno.php](../turno.php) |
| RN-10 | Desde el QR, el cliente ve su código, cuántas personas tiene antes, su espera y el nombre del negocio, y la página se actualiza sola. La espera se reparte entre las cajas abiertas. | Es la promesa de Shiftify: esperar donde quieras sabiendo cuánto falta. | [turno.php](../turno.php) |
| RN-11 | Cada persona tiene un solo turno activo por negocio. | Evita que alguien acapare la fila pidiendo varios turnos. | [turno.php](../turno.php) |
| RN-12 | La misma persona puede estar a la vez en la fila de dos negocios distintos. | Las filas de cada negocio son independientes. | `$_SESSION["mis_turnos"]` en [turno.php](../turno.php) |

## Atención

| Regla | Qué se exige | Por qué | Dónde |
|---|---|---|---|
| RN-13 | «Llamar siguiente» llama al turno más antiguo que espera. | Orden de llegada: nadie se salta la fila. | [turnos.php](../turnos.php) |
| RN-14 | Cada caja atiende a una persona a la vez: llamar al siguiente cierra la atención de esa caja y guarda su hora de fin. | Un asesor atiende a una persona a la vez. Los dos cambios van en una transacción. | [turnos.php](../turnos.php) |
| RN-15 | Un negocio nunca ve ni mueve la fila o el equipo de otro. | Todos comparten la misma base de datos. | `WHERE empresa_id = ?` en [panel.php](../panel.php), [turnos.php](../turnos.php), [usuarios.php](../usuarios.php) |
| RN-16 | El cliente ve cuándo es «el siguiente», cuándo es su turno y a qué caja acercarse, y cuándo terminó su atención; al terminar, la página deja de recargarse. | La persona no tiene que preguntar en la ventanilla. | [turno.php](../turno.php) |
| RN-17 | «Finalizar» cierra la atención sin llamar a nadie más. | Para pausar la atención sin dejar a alguien llamado. | [turnos.php](../turnos.php) |
| RN-18 | Con la fila vacía, «Llamar siguiente» está desactivado y avisa que no hay nadie. | Evita llamar a un turno que no existe. | [panel.php](../panel.php), [turnos.php](../turnos.php) |
| RN-19 | La espera estimada usa la duración media de las atenciones que empezaron hoy (5 min si aún no hay datos), dividida entre las cajas abiertas. | Una estimación fija se desvía en los negocios lentos o rápidos. | `minutosPorTurno()` en [includes/funciones.php](../includes/funciones.php) |
| RN-20 | Después de ser atendido, el cliente puede pedir un turno nuevo, que recibe el número siguiente. | Una misma persona puede volver el mismo día. | [turno.php](../turno.php) |
| RN-21 | Un turno que quedó esperando de un día anterior no entra en la fila de hoy, y su cliente puede pedir uno nuevo. | Quien no fue atendido ayer ya se fue: no puede quedar primero en la fila de hoy. | [panel.php](../panel.php), [turnos.php](../turnos.php), [turno.php](../turno.php) |

## Cajas y varios asesores

| Regla | Qué se exige | Por qué | Dónde |
|---|---|---|---|
| RN-27 | Cada negocio nace con 4 cajas libres, y el panel las muestra. | Varios asesores atienden la misma fila a la vez. | `crearCajas()`, [registro.php](../registro.php), [panel.php](../panel.php) |
| RN-28 | Un asesor atiende en una sola caja, y cada caja tiene un solo asesor. | Nadie puede acaparar cajas ni pisar la de otro. La base lo exige también con `UNIQUE (usuario_id)`. | [turnos.php](../turnos.php), [database/shiftify.sql](../database/shiftify.sql) |
| RN-29 | Solo quien tomó una caja puede llamar o finalizar desde ella. | Cada llamado tiene un responsable. | [turnos.php](../turnos.php) |
| RN-30 | Varias cajas atienden a la vez, en orden de llegada, y nunca llaman a la misma persona. | Si dos asesores pulsan «Llamar siguiente» al mismo tiempo, cada uno recibe un turno distinto: la acción bloquea la empresa mientras elige. | [turnos.php](../turnos.php) |
| RN-31 | Un asesor no puede dejar su caja con alguien en atención. | La persona llamada no queda abandonada en una caja vacía. | [turnos.php](../turnos.php) |
| RN-32 | Cerrar sesión libera la caja del asesor y cierra su atención. | Una caja no queda tomada por alguien que ya se fue. | [salir.php](../salir.php) |
| RN-33 | El cliente puede cancelar su turno mientras espera, y después pedir otro; si ya lo llamaron, no se cancela. | Quien se va libera su lugar, y la fila no espera a nadie que no vendrá. | [turno.php](../turno.php) |
| RN-34 | La espera no se dispara por una atención que quedó abierta desde otro día: solo cuentan las de hoy, con un tope de 60 min cada una. | Un turno que alguien olvidó finalizar mostraba ~36 940 minutos de espera. | `minutosPorTurno()` en [includes/funciones.php](../includes/funciones.php) |

## Equipo

| Regla | Qué se exige | Por qué | Dónde |
|---|---|---|---|
| RN-22 | Un miembro nuevo entra al negocio de quien lo crea, y su correo no puede estar usado en ningún negocio. | El correo es el usuario para iniciar sesión. | [usuarios.php](../usuarios.php) |
| RN-23 | Cerrar sesión impide volver a entrar al panel. | En un mostrador el computador es compartido. | [salir.php](../salir.php) |

## QR y seguridad

| Regla | Qué se exige | Por qué | Dónde |
|---|---|---|---|
| RN-24 | Un QR viejo o mal escrito responde 404 y no suma a nadie a ninguna fila; `qr.php` solo acepta direcciones válidas. | No sabemos a qué fila pertenece esa persona. | [turno.php](../turno.php), [qr.php](../qr.php) |
| RN-25 | Cada envío de formulario redirige, así que recargar la página no repite la acción. | Recargar no debe crear un turno duplicado ni llamar a dos personas. | [turnos.php](../turnos.php), [turno.php](../turno.php), [login.php](../login.php), [registro.php](../registro.php) |
| RN-26 | Lo que escribe el cliente se muestra como texto, nunca como código. | Un nombre con HTML no puede alterar el panel del negocio. | `htmlspecialchars()` en todas las vistas |

---

## Hallazgo durante la verificación

Al automatizar las reglas, **RN-21 falló**: un turno que quedaba esperando al
cerrar el día seguía apareciendo en «Fila de hoy» y, como tenía el número más
bajo, «Llamar siguiente» lo llamaba primero. El cliente, además, seguía viendo
ese turno como activo y no podía pedir otro.

**Corrección:** la fila de espera ahora se filtra por `DATE(creado) = CURDATE()`
en [panel.php](../panel.php) y [turnos.php](../turnos.php) (el mismo criterio
que ya usaba [xml_proyecto/api_xml.php](../xml_proyecto/api_xml.php)), y
[turno.php](../turno.php) descarta de la sesión un turno vencido para que la
persona pueda pedir uno nuevo. La prueba falla con el código anterior y pasa
con el corregido.

## Límites conocidos

No son fallas de las reglas, sino decisiones del alcance actual:

- **RN-11 se apoya en la sesión del navegador.** Quien borre sus cookies o
  use otro navegador puede pedir un segundo turno. Impedirlo exigiría
  identificar a la persona (por ejemplo, con su teléfono).
- **No hay estado «no se presentó».** Si alguien llamado no aparece, el
  personal lo finaliza y la atención cuenta como atendida.
