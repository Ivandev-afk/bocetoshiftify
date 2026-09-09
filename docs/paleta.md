# Paleta Ejecutiva

> Sobria · Profesional · Confiable

Fuente única de verdad: [`assets/css/tokens.css`](../assets/css/tokens.css).
Este documento es solo referencia; **no** se copian colores desde aquí.

---

## Colores

| Color | Token | Hex | Uso |
|---|---|---|---|
| Azul oscuro | `--azul-oscuro` | `#0F172A` | Fondos de alto contraste: vitrina y pie |
| Azul principal | `--azul-principal` | `#1D4ED8` | Acento: botones, enlaces, antetítulos |
| Azul claro | `--azul-claro` | `#93C5FD` | Detalles sobre fondo oscuro, halo del hero |
| Gris oscuro | `--gris-oscuro` | `#374151` | Cuerpo de texto pequeño |
| Gris medio | `--gris-medio` | `#9CA3AF` | Texto sobre fondo oscuro, bordes fuertes |
| Gris claro | `--gris-claro` | `#E5E7EB` | Bordes y separadores |
| Fondo | `--fondo` | `#F8FAFC` | Fondo general de la página |
| Texto principal | `--texto-principal` | `#111827` | Títulos y texto de máxima jerarquía |
| Texto secundario | `--texto-secundario` | `#6B7280` | Texto de apoyo, entradillas |
| Positivo | `--positivo` | `#16A34A` | Rellenos y badges (no texto pequeño) |
| Negativo | `--negativo` | `#DC2626` | Indicadores de dato negativo |

---

## Auditoría de contraste (WCAG 2.1)

Umbrales: **4.5:1** texto normal · **3:1** texto grande y elementos no textuales.

| Frente | Fondo | Ratio | Nivel | Uso |
|---|---|---|---|---|
| Texto principal `#111827` | Fondo `#F8FAFC` | **16.96:1** | AAA | Texto |
| Texto secundario `#6B7280` | Fondo `#F8FAFC` | **4.62:1** | AA | Texto |
| Gris oscuro (cuerpo) `#374151` | Fondo `#F8FAFC` | **9.85:1** | AAA | Texto |
| Azul principal `#1D4ED8` | Blanco `#FFFFFF` | **6.70:1** | AA | Texto |
| Blanco `#FFFFFF` | Azul principal `#1D4ED8` | **6.70:1** | AA | Texto |
| Blanco `#FFFFFF` | Azul oscuro `#0F172A` | **17.85:1** | AAA | Texto |
| Gris medio `#9CA3AF` | Azul oscuro `#0F172A` | **7.03:1** | AAA | Texto |
| Azul claro `#93C5FD` | Azul oscuro `#0F172A` | **9.90:1** | AAA | Texto |
| Positivo (texto) `#15803D` | Blanco `#FFFFFF` | **5.02:1** | AA | Texto |
| Negativo `#DC2626` | Blanco `#FFFFFF` | **4.83:1** | AA | Texto |
| Positivo marca `#16A34A` | Blanco `#FFFFFF` | **3.30:1** | AA (no textual) | Solo relleno |

---

## Nota sobre el verde

El verde de marca `#16A34A` da **3.30:1** sobre blanco.
Sirve como relleno (umbral 3:1) pero **no como texto pequeño** (umbral 4.5:1).

Por eso existe una variante exclusiva para texto:

```css
--positivo:       #16A34A;   /* rellenos, barras, badges */
--positivo-texto: #15803D;   /* 5.02:1 sobre blanco — cumple AA */
```

Los componentes piden `var(--dato-positivo)` y no necesitan saber que
detrás hay dos verdes distintos.

---

## Cómo cambiar la marca

Editar **solo** la CAPA 1 de `tokens.css`. La capa de roles y todos los
componentes se actualizan solos. Después de cambiar cualquier color, volver
a correr la auditoría de contraste antes de dar por buena la nueva paleta.
