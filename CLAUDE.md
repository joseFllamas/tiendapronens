# CLAUDE.md — Rediseño tienda Pronens (Drupal 11 + Commerce 3)

Eres el desarrollador front/back de este proyecto Drupal. Tu fuente de verdad visual es
`design/Tienda Pronens.dc.html` (ábrelo en un navegador; la barra superior cambia entre Home,
Categoría y Ficha). El detalle completo de pantallas, tokens y comportamiento está en
`design/README.md`. **Recrea el diseño en el tema — no copies el HTML del prototipo.**

## Contexto del repo
- Drupal 11 (`web/` como docroot), Commerce 3 + PayPal + Sermepa + Shipping + Stock ya en composer.
- No hay tema custom todavía: créalo en `web/themes/custom/pronens` (starterkit de core).
- Idiomas: ES (por defecto), CA, FR, EN — módulos multilingües de core.
- **La migración desde el D7 está hecha**: 370 productos con 1076 variaciones (bundle `default`),
  taxonomías con imagen en Media (2325), 1578 usuarios con contraseña, direcciones, alias y 4 páginas.
  Checkout completo con envíos por zonas, IVA europeo, cupones y pasarelas (Redsys/PayPal/manual).

## Resoluciones ya tomadas (no rehacer ni "corregir" hacia el handoff)
Donde este documento y la realidad del repo discrepan, manda esta lista (decidida con cliente):
- **Bundle de producto**: se queda `default`, NO se crea `prenda`. Renombrar el bundle de 370
  productos migrados no aporta nada; el template del tema es `commerce-product--default.html.twig`.
- **Campos de personalización** (ya construidos en el order item por `pronens_personalizacion`):
  `field_texto_bordado` y `field_color_bordado`, etiquetado **"Formato del bordado"**.
- **NO hay selector de tipografía: la fuente es única** (decisión del cliente, 2026-07-26, revierte
  una decisión anterior del mismo día). El cliente elige el **formato** en un solo selector: cada
  término de `color_letra` es una combinación cerrada de la fuente única + colores del hilo,
  representada por la **foto de una letra bordada** (como el "Step 3: choose your initial colour"
  de la referencia; los 9 términos del D7 traen foto, Negro/Coral/Turquesa esperan la del taller).
  El vocabulario `fuente_bordado`, `field_fuente` y `field_fuentes_permitidas` quedan dormidos.
- **Modo por producto** (`field_modo_personalizacion`, ya activo en el add-to-cart): `inicial`
  admite una sola letra (maxlength 1 + validación en servidor) y `texto` un nombre de hasta **30**
  caracteres (no 12: el 21% de los 3374 bordados reales del D7 superan 12). Los 289 migrados están
  en `texto`; marcar `inicial` producto a producto donde toque.
- **El +5 € ya existe**: ajuste `fee` por unidad (configurable en
  `/admin/commerce/config/personalizacion` y por producto en `field_recargo`). No se toca el precio
  unitario: el desglose "X € + 5,00 € bordado" del prototipo depende de ello. El cupón no descuenta
  el bordado (misma regla que el envío), verificado y documentado.
- **Copys a corregir en el tema**: "Bordado incluido" (home) y "Bordado gratis" (tarjetas) son
  falsas: el bordado se cobra +5 €. Usar "Bordado en 72 h" o equivalente.
- **Medias deduplicados (2026-07-27)**: la migración guardó cada foto dos veces con nombres
  distintos (`foto.jpg` / `foto_0.jpg`, bytes idénticos), así que la imagen principal se repetía
  como primera de la galería en 344 productos. Se dedujeron los 467 grupos idénticos: referencias
  repuntadas al media canónico (galerías, `field_imagenes` de variaciones, términos), **2325 → 1008
  medias**, 484,8 MB liberados y los mapas `migrate_map_pronens_media_imagen`/`_file` apuntando al
  supervivente (un re-import ya no recrearía copias). **205 productos solo tienen una foto real**:
  la tarjeta hace el slide con esa misma imagen. Copia previa: snapshot `pre-dedupe-medias` y
  `/var/www/_backups_pronens/medias-duplicados-pre-dedupe.tar.gz`.
