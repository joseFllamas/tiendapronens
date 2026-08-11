# CLAUDE.md — Rediseño tienda Pronens (Drupal 11 + Commerce 3)

Eres el desarrollador front/back de este proyecto Drupal. Tu fuente de verdad visual es
`design/Tienda Pronens.dc.html` (ábrelo en un navegador; la barra superior cambia entre Home,
Categoría y Ficha). El detalle completo de pantallas, tokens y comportamiento está en
`design/README.md`. **Recrea el diseño en el tema — no copies el HTML del prototipo.**

## Contexto del repo
- Drupal 11 (`web/` como docroot), Commerce 3 + PayPal + Sermepa + Shipping + Stock ya en composer.
- No hay tema custom todavía: créalo en `web/themes/custom/pronens` (starterkit de core).
- Idiomas: ES (por defecto), CA, FR, EN e IT — módulos multilingües de core. Los 366 productos
  están traducidos a los cinco (agosto de 2026).
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
  relleno) y **numeradas 1–6 igual que en la foto**. Los 8 que no se
  ofrecen (5 del D7 y los 3 del prototipo) se despublicaron primero y se **borraron** el 2026-07-29 a
  petición del cliente, porque ensuciaban la lista del vocabulario en la administración: se verificó
  antes que no tenían ni una referencia en contenido y que ninguna línea de pedido los usaba. Las filas
  de `migrate_map_pronens_taxonomia_color_letra` se dejaron a propósito: con la fila en el mapa y el
  término borrado, un re-import **no los recrea**. Copia previa: snapshot `pre-borrado-formatos`.
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
- **La letra va en Graduate (2026-07-30, cliente)**, la collegiate de Google Fonts, self-hosted como
  las demás (`fonts/graduate-v19-latin-regular.woff2`, 6 KB, subset latin) con su propia library
  `pronens/graduate`, igual que Caveat. Es la tipografía del bordado real: las fotos de tienda llevan
  una letra varsity con contorno y Archivo con `text-stroke` solo la imitaba de lejos. Vale para
  **todos** los productos de modo inicial, presentes y futuros: es el token `--pro-font-letra`, que usan
  la rejilla A–Z, la vista previa de la ficha y la marca arrastrable del backoffice (por eso
  `MontajeHooks` adjunta la library del tema: si el widget usara otra tipografía, la marca que se
  arrastra tendría otro ancho que el bordado que se acaba viendo). **Un solo peso, el 400**: Graduate no
  tiene bold y el sintético emborrona el contorno, así que los tres sitios lo declaran explícitamente.
  Las dos tipografías del bordado son excluyentes y se cargan por modo: `pronens/graduate` en modo
  inicial y `pronens/caveat` (la cursiva del nombre) en modo texto, no las dos siempre.
- **El formato viene elegido de entrada (2026-07-30, cliente)**: el nº 1 de la foto guía, que hoy es
  "perfil negro interior blanco". No está escrito a mano: es el **primero por peso** del vocabulario
  `color_letra`, así que para cambiar el que sale marcado basta reordenarlo en
  `/admin/structure/taxonomy/manage/color_letra/overview`. La intención ya estaba en el tema, pero no
  funcionaba: `options_buttons` en un campo de **un solo valor** espera el tid suelto en
  `#default_value` y se le pasaba dentro de un array, así que no marcaba ningún radio y se podía pedir
  una inicial sin formato (el campo no es obligatorio). Con la casilla ya marcada, además, la rejilla
  A–Z sale pintada con los colores del formato desde el primer render, sin tocar nada.
- **La casilla "Bordar su inicial" viene marcada (2026-07-30, cliente)**: la inicial es el reclamo con
  el que se vende el producto y no cuesta nada, así que obligar a activarla no tenía sentido; quien
  quiera la prenda lisa la desmarca. Consecuencia: como ya no es un acto deliberado, sin letra elegida
  el pedido saldría liso sin que nadie lo haya decidido, así que `validarPersonalizacion()` **pide la
  letra en servidor** ("Elige la inicial que quieres bordar, o desmarca…") cuando la casilla sigue
  marcada. En modo `texto` la casilla sigue **apagada** por defecto, porque marcarla cuesta 5 € y eso no
  se activa por nosotros.
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
- **Colocación del montaje en proporciones, no en píxeles (2026-07-29)**: `field_inicial_x`,
  `field_inicial_y` y `field_inicial_tamano` en el producto, en **porcentaje** de la foto, porque la
  misma foto se sirve en varios estilos de imagen y a varios anchos de pantalla: un 37% vale igual en
  la miniatura de 740px, en el lightbox de 1400 y en móvil, mientras que "128px desde la izquierda"
  solo valdría para un tamaño. Se descartó **focal_point**, que resuelve una interacción parecida y es
  compatible con D11: guarda el punto como entidad `Crop` **atada al fichero**
  (`Crop::findCrop($file->getFileUri(), …)`), arrastra el módulo `crop` y **no guarda tamaño**. Aquí la
  decisión es del producto y el parche necesita tamaño. De focal_point se toma la idea de marcar sobre
  la propia foto: `MontajeHooks` añade al formulario del producto un lienzo con la letra arrastrable y
  una barra de tamaño que rellenan los tres números, que siguen visibles y editables a mano.
