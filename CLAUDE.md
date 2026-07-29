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
  que los términos de `color_letra` del D7 traían la foto de una letra bordada. No es así: son
  miniaturas de **82×93 de camisetas dobladas**, restos de un muestrario de color de prenda, y
  encima tres términos comparten fichero y otros dos también (en el propio D7 `color2.jpg`,
  `color2_0.jpg` y `color2_1.jpg` son idénticos byte a byte, así que el dedupe de medias fue
  correcto). La ficha está construida para usar la foto cuando exista y con **200px de ancho mínimo**
  como umbral; por debajo cae a una muestra de color de `field_color`, y sin color al **número** de la
  combinación. En cuanto el taller suba las 6 fotos de letra a `field_imagen` la ficha las usa sin
  tocar código.
- **Los formatos del bordado son 6, y el selector es solo del modo inicial (2026-07-29)**. Lo que
  había venía de un experimento muerto: el vocabulario `color_letra` del D7 existía únicamente en el
  tipo de línea `custom_color_product`, de un solo producto (`producto_costumizado_color`, 1 ficha), y
  **ningún pedido lo usó jamás** (de las 7879 líneas con bordado del D7 todas eran `producto_bordado`
  y `field_data_field_color_bordado` está vacía). Los 3 términos Negro/Coral/Turquesa se habían creado
  a mano aquí con los colores del prototipo. La lista real la fija la foto de tienda "MOCHILA INICIAL
  BORDADA · STEP 3: CHOOSE YOUR INITIAL COLOUR": **negro/blanco, blanco/verde, blanco/rojo,
  blanco/marino, blanco/rosa y todo blanco**, con la convención del D7 (perfil = contorno, interior =
  relleno) y **numeradas 1–6 igual que en la foto**. Los que no se ofrecen quedan **despublicados**,
  no borrados: `TermSelection` filtra por `status` para quien no administra taxonomía, así que
  desaparecen de la tienda y el histórico se conserva.
- **El D7 tenía cuatro tipos de producto**, no dos: `product` (estándar, 92 fichas),
  `producto_costumizado` (326, solo texto a bordar), `producto_costumizado_color` (1, texto + color) y
  `producto_escuela` (19). Y ojo: `field_nombre_del_ni_o_a` (3883 registros) **no es bordado**, está
  en `commerce_customer_profile` y es el nombre del niño en la dirección de entrega. El bordado real
  es `field_bordar_texto`, 3374 registros en la línea de pedido.
- **Guía visual del bordado**: bloque de contenido del tipo `guia_bordado` (foto + texto) que el
  cliente edita en `/admin/content/block`. La ficha lo abre en un `<dialog>` nativo desde el enlace
  "¿Cómo queda?" que el JS pone junto al selector. La foto actual se importó de la ficha de Amazon de
  Pronens (679×699): **conviene sustituirla por el original del taller**.
- **Extras de producto (2026-07-29)**: complementos opcionales con o sin sobrecoste, montados sobre la
  misma maquinaria del +5 € del bordado. Se descartó contrib: `commerce_product_bundle` es alpha para
  core ^9, `commerce_addon` está abandonado y `commerce_product_options` solo tiene `1.0.x-dev` sin
  cobertura de seguridad, que no se pone en la ruta de compra de una tienda viva. El modelo:
  vocabulario `extras` (con `field_precio`, `field_pide_texto` y `field_imagen`), `field_extras_disponibles`
  en el producto (qué extras ofrece **ese** producto, de modo que el llavero no aparece en un polo sin
  tocar código), y `field_extras` + `field_extras_texto` en la línea de pedido. `ExtrasOrderProcessor`
  añade un ajuste `fee` por extra y por unidad, con el nombre del extra como etiqueta; va en prioridad
  149, justo detrás del recargo del bordado. `ExtrasCalculator` es lógica pura con sus tests unitarios.
