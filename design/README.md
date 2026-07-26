# Handoff: Rediseño Tienda Pronens (Home · Categoría · Ficha)

**Destino:** `tiendapronensd11/` (Drupal 11 + Commerce 3). Descomprime esta carpeta en `tiendapronensd11/design/` y sigue `CLAUDE.md`.

## Visión general
Rediseño e-commerce de Pronens (ropa infantil/escolar personalizada con bordado). Tres pantallas: **Home**, **Categoría** (listado) y **Ficha de producto**, más **carrito flyout** y **mega menú**. Dirección visual: minimal premium (ref. Anitials + Natura Selection) con paleta Pronens.

## Sobre los ficheros de diseño
`Tienda Pronens.dc.html` es una **referencia de diseño en HTML** (prototipo navegable), NO código de producción. Ábrela en un navegador: la barra oscura superior cambia entre las 3 pantallas. La tarea es **recrear este diseño en el tema Drupal** con Twig/CSS/JS propios del tema, siguiendo los patrones de Drupal — nunca copiar el HTML tal cual.

## Fidelidad
**Alta (hi-fi)**: colores, tipografías, espaciados e interacciones son finales. Recrear pixel-perfect.

## Arquitectura Drupal propuesta
- **Tema custom** `pronens` en `web/themes/custom/pronens` (starterkit de core; base `stable9`/standalone, sin Bootstrap ni frameworks CSS).
- **Home**: nodo único con **Paragraphs** (añadir `drupal/paragraphs`), un paragraph por sección: `hero`, `beneficios` (4 ítems), `mosaico_categorias` (4 refs a término+imagen), `pasos_personalizacion` (3 ítems), `best_sellers` (view embed + chips), `historia`, `newsletter`. Todo editable por el cliente.
- **Categoría**: View de productos por término del catálogo. Filtros con `drupal/facets` + Search API (color/talla/precio/personalizable), orden expuesto, paginación. Toggle "Vista 2/4" es JS del tema (persistir en `localStorage`).
- **Ficha**: template `commerce-product--prenda.html.twig`. Variaciones con atributos **color** y **talla**; galería = campo media múltiple. **Personalización bordado**: campos en el *order item type* (`field_nombre_bordado` texto máx. 12, `field_color_hilo` lista) expuestos en el formulario add-to-cart; +5 € vía **order processor** que ajusta el precio unitario cuando hay nombre.
- **Carrito flyout**: `drupal/commerce_cart_flyout` (o custom sobre Cart API). Contador del header como **lazy builder** (placeholder) para no romper page cache.
- **Mega menú**: menú principal a 2 niveles + campos imagen/etiqueta por término; render en Twig del tema (hover CSS/JS, sin plugin pesado).
- **Idiomas**: ES / CA / FR / EN con los módulos multilingües de core.

## Pantallas

### 1. Home
Orden de secciones (todas full-width, contenido a `max-width:1280px; padding:0 32px`):
1. **Marquee avisos** — fondo `#0E1B20`, texto blanco 10.5px/700/`letter-spacing:.22em`, animación translateX en bucle (28s, contenido duplicado). Altura fija para evitar CLS. Tres mensajes separados por punto turquesa.
2. **Header sticky** — blanco, borde inferior `#ECE7DE`, 62px, grid `1fr auto 1fr`: nav izquierda (Rebajas resaltado `#F9E547`, Bebé ▾, Escuela ▾, Sanitaria ▾, Personaliza), logo centrado (`pronens.` Archivo 800 25px, punto turquesa), derecha: buscar, cuenta, carrito con badge turquesa, ES/EN.
3. **Mega menú** (hover en Bebé/Escuela/Sanitaria) — panel full-width bajo el header, grid `1fr 1fr 1fr 360px`: col 1 VER TODO + NOVEDADES (14px/800/`ls .12em`); cols 2-3 subcategorías (15px/600, REBAJAS con fondo `#F9E547`); col 4 imagen destacada 210px + etiqueta.
4. **Hero** 600px full-bleed — foto `portada batas escolares.jpg`, scrim `linear-gradient(rgba(14,27,32,.2), rgba(14,27,32,.45))`, centrado: eyebrow 11px/800/`ls .42em`, H1 "REBAJAS VERANO" Archivo 900 76px blanco, subtítulo itálica 21px, 2 botones pill (blanco sólido + ghost borde blanco).
5. **Franja beneficios** — blanca, 4 columnas: punto turquesa 9px + título 13.5px/800 + sub 12px gris (`Envío gratis +60€ / Bordado incluido / Hecho en España / Cambios fáciles`).
6. **Mosaico categorías** — eyebrow COLECCIONES + H2 "Compra por categoría" Archivo 800 36px. Grid `1.2fr 1fr`, filas 280px, gap 16: izquierda tile alto (span 2), derecha tile ancho + 2 medios. Etiqueta chip blanca 10px/800/`ls .14em` abajo-izquierda de cada foto.
7. **Personalización** — fondo `#F3EEE6`, 2 columnas: foto 460px | eyebrow + H2 "Tres pasos para hacerlo *único.*" (único en turquesa itálica) + 3 pasos numerados 01/02/03 (número Archivo 700 13px turquesa, título 15px/800, texto 13.5px, separadores `#E4DCCE`) + botón oscuro pill.
8. **Best sellers** — eyebrow LOS FAVORITOS + H2 + chips filtro derecha (Todo/Bebé/Escuela; activo fondo `#10222B` blanco). Grid 4 tarjetas (ver "Tarjeta de producto").
9. **Historia** — fondo `#10222B`, grid `1.1fr 1fr`: H2 blanca Archivo 800 42px | 2 párrafos `#9FB3BA` 15px/1.7 + enlace `#7FD4DD`.
10. **Newsletter** — centrada: eyebrow + H2 34px + sub + input pill + botón oscuro.
11. **Footer** — fondo `#F7F3EC`, grid `1.6fr 1fr 1fr 1fr`: marca+contacto | TIENDA | AYUDA | EMPRESA; fila legal inferior.

