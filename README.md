# Shiftify

Landing page de **Shiftify**, una plataforma para gestionar turnos y filas en
comercios, bancos y centros de salud mediante código QR.

> Proyecto individual — *Desarrollo Básico de Aplicaciones en Red*

---

## Cómo verlo

El inicio de sesión usa PHP y MySQL, así que el proyecto se sirve con
**XAMPP**. Live Server o abrir los archivos con doble clic no ejecutan PHP.

1. En el XAMPP Control Panel arranca **Apache** y **MySQL**.
2. Haz que Apache vea el proyecto. Sin moverlo de su carpeta, crea un enlace
   dentro de `htdocs` (PowerShell):

   ```powershell
   New-Item -ItemType Junction -Path C:\xampp\htdocs\proyecto-shiftify -Target "<ruta de este proyecto>"
   ```

3. Crea la base de datos: abre `http://localhost/phpmyadmin`, pestaña **SQL**,
   pega el contenido de [database/shiftify.sql](database/shiftify.sql) y pulsa
   **Continuar**. Si ya tenías la base de antes, sin empresas, ejecuta
   [database/multiempresa.sql](database/multiempresa.sql) una sola vez. Si tu
   base es anterior a las cajas, ejecuta además
   [database/cajas.sql](database/cajas.sql) una sola vez.
4. Abre `http://localhost/proyecto-shiftify/`.

Usuario de prueba: `admin@shiftify.co` / `Shiftify2026`.

### Versión publicada

**https://ivandev-afk.github.io/bocetoshiftify/**

