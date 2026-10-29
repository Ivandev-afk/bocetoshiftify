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
   New-Item -ItemType Junction -Path C:\xampp\htdocs\bocetoshiftify -Target "<ruta de este proyecto>"
   ```

3. Crea la base de datos: abre `http://localhost/phpmyadmin`, pestaña **SQL**,
   pega el contenido de [database/shiftify.sql](database/shiftify.sql) y pulsa
   **Continuar**.
4. Abre `http://localhost/bocetoshiftify/`.

Usuario de prueba: `admin@shiftify.co` / `Shiftify2026`.

La página para clientes es `http://localhost/bocetoshiftify/turno.php`: no pide
sesión y es la dirección que iría en el código QR.

---

## Estructura

```
.
├── index.html              Landing compacta con vista previa del panel.
├── login.php               Inicio de sesión contra la tabla usuarios.
├── panel.php               Panel de atención: métricas del día, turno actual y fila.
├── turnos.php              Acciones del panel: dar turno, llamar al siguiente, finalizar.
├── usuarios.php            Equipo: lista de cuentas y alta de miembros nuevos.
├── turno.php               Página pública (la del QR): el cliente pide turno y sigue la fila.
├── salir.php               Cierra la sesión.
├── includes/
│   ├── conexion.php        Conexión a MySQL (usuario root de XAMPP).
│   ├── sesion.php          Protege las páginas privadas.
│   ├── barra.php           Barra superior y pestañas de las páginas privadas.
│   └── funciones.php       Crear turno, código A-001, minutos por turno e iniciales.
├── database/
│   └── shiftify.sql        Crea la base de datos, las tablas y el usuario de prueba.
├── assets/
│   ├── img/
│   │   └── shiftify-logo.png  Logotipo de marca.
│   ├── css/
│   │   ├── tokens.css      Paleta y escalas como variables CSS.
│   │   └── styles.css      Layout y componentes; no contiene hexadecimales.
└── docs/
    ├── proceso.md         Bitácora de decisiones de diseño.
    └── paleta.md          Referencia de paleta y contraste de tokens.
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