### 2. Categoría
- Breadcrumb 12.5px, H1 Archivo (38px) + contador, intro máx. 640px.
- Barra filtros con borde superior/inferior: chips pill (Color con 4 puntos de muestra, Talla, Precio, "✎ Solo personalizables" naranja) + derecha "Vista 2 4" (activo subrayado 800) y "Ordenar".
- Grid productos: **vista 4** → `repeat(4, minmax(0,1fr))`, column-gap 16 / **vista 2** → column-gap 4 (imágenes grandes). row-gap 36. Tarjetas idénticas a best sellers.
- Paginación circular.

### 3. Ficha de producto
- Grid `calc(60% - 28px) calc(40% - 28px)`, gap 56.
- **Galería** (60%): grid 2 columnas gap 8, todas las fotos en ratio **3:4** (2 grandes arriba, resto debajo — sin miniaturas en desktop; en mobile 1 grande + miniaturas). Vista previa del bordado superpuesta en la 1ª foto.
- **Compra** (40%): eyebrow categoría, H1 36px, rating, precio 32px + desglose (`14,52 € + 5,00 € bordado`), swatches color 30px, tallas pill (activa fondo `#173039` blanca), **card personalizador** (borde `#7FD4DD`, fondo `#EFFAFB`): checkbox "Bordar su nombre +5 €", input nombre (máx. 12), 4 colores de hilo, y **vista previa en vivo** sobre la foto (fuente Caveat 44px del color del hilo elegido). Stepper cantidad + CTA naranja pill con precio total. 3 bullets confianza. Acordeones Descripción/Lavado/Envíos (`<details>`).
- **Relacionados**: 4 tarjetas ratio 3:4.

### Tarjeta de producto (listados)
- Imagen ratio **3:4**, fondo `#F4F2EE`, badge chip blanco 10px/800 arriba-izquierda.
- **Hover**: aparece la 2ª imagen de la galería y una barra de progreso segmentada abajo (segmentos 2.5px, blanco sobre blanco 45%; el activo se llena en **1.4s** lineal) que encadena 3ª, 4ª… en bucle. Al salir, vuelve a la 1ª. Implementar con CSS `background-image` + JS ligero (NO `<img>` extra que dispare fetch anticipado; precargar la 2ª imagen solo on-hover).
- Info: nombre 14px/800 + precio derecha, "Bordado gratis" 12px gris, **swatches de color clicables** (16px, seleccionado anillo `#10222B`) y **pills de talla clicables** (11px/800, activa oscura) — selección rápida sin entrar en ficha (variación preseleccionada al navegar).

### Carrito flyout
Panel 420px derecha, overlay `rgba(18,38,44,.45)`, slide-in 280ms `cubic-bezier(.2,.8,.3,1)`. Cabecera "Tu cesta (n)", **barra de progreso envío gratis** (fondo `#EFFAFB`, "Te faltan X € para el envío gratuito", barra turquesa animada, umbral 60 €), líneas con meta de personalización ("Talla 6M · Bordado: Martina (hilo coral)"), subtotal, CTA naranja "Tramitar pedido", "Seguir comprando".