- **La foto del montaje es siempre `field_imagen_principal`**, y no cambia al elegir color (decisión
  del cliente, 2026-07-29). Es la foto **sin letra** sobre la que se mide la posición en el
  backoffice, así que tiene que ser la misma que se pinta en la tienda: si cambiara con la variación,
  la posición medida sobre una foto se aplicaría sobre otra con distinto encuadre. El widget del
  backoffice mide sobre esa misma foto, de modo que lo que se coloca es exactamente lo que se ve.
  Las fotos de cada color siguen usándose en la muestra del selector y en la línea del carrito.
- El `field_posicion_bordado` del modelo (`lateral` / `centro`) **no sirve para esto**: es una lista de
  dos valores en la línea de pedido, no una coordenada.
- **Los 281 productos personalizables que quedan siguen en modo `texto`**: el selector de formato y su
  guía solo se ven en el de inicial. Para activarlos en otro producto basta cambiar "Modo de
  personalización" a *inicial* en su formulario de edición.
- **Las 8 sudaderas "con iniciales" (232-239) están en modo `inicial` (2026-07-30)**, las primeras del
  catálogo migrado, y con ellas **la inicial ya no se cobra** (30,25 € con el bordado incluido).
  Lo que hubo que arreglar de los datos del D7, y que probablemente afecte a la siguiente tanda:
  - **La foto principal de las 8 ya llevaba una letra bordada** (una R, una M, una C…), así que la vista
    previa habría pintado una segunda letra encima. Ahora la principal es la foto de la prenda **lisa
    de frente** del catálogo JHK, que ya estaba en la galería de cada una (medias 1179, 1157, 1170,
    1166, 1161, 1175, 1163 y 1200), y la de modelo pasó a primera de galería. Cada color traía tres
    fotos de catálogo (frente, espalda y lateral) y hay que coger la del **frente**: se distingue por
    el hueco del cuello. La marino es la excepción, su única foto lisa es de **597×750** y la ficha
    sirve a 800×1066, así que se ve blanda: **pedir al taller la foto buena**.
  - **Colocación 64,33 / 32,70 / 17**, la calibración que el cliente hizo a mano sobre la rosa. Las 8
    fotos comparten encuadre, así que vale para todas; se afina por producto arrastrando la letra.
  - **Tallas**: solo la negra y la marino tenían la serie de adulto casi completa. Se han creado las que
    faltaban hasta las seis (16/XS, 18/S, 20/M, 22/L, 24/XL, 26/XXL) clonando la variación existente,
    con **400 uds de stock** cada una: sin transacción de stock una variación nueva sale "Agotado".
    Las **tallas de niño de la celeste, la negra y la marino se han dejado**, así que esas tres ofrecen
    niño y adulto. La roja tenía una variación **sin talla** (imposible de elegir en la tienda) y se le
    puso la 16/XS, que es lo que ya decía su SKU. SKUs nuevos = prefijo del producto + código de talla.
  - **El precio de la lila era 388,41 €** (error de datos del D7, ya documentado): corregido a 30,25 €
    en su talla existente y en las cinco nuevas.
  - **Los alias de la blanca y la rosa están cruzados**: `/…/sudadera-blanca-con-iniciales` sirve el 236,
    que es la **rosa**, y `/…/sudadera-rosa-con-iniciales` el 232, la blanca. Igual que el SKU
    `SIROSA-XS`, que está en la **blanca**. Son datos del D7 y no se han tocado (los alias tienen coste
    de SEO). Ojo con esto al revisar fichas por URL.
  - **Las descripciones migradas contradicen la ficha**: dicen "Opción 2 iniciales… seguidas, sin punto"
    y el modo inicial admite **una sola letra**. O se corrige el copy o se decide si la rejilla admite
    dos. Copia previa de todo esto: snapshot `pre-sudaderas-inicial`.
