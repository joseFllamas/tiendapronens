# Mapeo de atributos del Drupal 7 al modelo de Commerce 3

Tabla para revisar antes de migrar los 1150 productos. La columna **n** es el
número de productos afectados. Cuando un valor aparece en las dos fuentes se
indican las dos cuentas, y coinciden porque son los mismos productos.

Fuentes de origen, por cobertura sobre 1150 productos:

| Fuente | Cobertura |
|---|---|
| Paréntesis del título, 73 valores distintos | 929 |
| `field_talla_`, taxonomía talla, 27 términos | 573 |
| `field__tama_o`, taxonomía edades | 436 |
| SKU con `T-` | 179 |
| `field_talla`, lista de texto | 22 |
| `field_productcolor` | 20 |

Conclusión de la investigación: el color **no** es un eje de variación en el D7.
De los 438 displays, solo 8 tienen dato de color y los 8 tienen un único color.
El eje real es la talla, y aparecen dos ejes más que no estaban previstos:
medidas físicas y piezas de conjunto.

---

## Atributo Talla, ropa

Los valores marcados NUEVO hay que añadirlos, no existen todavía.

| Origen (título) | n | Origen (`field_talla_`) | n | Destino |
|---|---|---|---|---|
| `6 months` | 2 | `000 (6 months)` | 2 | 000 (6 meses) |
| `8 months` | 1 | `00 (8 months)` | 1 | 00 (8 meses) |
| `3M`, `0-3 meses` | 39 + 3 | — | | **NUEVO** 3 meses |
| `6M`, `3-6 meses` | 37 + 4 | — | | **NUEVO** 6 meses |
| `9M` | 38 | — | | **NUEVO** 9 meses |
| `12M`, `6-12 meses` | 41 + 3 | — | | **NUEVO** 12 meses |
| `18M`, `12-18 meses` | 44 + 1 | — | | **NUEVO** 18 meses |
| `0-1 years` | 4 | `0 (0-1 years)` | 30 | 0 (0-1 años) |
| `2`, `2-3 years` | 12 + 4 | `2  (2-3 years)` | 24 | 2 (2-3 años) |
| `4`, `3-4 years` | 23 + 27 | `4 (3-4 years)` | 59 | 4 (3-4 años) |
| `6`, `5-6 years` | 17 + 27 | `6 (5-6 years)` | 45 | 6 (5-6 años) |
| `8`, `7-8 years` | 15 + 24 | `8 (7-8 years)` | 39 | 8 (7-8 años) |
| `10`, `9-10 years` | 17 + 23 | `10 (9-10 years)` | 40 | 10 (9-10 años) |
| `12`, `11-12 years` | 14 + 3 | `12 (11-12 years)` | 17 | 12 (11-12 años) |
| `14`, `13-14 years` | 14 + 3 | `14 (13-14 years)` | 17 | 14 (13-14 años) |
| `16`, `16-XS`, ` XS` | 14 + 6 + 5 | `16-XS` | 20 | 16 / XS |
| `18-S`, `18-Small`, ` S` | 12 + 6 + 3 | `18-Small` | 18 | 18 / S |
| `20-M`, `20-Medium`, ` M` | 13 + 7 + 5 | `20-Medium` | 20 | 20 / M |
| `22-L`, `22-Large`, ` L` | 12 + 9 + 3 | `22-Large` | 21 | 22 / L |
| `24-XL`, ` XL` | 19 + 2 | `24-XL` | 19 | 24 / XL |
| `26-XXL`, ` XXL` | 3 + 3 | `26-XXL` | 3 | 26 / XXL |

**Duda a resolver: el valor `16`.** Lo he mapeado a "16 / XS" porque existe
`16-XS`, pero también podría ser "16 años" siguiendo la serie 10, 12, 14, 16.
Son 14 productos. Decidir.

---

## Atributo Medida, NUEVO

Para etiquetas identificativas y textil de hogar, donde lo que varía es una
medida física, no una talla de ropa.

| Origen (título) | n | Origen (`field_talla_`) | n | Destino |
|---|---|---|---|---|
| `0 - 6 años` | 3 | `0 - 6 años` | 3 | Infantil S (0-6 años) |
| `6 - 9 años` | 36 | `Infantil Medium (6 - 9 años) 6 x 15 cm` | 36 | Infantil M (6-9 años), 6 x 15 cm |
| `9-12 años` | 35 | `Infantil Large (9-12 años) 8,5 x 17 cm` | 35 | Infantil L (9-12 años), 8,5 x 17 cm |
| `6 - 12 años` | 2 | `6 - 12 años` | 2 | Infantil (6-12 años) |
| `+12 años` | 32 | `Adulto (+12 años) 12 x 18 cm` | 32 | Adulto (+12 años), 12 x 18 cm |
| `20 x 30cm` | — | `20 x 30cm` | 2 | 20 x 30 cm |
| `30 x 40cm` | 1 | — | | 30 x 40 cm |
| `32x45 cm` | — | `32x45 cm` | 4 | 32 x 45 cm |
| `40 x 40cm` | 5 | `40 x 40cm` | 81 | 40 x 40 cm |
| `50x70 cm` | 1 | `50x70 cm` | 1 | 50 x 70 cm |