- **Ordenación por `created`, no por `changed`**: el re-guardado masivo del dedupe aplanó los
  `changed` de los productos. `created` conserva las fechas reales del D7 (2014–2026), así que es
  el campo para "novedades" y para la view de destacados.
- Los campos `field_posicion_bordado` y el modo inicial A-Z existen en el modelo pero NO se exponen.
- Las estrellas de valoración de la ficha requieren un módulo de reseñas aún no elegido (el D7
  tenía fivestar sin migrar): decidir antes de maquetar esa zona.
- **Facetas del catálogo (2026-07-27)**: el prototipo pinta Color / Talla / Precio / Solo
  personalizables, pero **el color no existe en los datos**: solo 19 de las 1076 variaciones tienen
  `attribute_color` y con 4 valores. Los ejes reales son **talla** (528 variaciones), **medida**
  (412) y **pieza** (224), y cada producto usa uno solo (109 variaciones no tienen ninguno). Las
  facetas son esas tres más precio y personalizable; facets oculta sola la que no aplica en la
  categoría abierta. Las muestras de color de la tarjeta se sustituyen por chips del eje real, que
  enlazan a la ficha con `?v=ID` (así preselecciona Commerce la variación).
- **Precio: dos campos en el índice**. `precio` = mínimo agregado, de valor único, para **ordenar**
  (el backend SQL no ordena por multivalor) y para el "desde X €". `precios` = todos los precios de
  las variaciones, para la **faceta**: dentro de una categoría el mínimo suele ser idéntico en todos
  los productos (las 74 bolsas arrancan las tres en 7,87 €) y filtrar por él no separa nada. La
  faceta de precio va **sin recuento**: el campo es multivalor y el backend cuenta filas, así que un
  producto con dos variaciones en el mismo tramo se contaría dos veces (el filtro sí es correcto).
- **La view del catálogo sobreescribe `entity.taxonomy_term.canonical`**, no crea ruta nueva: Views
  hace eso cuando su path choca con una existente. Consecuencias: la pantalla se reconoce por
  `view_id`, no por el nombre de ruta; se ha deshabilitado `views.view.taxonomy_term` de core; y las
  páginas de término de los vocabularios de atributo (`tamaño`, `escuelas`, `color_letra`…) pasan de
  200 vacío a **404**, que es correcto porque nunca listaron nada (la view de core lista nodos y
  aquí los productos no son nodos).
- **Los 433 alias del D7 son solo `es`**, así que `/ca/productos/<slug>` da 404 mientras
  `/ca/taxonomy/term/<tid>` funciona, y es lo que generan los menús: la navegación está bien en los
  4 idiomas. Pasar los alias a `und` haría funcionar los bonitos en todos los idiomas; es un cambio
  de datos con impacto en SEO, pendiente de decisión.