- **El nombre de los extras y de los formatos no está traducido** en CA/FR/EN porque son términos de
  taxonomía y los vocabularios no son traducibles. La interfaz sí está en los idiomas del sitio.
- **Formato de los títulos de producto (2026-08-11)**: el catálogo migrado mezclaba tres estilos
  (100 títulos gritados en MAYÚSCULAS, una veintena en Title Case y el resto bien). La regla ya
  dominante en ES/CA/FR/IT era **frase + nombre del motivo en mayúscula** ("Bata babi escolar
  Cupcake"), así que es la que se ha aplicado a los 5 idiomas menos el inglés, que se queda en
  **Title Case** por ser su convención y su estilo mayoritario (decisión del cliente). Se
  normalizaron **523 títulos** y se regeneraron los de las variaciones, que llevaban el título del
  producto dentro (`generateTitle: true` en el tipo `default`) y salen en el carrito y en los
  correos. Detalles que conviene no reinventar:
  - El léxico de qué palabra es genérica (prenda, color, talla, material) y cómo se acentúa se
    **dedujo de los títulos que ya estaban bien escritos**, no de una lista a mano; solo se
    añadieron los términos que nunca aparecieron fuera de las mayúsculas. Al pasar a minúsculas se
    recuperaron las tildes que faltaban en el D7 (COJIN → Cojín, BUHO → Búho, CAPITAN → Capitán).
  - **Los colores van en minúscula** aunque sean lo único que distingue al producto ("Mascarilla
    higiénica rosa"), y los nombres de estampado en mayúscula ("Mascarilla higiénica Aguacates").
  - `GOAR` y `POP Culture` son las **únicas** mayúsculas legítimas del catálogo: cualquier otra
    palabra en caja alta era un grito.
  - **Las URLs no se movieron**: los 366 productos tienen `pathauto_state` a 0 salvo 15, y ninguno
    de esos 15 estaba en la lista, así que pathauto no regeneró ningún alias (2038 antes y después).
  - Copia previa: snapshot `pre-normalizar-titulos`.
- **Repaso de las traducciones de título (2026-08-11)**: la pasada de traducción por IA dejó restos
  de castellano en 89 productos. Se corrigieron **166 títulos** por sustitución quirúrgica, tomando
  el término de destino del idioma hermano que ya lo tenía bien en ese mismo producto, no de una
  elección propia. Lo que hay que saber para la próxima tanda:
  - **La categoría manda sobre el título**. Los productos 15, 23, 24, 25 y 31 son de "Batas
    guardería" y la traducción los convirtió en baberos (*bavoir*, *pitet*, *bavaglino*, *bib*) y
    uno en body. No se detecta leyendo el título: hay que cruzar con `field_tipo_de_producto`.
  - **Los motivos sí se traducen**, que es lo que ya hacían inglés, francés e italiano (Owl Bib,
    Bavoir Hibou, Bavaglino Gufo). El catalán lo hacía a medias y se ha completado.
  - **Un título traía un token del modelo**: el producto 67 en francés era `Short<|endoftext|>`, y
    el 332 en italiano acababa en salto de línea. Barrido hecho sobre títulos, `body` y
    `field_composicion` en los 5 idiomas: no hay más.
  - **Sin decidir a propósito**, porque son ambiguos y los deja el cliente: `Carpas` (144 y 211,
    ¿carpas de circo o peces?), `Helada` (281, el catalán lo tradujo y los demás no), `Blanca` (110,
    ¿el personaje de Street Fighter, Blancanieves o el color?) y `Márfega` (261, 262, 267), que se
    trata como nombre de gama y no se traduce en ningún idioma.
  - Copia previa: snapshot `pre-traducciones-titulos`.
- **Lo que el tema carga por su cuenta hay que traducirlo a mano (2026-08-11)**: una entidad
  cargada por id llega en su idioma por defecto, que aquí es el castellano, así que `->label()` a
  secas devolvía "Batas guardería" también en la ficha francesa. Vale para términos de taxonomía,
  valores de atributo y el producto de una línea de pedido; lo que renderiza el view builder (el
  título del producto, los campos del display) ya viene traducido. El helper está en
  `TraduccionTrait` (`traducido()` / `etiqueta()`) y lo usan las cinco clases de hooks del tema.
- **La etiqueta del selector de variación la fuerza Commerce al idioma de la variación**:
  `ProductVariationAttributeMapper::prepareAttributes()` llama a `getTranslationFromContext()`
  pasándole `$selected_variation->language()`, y aquí las 1123 variaciones son solo `es`, así que
  el selector decía "Talla" en los cinco idiomas. Dos cosas: la etiqueta sale del **atributo**
  (`commerce_product.commerce_product_attribute.talla`), no del campo de la variación, que es lo
  que estaba traducido y solo se ve en el backoffice; y aunque se traduzca el atributo, Commerce
  la seguiría pidiendo en `es`. `FichaHooks::traduceEtiquetasDeAtributo()` rehace el `#title`
  leyendo la config, que ya llega con el override del idioma activo.
- **Los valores de atributo siguen solo en castellano**: las 78 opciones (`0 (0-1 años)`,
  `40 x 40 cm`…) son entidades `commerce_product_attribute_value` traducibles pero sin traducir, así
  que las pastillas de talla salen en castellano en los cinco idiomas. Es tarea de datos.
- **Las views tienen que filtrar por idioma**: `commerce_product_field_data` tiene una fila por
  idioma y el índice de Search API indexa los cinco, así que sin filtro cada producto salía **cinco
  veces** (74 productos en una categoría daban "370 productos" y 24 tarjetas eran 5 productos
  repetidos). Añadido `search_api_language` en `catalogo` y `langcode` en `productos_destacados`,
  las dos con `***LANGUAGE_language_content***`, que Views sustituye por el idioma de la página: no
  hacen falta displays por idioma. Cualquier view nueva que liste productos necesita lo mismo.
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
- **Comisión por medio de pago (2026-07-30)**: módulo `pronens_comision_pago`, un porcentaje del
  **total** del pedido cuando se paga con PayPal, configurable en
  `/admin/commerce/config/comision-pago` (1,5% y solo `paypal` por defecto). Avisos que hay que
  tener presentes antes de ponerlo en producción: las condiciones de uso de PayPal **prohíben el
  recargo** salvo que la ley lo permita, y el artículo 60 ter del texto refundido de consumidores
  impide cobrar más de lo que cuesta el medio de pago, así que el porcentaje tiene que ser el real
  de la pasarela. Con **tarjeta (Redsys) no se puede** cobrar recargo, lo prohíbe la PSD2. La
  alternativa limpia, si el cliente la acepta, es el descuento por transferencia: mismo efecto en
  caja sin ninguno de los dos problemas.
  - **Prioridad -200**, el último de la cola, detrás del envío tardío (-100): la base tiene que ser
    lo que la pasarela va a cobrar de verdad, con bordado, extras, cupón, IVA y envío dentro. Como
    entra después del IVA, la comisión **no lleva el impuesto desglosado**; si la gestoría dice que
    debe llevarlo, se sube a 60 y hay que sumar el envío a la base a mano.
  - **El procesador solo no basta**: Commerce **bloquea el pedido** al saltar al paso de pago
    (`CheckoutFlowBase::onStepChange`) y un pedido bloqueado ya no se refresca, así que el ajuste
    nunca llegaría a entrar. `ComisionHooks::refrescarTrasElegirPago` se encola en el botón de
    continuar y llama a `order_refresh->refresh()`, que **no mira el bloqueo**, al revés que
    `shouldRefresh()`. Sin eso el recargo no aparece en ningún pedido.
  - **El importe se anuncia en el propio radio** ("PayPal (+1,5% de comisión: 0,58 €)") porque el
    resumen lateral no se recalcula hasta continuar y el paso de pago no lo enseña: el cliente tiene
    que ver el sobrecoste cuando decide. Se etiqueta recorriendo `#payment_options`, no las claves
    de `#options`: la opción de PayPal se llama `new--paypal_checkout--paypal`, no `paypal`.
  - `commerce_paypal` ya manda los ajustes de tipo `fee` a la API como una línea más y los suma al
    `item_total` (`SdkBase::prepareOrderRequest`), así que el desglose cuadra y PayPal no rechaza la
    orden. Si algún día se activa `enable_on_cart`, hay que revisarlo: el botón exprés del carrito
    fija la pasarela fuera del panel de pago y se salta este camino.
- **PayPal no se podía elegir en la compra (2026-07-30)**: su pasarela tenía guardado
  `payment_method_types: [credit_card]`, pero el plugin `paypal_checkout` solo admite el tipo
  `paypal_checkout`, así que `getPaymentMethodTypes()` devolvía vacío y Commerce no pintaba la
  opción. Corregido en la config de la pasarela. **Las tres pasarelas siguen en modo test**: Redsys
  tiene las credenciales reales del D7 (PRONENS, comercio 329583926, terminal 001), PayPal está sin
  `client_id` ni `secret`, y la transferencia está desactivada y sin IBAN.

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