## Design tokens
Colores: ink `#10222B` (y `#173039`), fondo `#FCFBF9`, crema `#F3EEE6`/`#F7F3EC`, gris imagen `#F4F2EE`, borde `#ECE7DE`/`#DCD5C9`, turquesa `#2E9DAA` (claro `#7FD4DD`, fondo `#EFFAFB`), naranja CTA `#F4854E` (hover `#E06F37`), amarillo rebajas `#F9E547`, texto secundario `#5B7078`, mute `#8AA0A6`.
Tipografía: **Archivo** 600–900 (display/H1-H2/logo), **Nunito Sans** 400–800 (UI/cuerpo), **Caveat** 600 (solo vista previa bordado). Eyebrows: 11px/800/`letter-spacing:.3em`/turquesa.
Radios: pills `999px`, chips/imágenes `0` (esquinas rectas), tallas ficha `12px`. Sombra panel: `0 32px 56px rgba(16,34,43,.1)`.

## Rendimiento (OBLIGATORIO — PageSpeed / Core Web Vitals)
Objetivos: **LCP < 2.5s · CLS < 0.1 · INP < 200ms · JS del tema < 100KB** total.
1. **Imágenes**: `responsive_image` (core) con image styles por breakpoint y **WebP**; ratio 3:4 fijado con `aspect-ratio` en CSS (cero CLS); lazy loading nativo (core) en todo MENOS el hero, que va `loading="eager"` + `fetchpriority="high"` + `<link rel="preload">`.
2. **Fuentes**: self-host WOFF2 en el tema (no CDN de Google), `font-display: swap`, preload solo de Archivo 800 y Nunito Sans 400/700, subset latin. Caveat puede cargarse diferida (solo ficha).
3. **CSS/JS**: libraries de Drupal por componente con `attach` condicional (el JS de galería solo en ficha, el de facetas solo en categoría); agregación de core activada; CSS crítico del above-the-fold inline en `html.html.twig`; **cero jQuery** en el front, vanilla JS.
4. **Animaciones**: marquee, hover-cycle y flyout en CSS transforms/opacity (compositor); respetar `prefers-reduced-motion`; alturas fijas en marquee y header (sin saltos).
5. **Caché**: Internal Page Cache + Dynamic Page Cache + **BigPipe** activos; contador del carrito y precios por sesión con `#lazy_builder`; cache tags de Commerce intactos (no `cache: false` globales en Twig).
6. **Prohibido**: sliders/carousels JS pesados (el hero es estático), CSS/JS de terceros bloqueante, imágenes sin dimensiones, base64 grandes, `@import` en CSS.

## Assets
Las fotos ya existen en `web/sites/default/files/` del proyecto. Mapa referencia → original:
- `assets/portada-batas.jpg` → `portada batas escolares.jpg` (hero)
- `assets/cat-baberos.jpg` → `baberos bebe personalizados pronens.jpg`
- `assets/cat-mochilas.jpg` → `mochilas guarderia personalizadas.jpg`
- `assets/cat-batas.jpg` → `batas escolares pronens.jpg`
- `assets/cat-bodys.jpg` / `cat-bodys-2.jpg` → `bodys bebe personalizados pronens(.jpg / _0.jpg)`
- `assets/cat-complementos.jpg` → `complementos bebe personalizados pronens.jpg`
- `assets/prod-bata-princesa.jpg` / `-2.jpg` → `Bata escolar princesa 1 pronens.jpg` / `Bata escolar princesa_1.jpg`
- `assets/prod-bata-rosa.jpg` → `comprar batas escolares rosa_1.jpg`
- `assets/prod-mochila.png` → `mochila.png` · `assets/prod-saquito.png` → `babi saquito_0.png` · `assets/prod-estuche.jpg` → `estuche-metalico-escolar.jpg` · `assets/newbata.jpg` → `newbata.jpg`

## Ficheros de este handoff
- `Tienda Pronens.dc.html` — prototipo navegable (Home/Categoría/Ficha + flyout + mega menú). Requiere `support.js` e `image-slot.js` junto a él.
- `assets/` — fotos usadas por el prototipo.
- `CLAUDE.md` — instrucciones de trabajo para Claude Code (copiar a la raíz del repo o a `design/`).