- **Basura del D7 en el catálogo**: los productos 5, 6, 7 y 359 ("Producto de ejemplo A", "prueba
  bordado" a 552.308,13 €, "prueba slogan", "Pedido 7682") están publicados pero **sin categoría**,
  así que no salen en ninguna página de categoría. Ensuciarían un buscador o catálogo global.
  Aparte, el producto 238 (sudadera lila) cuesta 388,41 €, que parece error de datos.
- **Las fotos de letra bordada NO existen (2026-07-27)**: la resolución de la ficha daba por hecho
  que los 9 términos de `color_letra` del D7 traían la foto de una letra bordada. No es así: son
  miniaturas de **82×93 de camisetas dobladas**, restos de un muestrario de color de prenda, y
  encima tres términos comparten fichero y otros dos también (en el propio D7 `color2.jpg`,
  `color2_0.jpg` y `color2_1.jpg` son idénticos byte a byte, así que el dedupe de medias fue
  correcto). Los otros 3 términos (Negro, Coral, Turquesa) solo tienen `field_color`. La ficha
  está construida para usar la foto cuando exista y con **200px de ancho mínimo** como umbral; por
  debajo cae a una muestra de color de `field_color`, y sin color al nombre a secas. En cuanto el
  taller suba las 12 fotos de letra a `field_imagen` la ficha las usa sin tocar código.
- **`field_relacionados` está vacío en los 370 productos**, así que "Combínalo con" cae a los 4
  productos más recientes del mismo término. Si el cliente rellena el campo, manda el campo.
- **Contraste AA del naranja**: el `#f4854e` del prototipo con texto blanco da 2,5:1 y el CTA es
  texto de 17px (no cuenta como texto grande), así que los **fondos** naranjas con texto blanco usan
  `--pro-orange-cta: #c2551f` (4,6:1) y el **texto** naranja sobre fondos claros usa
  `--pro-orange-ink: #a94d1c`. Mismo criterio que `--pro-teal-ink`. Si el cliente prefiere el
  naranja exacto del prototipo, la alternativa que pasa AA es texto oscuro sobre el naranja claro.
- **La zona de valoraciones de la ficha sigue sin maquetar**: falta elegir el módulo de reseñas.
- **Descripciones internas de campo**: `field_texto_bordado` y `field_color_bordado` traen notas de
  desarrollo ("Máximo real observado en el Drupal 7: 47 caracteres") que se veían en la tienda. El
  tema las oculta en el formulario y pone un placeholder con el límite real; conviene limpiarlas en
  la config del módulo cuando se pueda tocar.

## Orden de trabajo
1. **Tema `pronens`**: tokens CSS (custom properties con los colores/tipos del README), fuentes
   self-hosted WOFF2 (Archivo, Nunito Sans, Caveat), layout base, header sticky + marquee + footer.
2. **Estructura de contenido**: `composer require drupal/paragraphs drupal/facets drupal/search_api
   drupal/commerce_cart_flyout drupal/pathauto drupal/metatag` (verificar compatibilidad D11 de cada
   uno antes de requerir). Paragraphs de la Home. El catálogo, el producto y la personalización YA
   existen (ver Resoluciones): no crear tipos ni campos nuevos, solo exponerlos en el tema.
3. **Home**: templates Twig por paragraph, una library CSS/JS por componente.
4. **Categoría**: View + facets + toggle Vista 2/4 (JS del tema, localStorage) + tarjeta de producto
   (componente compartido con hover-cycle de galería y selección color/talla en tarjeta).
5. **Ficha**: galería grid 3:4, add-to-cart con variaciones (el personalizador funcional ya existe:
   card con checkbox, inicial o nombre según el producto, y selector de formato). Trabajo del tema:
   maquetar la card con los formatos como fotos de letra bordada (estilo "choose your initial
   colour") y la vista previa en vivo con la fuente única sobre la 1ª foto, ancla `bottom:44px`.
6. **Carrito flyout** con barra de progreso de envío gratis (umbral 60 €).

## Reglas de calidad (cada entrega debe cumplirlas)
- Twig limpio: nada de lógica de negocio en templates; preprocess en `.theme`.
- CSS: BEM, custom properties, sin frameworks; una library por componente, attach condicional.
- JS: vanilla, sin jQuery, < 100KB total; `defer` siempre; respetar `prefers-reduced-motion`.
- Imágenes: responsive_image + WebP, `aspect-ratio` en CSS, lazy salvo hero (eager + fetchpriority high + preload).
- Caché: no romper Page/Dynamic Page Cache; datos por sesión (contador carrito) con `#lazy_builder`;
  BigPipe activo; cache tags/contexts correctos en todo render array.
- Accesibilidad: contraste AA, foco visible, mega menú y flyout navegables por teclado (Escape cierra).
- **Verifica PageSpeed tras cada pantalla**: LCP < 2.5s, CLS < 0.1, INP < 200ms (Lighthouse CI o
  `npx lighthouse` contra el entorno local).

## Definition of done por pantalla
Comparación lado a lado con el prototipo (mismos espaciados/tipos/colores), funciona en los 4 idiomas,
editable por el cliente (Paragraphs/campos), Lighthouse Performance ≥ 90 en mobile, sin errores de
consola, y sin regresiones de caché (probar como anónimo).