- **El primer producto de inicial ya existe**: "Mochila personalizada con inicial bordada" (id 373,
  `/productos/mochila-personalizada-con-inicial-bordada`), con 2 tamaños × 10 colores = **20
  variaciones**, la inicial, el formato, la guía y el extra del llavero (+6 €). **Los precios (18,95 €
  infantil / 23,95 € adulto) y el stock (100 uds) son marcadores**: no se facilitaron y hay que
  revisarlos. Las fotos vienen de la carpeta `initials/` del repo, ya importadas como media.
- **Muestras de color en el selector de variación**: el atributo `color` ya venía con
  `commerce_product_rendered_attribute`, así que solo faltaba que el display `add_to_cart` del valor
  mostrara algo más que el nombre. Ahora enseña `field_color` como círculo de 30px, y para estampados
  como Camuflaje un nuevo `field_imagen` en el valor de atributo, porque un color plano no sirve. El
  tema recorta la foto al tamaño del círculo y esconde el hex cuando hay foto.
- **Stock**: la migración dio 500 uds por variación con transacciones de `commerce_stock_local`. Un
  producto nuevo sin transacciones sale **"Agotado"** con el botón desactivado, porque
  `commerce_stock_enforcement` mira el nivel real y no basta con dejar `field_stock` vacío.
- **Pathauto estaba instalado sin ningún patrón** desde la fase 2, así que todo lo que creara el
  cliente salía como `/product/373`. Añadidos dos patrones con el esquema del D7:
  `productos/[commerce_product:title]` y `productos/[term:name]` para `tipo_de_producto`. Los 370
  alias migrados y los 30 de término no se tocan: pathauto solo genera para lo que no tiene alias.
- **La inicial NO se cobra nunca (2026-07-29, cliente)**: en los productos de modo `inicial` el
  bordado va incluido, es el reclamo con el que se venden ("Lisa o Personalizada"). La regla vive en
  `SurchargeCalculator::calculate()` con su prueba unitaria y manda sobre `field_recargo` y sobre el
  ajuste global, así que no hay que acordarse de poner 0 en cada producto. En modo `texto` se sigue
  cobrando el +5 €.
- **La inicial se elige de una rejilla A–Z**, no se teclea: es una sola letra mayúscula y así no hay
  forma de equivocarse. Cada letra se dibuja con los colores del formato elegido (relleno con `color`
  y contorno con `-webkit-text-stroke`), que es lo que permite **ver** la combinación sin una foto por
  letra y formato. Por eso cada término de `color_letra` tiene ahora dos colores, `field_color_perfil`
  (contorno) y `field_color` (relleno, con la etiqueta cambiada a "Color del interior"), y la muestra
  del chip es un círculo de dos tonos: con uno solo, "perfil negro interior blanco" y "todo blanco" se
  confundían. El alfabeto entero está en `FichaHooks::LETRAS`; si el taller no tiene parche de alguna
  letra, se quita de ahí.
- **El selector de color enseña la foto del producto en ese color y nada más**: con la foto, el punto
  de color y el nombre sobran a la vista, así que el nombre se queda solo para lectores de pantalla y
  para el carrito y el pedido. Los colores sin foto (el resto del catálogo) siguen mostrando el punto.
- **Lightbox de la galería en vez de lupa (2026-07-29)**: la referencia (Natura Selection) hace las
  dos cosas, lupa al pasar el ratón y modal al hacer clic, pero aquí manda el dato: la **mediana del
  catálogo son 945px de ancho** y solo el 36% llega a 1200, así que ampliar se vería peor. El
  lightbox sí aporta, porque la cuadrícula recorta a 3:4 y ahí se ve la foto **entera**. Estilo
  `pronens_lightbox` con `image_scale` (no `scale_and_crop`) y `upscale: FALSE`, tope 1400px: un
  original de 6480px sirve 1400 nítidos y uno de 679 sirve 679 sin emborronar. El diálogo usa
  `width: fit-content` para no dejar hueco alrededor de las fotos pequeñas. Se pasa con flechas,
  botones o arrastrando, y **sin JS cada foto es un enlace a su versión grande**.
