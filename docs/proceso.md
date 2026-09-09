# Bitácora del proceso

Registro de qué se cambió respecto a la versión inicial y por qué.
El punto de partida era un único `index.html` de 80 líneas: estructura
semántica básica, sin CSS y sin hoja de estilos vinculada.

---

## 1. Separar contenido de presentación

**Antes:** un archivo.
**Ahora:** `index.html` + `assets/css/tokens.css` + `assets/css/styles.css`.

`index.html` responde *qué dice la página*. El CSS responde *cómo se ve*.
Mezclar ambos es lo que vuelve imposible rediseñar sin romper el contenido.

El CSS a su vez se parte en dos capas:

- **`tokens.css`** — declara variables. No pinta nada.
- **`styles.css`** — usa esas variables. No define ningún color.

---

## 2. La paleta como código, en dos capas

La paleta llegó como una imagen. Convertida a variables queda en dos niveles:

```css
/* CAPA 1 — el color, con su nombre de la guía de marca */
--azul-principal: #1D4ED8;

/* CAPA 2 — para qué SIRVE ese color */
--acento: var(--azul-principal);
```

Los componentes **siempre** usan la capa 2:

```css
.boton--primario { background-color: var(--acento); }   /* sí */
.boton--primario { background-color: #1D4ED8; }         /* no */
```

**Por qué el rodeo.** Si mañana el azul de marca cambia a morado,
`.boton--primario` no se toca: sigue pidiendo «el acento». La capa 2 es un
contrato entre la marca y la interfaz.

Esa separación resolvió un problema real, descrito en el punto 6.

---

## 3. El `<h1>` estaba en el lugar equivocado

**Antes:**

```html
<header><h1>Shiftify</h1></header>
<section id="hero"><h2>Gestión de turnos ágil y sencilla</h2></section>
```

**Ahora:** el logo es un enlace normal y el `<h1>` es el titular del hero.

El `<h1>` describe **de qué trata la página**, no cómo se llama la empresa.
Buscadores y lectores de pantalla lo usan como resumen: «Shiftify» a secas no
dice nada. Además el `<h2>` del hero ya no cuelga de un `<h1>` que era el logo.

---

## 4. Listas con el significado correcto

**Cómo funciona → `<ol>`.** Eran tres `<article>` con el número escrito a mano
dentro del título (`1. Escanea el QR`). Son pasos **ordenados**: eso lo dice
`<ol>`, y el número lo genera el CSS con un contador.

```css
.pasos { counter-reset: paso; }
.paso::before { counter-increment: paso; content: counter(paso, decimal-leading-zero); }
```

Ganancia práctica: si se agrega o reordena un paso, la numeración se corrige
sola. Nunca vuelve a existir un «2.» duplicado.

**Beneficios → `<ul>`.** Son tres ítems equivalentes, sin jerarquía. Una lista
sin orden lo comunica y le dice al lector de pantalla cuántos elementos hay.

---

## 5. Correcciones de estructura y accesibilidad

| Problema | Corrección |
|---|---|
| No existía `<main>` | Envuelve el contenido y habilita el salto directo. |
| El mockup era `<aside>` | `<aside>` es para contenido *tangencial*; es contenido principal → `<section>`. |
| `<a href="#">Ver más</a>` suelto | Eliminado: no llevaba a ninguna parte. |
| Sin enlace de salto | Agregado «Saltar al contenido», primer elemento enfocable. |
| Secciones sin nombre accesible | `aria-labelledby` apuntando al propio `<h2>`. |
| Separadores `|` escritos a mano en el HTML | Ahora son ítems `<li>` y el espaciado lo pone el CSS. |
| Mockup como texto «Imagen: …» | Dibujado con HTML + CSS, con `role="img"` y `aria-label` descriptivo. |

---

## 6. El hallazgo: el verde de marca no cumple contraste

Al auditar los pares de color apareció un fallo:

```
Positivo #16A34A sobre blanco → 3.30:1
WCAG 2.1 AA para texto normal → mínimo 4.5:1
```

El verde de la paleta **no es legible como texto pequeño** sobre fondo claro.

Se descartó cambiar el verde de marca: sigue siendo correcto como relleno de
un badge o una barra, donde el requisito es 3:1, no 4.5:1.

**Solución.** Una variante oscurecida, usada únicamente cuando el verde es texto:

```css
--positivo:       #16A34A;   /* marca: rellenos, barras, badges */
--positivo-texto: #15803D;   /* 5.02:1 sobre blanco — cumple AA  */
--dato-positivo:  var(--positivo-texto);
```

Este es exactamente el caso que justifica la capa 2: el componente sigue
pidiendo `var(--dato-positivo)` sin enterarse de que detrás hay dos verdes.

Auditoría completa en [paleta.md](paleta.md).

---

## 7. Responsive sin JavaScript

El menú se reordena con dos propiedades:

```css
.navegacion { order: 3; width: 100%; }              /* móvil: baja a otra fila */

@media (min-width: 900px) {
  .navegacion { order: 0; width: auto; }            /* escritorio: misma fila */
}
```

El contenedor es `flex-wrap: wrap`: al ocupar el 100 % del ancho, el menú
obliga a un salto de línea y queda debajo del logo. En pantallas grandes
vuelve a su sitio. Sin menú hamburguesa, sin estado, sin JavaScript.

Los tamaños de texto usan `clamp()`, así que escalan de forma continua en vez
de saltar en cada punto de quiebre:

```css
--texto-3xl: clamp(2rem, 3.2vw + 1.2rem, 3.25rem);
```

---

## 8. Verificaciones automatizadas

Antes de dar por cerrado el trabajo se comprobó que:

1. `styles.css` no contiene **ningún** color hexadecimal.
2. Toda variable usada con `var()` existe en `tokens.css`.
3. No quedan tokens definidos sin usar — se eliminaron `--anillo-foco` y
   `--peso-normal`, y `--gris-oscuro` (que estaba en la paleta sin rol) pasó a
   ser `--contenido-medio` para el cuerpo de texto pequeño.
4. Las etiquetas HTML están balanceadas.
5. Todos los pares de color cumplen WCAG AA.

---

## Pendientes

- [ ] Formulario real en la sección de contacto (hoy es solo `mailto:`).
- [ ] Favicon.
- [ ] Etiquetas Open Graph para compartir en redes.
- [ ] Imagen `og:image` en `assets/img/`.
