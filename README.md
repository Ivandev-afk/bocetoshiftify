# Shiftify

Landing page de **Shiftify**, una plataforma para gestionar turnos y filas en
comercios, bancos y centros de salud mediante código QR.

> Proyecto individual — *Desarrollo Básico de Aplicaciones en Red*

---

## Cómo verlo

No hay compilación ni dependencias. Basta abrir el archivo:

```bash
xdg-open index.html
```

O, si prefieres servirlo por HTTP (recomendado, evita restricciones del
protocolo `file://`):

```bash
python3 -m http.server 8000
# luego abre http://localhost:8000
```

---

## Estructura

```
.
├── index.html              Única página. Solo estructura y contenido.
├── assets/
│   ├── css/
│   │   ├── tokens.css      Paleta y escalas como variables CSS.
│   │   └── styles.css      Layout y componentes. No contiene colores.
│   └── img/                Imágenes (vacío: el mockup es HTML + CSS).
└── docs/
    ├── proceso.md          Bitácora: qué se cambió y por qué.
    └── paleta.md           Referencia de la paleta y auditoría de contraste.
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
| Sin JavaScript | Nada en esta página lo necesita. El menú responsive se resuelve con `flex-wrap` y `order`. |
| Mobile-first | El CSS base describe el móvil; las `@media (min-width)` agregan complejidad hacia arriba. Es más corto que el camino inverso. |
| Variables CSS nativas | Sin preprocesador (Sass) ni paso de compilación. Funcionan en todos los navegadores actuales. |
| Mockup en HTML + CSS | No depende de una imagen: escala sin pixelarse, pesa cero y su texto es real. |

---

## Accesibilidad

- Enlace **«Saltar al contenido»** como primer elemento enfocable.
- Jerarquía de encabezados correcta: un solo `<h1>`, sin saltos de nivel.
- `:focus-visible` estilizado, nunca eliminado.
- Todos los pares de color cumplen **WCAG 2.1 AA** — ver [docs/paleta.md](docs/paleta.md).
- Se respeta `prefers-reduced-motion`.