- **Cuidado con el AJAX del add-to-cart**: al cambiar de talla o color, Commerce vuelve a renderizar
  **el formulario entero**, así que todo lo que el tema le añada tiene que ir con un `once()` puesto
  en algo que esté **dentro** del formulario. El enlace "¿Cómo queda?" se perdía porque su `once`
  estaba en el `<dialog>` de la guía, que vive en la plantilla del producto y no se reemplaza nunca.
  Y por lo mismo, **el precio no puede salir de `drupalSettings`**: se fija al renderizar la página y
  no cambia con la variación, de modo que se elegía la talla adulto y seguía diciendo 18,95 € cuando
  se iban a pagar 23,95 €. Ahora el precio de la variación elegida viaja en `data-pro-precio` del
  propio formulario, que es lo único que se rehace en cada refresco. Afectaba a los **152 productos
  migrados con precios distintos por variación**, no solo a la mochila.
- **El texto alternativo de las fotos migradas es el nombre del fichero** ("Foto Cupcake 1 -
  copia.jpg"). El lightbox suprime el pie de foto cuando detecta un nombre de fichero, pero el `alt`
  sigue siendo ese en más de mil medias: es una tarea de datos pendiente que afecta a accesibilidad y
  a SEO de imágenes.
- **La vista previa sigue anclada a `bottom: 44px`** como el prototipo, que estaba pensado para una
  foto de prenda plana. En la mochila el parche va más arriba, así que la letra cae por debajo de su
  sitio. El modelo tiene un `field_posicion_bordado` dormido que sería el lugar natural para
  resolverlo por producto o por foto.
- **Los 289 productos personalizables siguen en modo `texto`**: el selector de formato y su guía solo
  se ven en el de inicial. Para activarlos en otro producto basta cambiar "Modo de personalización" a
  *inicial* en su formulario de edición.
- **El nombre de los extras y de los formatos no está traducido** en CA/FR/EN porque son términos de
  taxonomía y los vocabularios no son traducibles: en este sitio **el contenido es solo español** (0
  traducciones de producto), así que es coherente con el resto. La interfaz sí está en los 4 idiomas.
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
- **Carrito flyout sin `commerce_cart_flyout` (2026-07-27)**: el módulo es compatible con D11 y está
  cubierto por seguridad, pero depende de **jQuery, Backbone y Underscore** (los dos últimos marcados
  como *internal* en core) más `commerce_cart_api`, y encima habría que rehacer el diseño dentro de
  sus vistas Backbone. En su lugar: el bloque de carrito de Commerce con `dropdown: true`, que ya es
  un `#lazy_builder` cache-safe, la view `commerce_cart_block` ampliada con el botón de quitar de
  Commerce (Views la envuelve en un formulario, así que **quitar funciona sin JS**) y unas 100 líneas
  de JS del tema para abrir/cerrar con foco atrapado y Escape.
- **El umbral de envío gratuito no está escrito a mano**: se lee de la condición `order_total_price`
  del método de envío id 7 ("Envío gratuito desde 60 €"), así que si el cliente lo cambia en
  `/admin/commerce/config/shipping-methods` la barra del flyout lo sigue. Se compara contra el
  **total** del pedido, que es lo que compara la condición, de modo que el recargo del bordado
  cuenta para llegar a los 60 €.
- **El total de línea de Commerce no incluye los ajustes**, así que el flyout enseña el recargo del
  bordado aparte ("24,81 € + 5,00 €"): sin eso las líneas no sumaban el subtotal del pie.
- **El panel del carrito se mueve al `body` por JS**: el bloque vive en el header sticky, que crea
  contexto de apilamiento, y ahí el overlay se comía los clics del panel. Es el mismo problema que
  ya apareció con el off-canvas del menú.

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