Nótese que los tres primeros son a la vez edad y medida: en el D7 el término
lleva las dos cosas en el nombre. Propongo conservar ambas en la etiqueta.

---

## Atributo Pieza, NUEVO

Conjuntos de guardería. Verificado: cada display "Bolsa guardería impermeable X"
agrupa exactamente estas tres piezas, así que es un eje de variación legítimo.

| Origen (título) | n | Destino |
|---|---|---|
| `chupetera` | 74 | Chupetera |
| `almuerzo` | 76 | Bolsa de almuerzo |
| `muda` | 74 | Bolsa de muda |

---

## Atributo Formato, NUEVO

| Origen (título) | n | Origen (`field_talla_`) | n | Destino |
|---|---|---|---|---|
| `Cojín` | 1 | `Cojín (cushion)` | 1 | Cojín con relleno |
| `sin relleno` | 1 | `Funda cojin (cushion cover only)` | 1 | Solo funda |
| `Pack 10 pcs`, `Pack 10 uds` | 1 + 1 | — | | Pack de 10 |
| `Pack 20 pcs` | 1 | — | | Pack de 20 |
| `Pack 100 pcs` | 1 | — | | Pack de 100 |

`Manta` (1) y `Manta ajustable` (1) no son variantes del mismo artículo: son
productos distintos. Propongo no crear atributo y dejarlos como dos productos.

---

## Atributo Color

Solo 13 productos traen color en el título, siempre junto a la talla, más los 20
de `field_productcolor`. El resto del catálogo lleva el color en el nombre del
producto y no como opción.

| Origen (título) | n | Destino Talla | Destino Color |
|---|---|---|---|
| `Azul Celeste` | 1 | — | Azul celeste |
| `2, Azul Celeste` | 2 | 2 (2-3 años) | Azul celeste |
| `Naranja` | 2 | — | Naranja |
| `2, Naranja` | 2 | 2 (2-3 años) | Naranja |
| `4, Naranja` | 2 | 4 (3-4 años) | Naranja |
| `Rojo` | 1 | — | Rojo |
| `2, Rojo` | 2 | 2 (2-3 años) | Rojo |
| `4, Rojo` | 1 | 4 (3-4 años) | Rojo |
| `Rosa` | 1 | — | Rosa |
| `Verde pistacho` | 2 | — | Verde pistacho |
| `2, Verde pistacho` | 2 | 2 (2-3 años) | Verde pistacho |
| `4, Verde pistacho` | 2 | 4 (3-4 años) | Verde pistacho |

---

## Sin ninguna información de variación

116 productos no tienen paréntesis en el título ni `field_talla_`. Propongo
migrarlos como productos de una sola variación sin atributos, que es lo que son.

---

## Decisiones pendientes de tu confirmación

1. Crear los tres atributos nuevos: **Medida**, **Pieza** y **Formato**. La
   alternativa es meterlo todo en Talla, que dejaría desplegables mezclando
   `24-XL` con `50 x 70 cm` y `chupetera`.
2. El valor `16`: talla 16 años o 16 / XS. Afecta a 14 productos.
3. `Manta` y `Manta ajustable`: dos productos separados, o un atributo.
4. Los 74 productos huérfanos, sin ningún display que los referencie: migrar o
   descartar.

---

## Ampliación tras corregir la extracción de paréntesis anidados

La primera versión del extractor buscaba el último `(` y el último `)`, y con
títulos como `Bolsa mochila impermeable guardería Oso Tribal (Pequeño 25x30cm
(almuerzo))` devolvía `almuerzo)` con el cierre pegado. Consecuencias: las 224
piezas no se clasificaban y las medidas del nivel exterior se perdían.

Al corregirlo aparecieron nueve medidas que no estaban en la tabla original, y
que afectan a 254 productos:

| Origen | n | Destino |
|---|---|---|
| `Grande 38x40 cm`, `Grande 38x40cm` | 70 | Grande 38 x 40 cm |
| `Medio 28x30 cm` | 55 | Medio 28 x 30 cm |
| `Pequeño 20x20 cm` | 38 | Pequeño 20 x 20 cm |
| `Mini 14x14cm` | 19 | Mini 14 x 14 cm |
| `Pequeño 15x15 cm` | 17 | Pequeño 15 x 15 cm |
| `Pequeño 25x25cm` | 15 | Pequeño 25 x 25 cm |
| `Pequeño 25x28cm` | 4 | Pequeño 25 x 28 cm |
| `Grande 37x42cm` | 4 | Grande 37 x 42 cm |
| `Pequeño 25x30cm` | 2 | Pequeño 25 x 30 cm |

Más `Pack 5 unidades` y `Pack 10 unidades` al formato, `Funda cojin` suelto, y
las tallas `000` y `00` sueltas.

Segundo riesgo detectado y cerrado: trocear por coma suelta partía
`8,5 x 17 cm` en un `8` que se clasificaba como talla, un falso positivo en 33
productos. Ahora solo se trocea por coma seguida de espacio, que es el patrón
real de `(2, Azul Celeste)`.