GitHub Pages solo sirve páginas estáticas, así que ahí no corren PHP ni MySQL.
Lo que se publica es la landing y las
[maquetas de las pantallas](https://ivandev-afk.github.io/bocetoshiftify/pantallas/index.html):
copias reales del panel, del equipo y de la página del cliente, navegables con
datos de ejemplo. Los archivos PHP y de servidor no se publican
([_config.yml](_config.yml)); quien abra una de esas direcciones ve
[404.html](404.html), que lo lleva a las maquetas.

### Reglas de negocio y maquetas

Las 34 reglas de negocio están en [docs/reglas-negocio.md](docs/reglas-negocio.md)
y se verifican de forma automática contra el sistema real. Con MySQL encendido:

```powershell
# Ventana 1: servidor de prueba, conectado a la base shiftify_pruebas
powershell -ExecutionPolicy Bypass -File pruebas\servidor.ps1

# Ventana 2
C:\xampp\php\php.exe pruebas\reglas_negocio.php   # verifica las reglas → docs/reglas-resultado.md
C:\xampp\php\php.exe pruebas\maquetas.php         # vuelve a generar pantallas/*.html
```

Ninguno de los dos toca la base real `shiftify`: crean `shiftify_pruebas` y la
borran al terminar.

> **Antivirus.** Avast marca como `IDP.Generic` cualquier script PHP de consola
> que hace peticiones HTTP (le pasa también al `cliente.php` de la práctica
> XML) y lo pone en cuarentena al terminar. Es un falso positivo: si ocurre,
> restaura el archivo desde el Baúl de virus y agrega la carpeta del proyecto
> como excepción.

### Varias empresas

Todo vive en una sola base de datos. Cada negocio es una fila de la tabla
`empresas`, y cada usuario y cada turno guarda su `empresa_id`. El panel filtra
todas las consultas por la empresa de la sesión, así que un negocio nunca ve la
fila ni el equipo de otro. Los turnos se numeran por empresa: cada una empieza
en A-001.

- Un negocio nuevo se registra en `registro.php`, enlazado desde el login.
- La página para clientes lleva la empresa en la dirección:
  `http://localhost/proyecto-shiftify/turno.php?e=shiftify`. Esa es la
  dirección del código QR, que cada negocio ve en su panel.

---

## Estructura

```
.
├── index.html              Landing compacta con vista previa del panel.
├── login.php               Inicio de sesión contra la tabla usuarios.
├── registro.php            Registro de un negocio nuevo con su primera cuenta.
├── panel.php               Panel de atención: métricas del día, turno actual y fila.
├── turnos.php              Acciones del panel: dar turno, llamar al siguiente, finalizar.
├── usuarios.php            Equipo: lista de cuentas y alta de miembros nuevos.
├── turno.php               Página pública (la del QR): el cliente pide turno y sigue la fila.
├── qr.php                  Imagen del QR de la fila de una empresa.
├── salir.php               Cierra la sesión.
├── includes/
│   ├── conexion.php        Conexión a MySQL (usuario root de XAMPP).
│   ├── sesion.php          Protege las páginas privadas.
│   ├── barra.php           Barra superior y pestañas de las páginas privadas.
│   └── funciones.php       Consultas preparadas, empresas, crear turno, código A-001, minutos por turno.
├── database/
│   ├── shiftify.sql        Crea la base de datos, las tablas y el usuario de prueba.
│   ├── multiempresa.sql    Pasa una base de antes a varias empresas.
│   ├── cajas.sql           Agrega las 4 cajas y la cancelación a una base de antes.
│   └── hosting.sql         Igual que shiftify.sql, para la base que crea el hosting.
├── publicar.ps1            Enlace público temporal (Cloudflare) para probar el QR desde cualquier red.
├── pantallas/              Maquetas estáticas de las pantallas operativas (se ven en GitHub Pages).
│   ├── index.html          Galería: personal del negocio y cliente desde el QR, con sus reglas.
│   └── *.html              Cada pantalla, generada desde el sistema real por maquetas.php.
├── pruebas/
│   ├── reglas_negocio.php  Verifica las 34 reglas de negocio contra el sistema real.
│   ├── maquetas.php        Genera pantallas/*.html recorriendo un día de atención.
│   ├── servidor.ps1        Servidor de PHP conectado a la base de prueba.
│   └── lib.php             Base de prueba y "navegador" con su cookie de sesión.
├── _config.yml             GitHub Pages: no publicar los archivos PHP ni de servidor.
├── 404.html                Página de GitHub Pages para las direcciones que necesitan PHP.
├── xml_proyecto/           Práctica XML: la fila de turnos como XML con DTD, XPath y XSLT.
│   ├── turnos.xml / .dtd / .xsl   Documento, contrato y hoja de transformación.
│   ├── api_xml.php         Servicio que genera el XML desde la tabla turnos.
│   └── servicio.php        Servicio que entrega turnos.xml tal cual.
├── assets/
│   ├── img/
│   │   └── shiftify-logo.png  Logotipo de marca.
│   ├── css/
│   │   ├── tokens.css      Paleta y escalas como variables CSS.
│   │   └── styles.css      Layout y componentes; no contiene hexadecimales.
└── docs/
    ├── proceso.md          Bitácora de decisiones de diseño.
    ├── paleta.md           Referencia de paleta y contraste de tokens.
    ├── reglas-negocio.md   Las reglas de negocio: qué, por qué y dónde se aplican.
    └── reglas-resultado.md Último resultado de la verificación (lo escribe reglas_negocio.php).
```

### Por qué el CSS está partido en dos

`tokens.css` declara **qué colores existen**; `styles.css` decide **cómo se ven
las cosas**. La regla del proyecto es que `styles.css` no contiene ni un solo
color en hexadecimal: todo se pide con `var(--rol)`.

La ventaja es concreta: cambiar la marca entera significa editar un archivo de
variables, no buscar y reemplazar colores por todo el proyecto.

---

## Decisiones técnicas

| Decisión | Motivo |
|---|---|
| Sin frameworks (no Bootstrap, no Tailwind) | El objetivo es demostrar HTML y CSS. Un framework escondería precisamente lo que se evalúa. |
| Sin JavaScript | Todo lo resuelve PHP en el servidor. La página del cliente se mantiene al día con `<meta http-equiv="refresh">` cada 15 segundos. |
| Maquetas generadas, no dibujadas | Las pantallas de `pantallas/` salen del sistema real; si cambia una vista, se regeneran y no quedan desactualizadas. |
| Pruebas por HTTP, sin framework | `reglas_negocio.php` usa la aplicación como un navegador: lo que verifica es lo que vería una persona. |
| PHP + MySQL (XAMPP) | Consultas preparadas contra inyección SQL, contraseñas guardadas con `password_hash()` y sesión regenerada al entrar. |
| Diseño responsive | La landing y la pantalla de acceso se adaptan a móviles y escritorio con CSS. |
| Variables CSS nativas | Sin preprocesador (Sass) ni paso de compilación. Funcionan en todos los navegadores actuales. |
| Vista previa en HTML + CSS | El panel de producto se dibuja sin imágenes externas; los datos están marcados como ejemplo. |

---

## Accesibilidad

- Enlace **«Saltar al contenido»** como primer elemento enfocable.
- Jerarquía de encabezados correcta: un solo `<h1>`, sin saltos de nivel.
- `:focus-visible` estilizado, nunca eliminado.
- [docs/paleta.md](docs/paleta.md) documenta el contraste de los tokens de marca; los nuevos estados y mezclas de color deben auditarse si se usan como texto.
- Se respeta `prefers-reduced-motion`.
