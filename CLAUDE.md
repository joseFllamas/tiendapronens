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
- **Los alias ya existen en los 5 idiomas** (2026-08-12, comprobado en `path_alias`): 366 es y 364
  por idioma en ca/en/fr/it para productos, 44 es y 41 por idioma para términos. La nota anterior
  ("los 433 alias del D7 son solo `es`, así que `/ca/productos/<slug>` da 404 y los menús generan
  `/ca/taxonomy/term/<tid>`") está **obsoleta**: el menú catalán ya sale con alias bonitos
  (0 enlaces a `taxonomy/term` en la home ca) y no hace falta pasar nada a `und`.
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
  Las tipografías del bordado son excluyentes y se carga **una por producto**: en modo inicial siempre
  `pronens/graduate`, y en modo nombre la que diga `field_bordado_fuente` (ver la resolución siguiente).
- **El nombre bordado tiene fuente, color y caja por producto (2026-08-13, cliente)**: en modo `texto`
  el nombre se pintaba siempre en la cursiva Caveat y en el gris del texto, y eso no es lo que sale del
  taller (la bolsa de referencia lleva "MÓNICA" en mayúsculas y en rosa). Son tres campos del producto
  —`field_bordado_fuente`, `field_bordado_color` y `field_bordado_mayusculas`— y viven en el mismo
  `details` que la colocación, porque son la misma decisión: cómo va el bordado en esa prenda. Los pone
  `MontajeHooks`, que **oculta los tres en modo inicial** (ahí la letra va en Graduate y los colores
  salen del formato que elige el cliente). Lo que conviene no reinventar:
  - **Son del backoffice, no del cliente**: es una característica de la prenda, no una elección de
    quien compra, así que no resucitan el vocabulario `fuente_bordado` ni `field_fuentes_permitidas`,
    que eran un selector para la tienda y siguen dormidos. Por eso la fuente es una **lista cerrada de
    tres**: cada opción necesita su WOFF2 en el tema, igual que los 9 iconos de las landings.
  - **La unicase es la de por defecto** (cliente): `Delius Unicase` 700, self-hosted (10 KB, subset
    latin, library `pronens/delius`, token `--pro-font-unicase`). Unicase quiere decir que la caja alta
    y la baja miden lo mismo, que es como borda el taller ("Alex" sale ALeX); es la **única unicase con
    dibujo redondeado de Google Fonts** (la otra, Cormorant Unicase, es serif). Cubre las tildes y la
    eñe de los nombres del catálogo, comprobado con Mónica / Anaïs / Iñaki. Los **279 productos de
    nombre migrados no traen valor**, así que se les aplica esta; poniéndoles "Cursiva" vuelven a la
    Caveat de antes, producto a producto.
  - **Las mayúsculas se aplican en servidor**, en `validarPersonalizacion()`, y no solo en la vista
    previa: lo que se guarda en la línea de pedido es lo que va a leer el taller, así que el pedido, el
    correo y el albarán dicen MÓNICA. `mb_strtoupper` respeta la tilde (mónica → MÓNICA). El campo de
    la ficha además se teclea ya en caja alta (`text-transform` + `autocapitalize`).
  - **El "tamaño" cambia de significado con el modo**, y por eso el widget cambia de etiqueta y de
    rango: en inicial es el **lado del parche** cuadrado (2–60%, defecto 12) y en nombre la **altura de
    la letra** (1–20%, defecto 5), con el ancho a lo que salga, como en el bordado real. Los dos en %
    del ancho de la foto. Solo 2 de los 279 productos de nombre tenían valor, así que el cambio de
    defecto no descoloca nada ya calibrado.
  - **La vista previa ya no lleva 44px a pelo**: escala con la configuración (`cqw` sobre la foto), así
    que el nombre se ve del tamaño que se va a bordar en cualquier ancho de pantalla y desaparece la
    excepción de móvil.
  - **Ojo con `container-type` y los anchos intrínsecos**: el lienzo del backoffice era un
    `inline-block` y al declararlo contenedor su ancho se calculó **sin mirar la foto**, o sea 0, y la
    marca se quedaba en nada (`font-size: 0px`). Con `display:block` y ancho declarado, resuelto. En la
    ficha no pasa: la foto es celda de una cuadrícula y su ancho ya viene de fuera.
  - **Carta de 30 hilos** (2026-08-13, cliente), en cinco filas por familia y de claro a oscuro:
    neutros, rosas y rojos, naranjas y amarillos, verdes, y azules y violetas. Los 10 anteriores están
    dentro, así que ampliarla no deselecciona nada ya configurado, y el widget de color_field sigue
    dejando elegir cualquier otro a mano. Vive en los **settings del widget** del form display, no en
    el campo, así que se amplía en `scripts/bordado-nombre.php` y no en la administración.
- **La rotación del bordado va en grados y sirve para los dos modos (2026-08-13)**:
  `field_bordado_rotacion`, decimal 5,2, en el mismo grupo que la posición, porque es colocación y no
  una decisión sobre la letra: un parche de inicial también se puede inclinar. **No va en porcentaje**
  como la posición y el tamaño (el cliente lo pidió así) porque un porcentaje necesita algo contra lo
  que medirse, el ancho de la foto en ese caso, y una rotación no lo tiene; los grados además se
  entienden sin explicación (90 es un cuarto de vuelta). Tiene su propia barra, **la única del montaje
  que no se puede arrastrar sobre la foto**, y se compone con el centrado en la misma `transform`
  (`translate(-50%,-50%) rotate(...)`), tanto en la marca del widget como en la vista previa: una
  `transform` en línea sustituye a la de la hoja de estilos entera, así que el centrado tiene que
  repetirse ahí y no basta con añadir el giro.
- **Los 41 bodys de bebé llevan el montaje del esmoquin (2026-08-13)**: el cliente calibró el body
  de esmoquin (76: unicase, sin mayúsculas, 50,36 / 30,30, 2,5 % de altura) y
  `scripts/bordado-bodys.php` lo replica en los otros 40 de la categoría (177), que comparten
  encuadre de catálogo JHK. El **hilo se decidió por producto** midiendo con ImageMagick la tela
  bajo el nombre y el estampado: blanco sobre prenda oscura (como la referencia), el color del
  print en las claras estampadas (negro, grafito en el iPood, frambuesa en los magenta, verde en
  Young wild) y el complementario de la carta de 30 en los lisos (verde agua sobre rosa, violeta
  sobre amarillo, ámbar sobre marino y azulón, marino sobre naranja; blanco donde el complementario
  no contrasta: fucsia, rojo, negro). Dos excepciones de colocación medidas sobre la foto: el
  **iPood (55)** lleva el rótulo en el pecho y el nombre baja a la barriga (y=66), y el
  **Perezoso (56)** tiene la cabeza del oso ahí y el nombre sube encima del dibujo (y=24). El
  script **solo escribe donde está vacío**, así que el 76 no se tocó y relanzarlo no pisa ajustes.
  Copia previa: snapshot `pre-bordado-bodys`.
- **El nombre se borda dentro de una nube en las mochilas y las bolsas (2026-08-13, cliente)**: no va
  sobre la tela, va dentro de una forma de color que hoy viene en dos tonos (marrón y rosa) y mañana
  en los que haga falta. Por eso es un **vocabulario, `fondos_bordado`**, y no una lista cerrada como
  `field_bordado_fuente`: añadir un color o una silueta es crear un término con su foto, sin tocar
  código. El modelo es el de los extras (vocabulario + qué ofrece cada producto + qué eligió cada
  línea): `field_fondos_disponibles` con casillas en el producto, `field_fondo_bordado` en la línea de
  pedido, y todo **compartido entre traducciones**, que aquí no hay nada que redactar. **El fondo no
  cuesta nada**: no tiene procesador de pedido, al revés que los extras. Lo que conviene no reinventar:
  - **Se coloca UNA vez, no dos**: el nombre va centrado dentro de la nube y viaja con ella, así que la
    posición (`field_inicial_x` / `_y`) y la rotación son las de siempre y solo hace falta un número
    nuevo, `field_fondo_tamano`, el **ancho de la nube en % del ancho de la foto**. El alto sale de la
    proporción de la propia foto del fondo, así que una nube más apaisada encaja sin tocar nada.
  - **La caja de texto la declara el término** (`field_caja_ancho` / `field_caja_alto`, en % del
    fondo, **50 y 34** por defecto): una nube no es un rectángulo y el nombre tiene que quedarse dentro
    de la panza. Es lo que permite que un fondo con otra silueta encaje sin CSS nuevo. El 50 está
    **medido sobre el alfa de la nube**, no puesto a ojo: la forma es **asimétrica** (el lóbulo de
    arriba a la derecha se queda en el 79 % del ancho mientras la panza llega al 99 %), así que una
    caja centrada no puede pasar del 58 % sin salirse por ahí; el resto, hasta el 50, es aire. Empezó
    en 66 y el nombre rozaba el contorno (cliente, 2026-08-13).
  - **Los nombres largos encogen solos**, que es lo que hace el taller: se mide el texto con
    `offsetWidth` (no `getBoundingClientRect()`, que vendría girado por la rotación del montaje) y se
    escribe un factor en `--pro-bordado-encoge`. Ojo: el factor va **fuera** del `max(10px, …)` del
    tamaño de letra; metido dentro, el suelo lo anulaba y el nombre seguía saliéndose de la nube.
  - **El hilo lo puede decidir el fondo**: `field_color` en el término manda sobre el
    `field_bordado_color` del producto, porque sobre la nube marrón el nombre va en blanco y sobre la
    tela iría en rosa. Se declara el último en el atributo `style`, que es lo que hace que gane.
  - **Solo en modo `texto`**: una inicial es un parche sobre la tela, no un nombre dentro de una nube,
    así que en modo inicial no se pregunta ni en la ficha ni en el backoffice.
  - **Las fotos que llegaron NO tenían transparencia**: eran PNG de 3 canales con el **damero gris
    pintado dentro**, así que puestas sobre la prenda se habrían visto con su recuadro de cuadros. Las
    de `fondos/` son una reconstrucción hecha aquí (máscara por saturación + umbral de luminosidad,
    ImageMagick) y sirven para verlo funcionando: **hay que pedirle al taller el PNG bueno o el SVG**.
  - **Puestas en 91 productos** de Mochilas (179) y Bolsas guardería (182) con
    `scripts/fondos-bordado-asignar.php`, que **solo escribe donde está vacío**, así que no pisa nada
    calibrado a mano. La colocación de partida (77 / 88 / 26) sale de la foto de tienda y **acierta
    donde la bolsa llena el encuadre**; en las fotos de objeto pequeño sobre blanco la nube cae fuera
    de la prenda y hay que moverla producto a producto.
  - **La colocación de partida quedó sustituida por la de la Caperucita (2026-08-13, cliente)**: el
    cliente calibró la bolsa 132 (69,48 / 70,59, letra 5,5 %, nube 26 %) y
    `scripts/bordado-bolsas.php` lo copió a las **85 bolsas** de las tres familias (término 182
    entero más las 13 "Bolsa mochila…" de Mochilas, verificado sobre las 87 fotos que la nube cae
    dentro: aquí la lámina llena el encuadre). Al revés que los otros scripts de bordado, este
    **pisa, pero solo lo que siga exactamente en la partida** (77 / 88 / 4 / 26): cualquier otro
    valor cuenta como calibración manual y se respeta entera (la 132 y la bolsa Mamá 304, afinada a
    mano con 70,57 / 71,99 / 3,5 / 29,5). Pendiente de datos: las fotos de las 13 "Bolsa mochila"
    (218–230) traen un **nombre de ejemplo impreso en la lámina** ("Lucas", "ERIC", "HUGO"…), así
    que la vista previa pinta un segundo nombre; la solución es foto sin nombre, como en las
    sudaderas de inicial. Copia previa: snapshot `pre-bordado-bolsas`.
  - **Los baberos también llevan nube (2026-08-13, cliente)**: el cliente calibró el Baby Shark
    (162: nube con fondos 225+226, nombre en 64,27 / 76,72, unicase, hilo blanco, y **tamaño de
    letra y ancho de nube vacíos a propósito**, que caen a los defectos 5 % / 34 %) y
    `scripts/bordado-baberos.php` lo copió a los otros **45 baberos** del término 180, packs de
    rizo incluidos (su foto es un solo babero y el punto cae dentro). Verificado sobre las 47
    fotos. Este vuelve a la regla de **solo escribir donde está vacío** (los baberos no pasaron
    por el script de fondos): cualquier `field_inicial_x/_y` relleno cuenta como calibración
    manual y el producto se salta entero (el 162 y el Lorito 338, afinado a mano con
    64,61 / 47,52 y letra 3, sin nube). Copia previa: snapshot `pre-bordado-baberos`.
- **El nombre sin nube también tiene tope (2026-08-13, cliente)**: la zona de bordado de una prenda
  no es infinita, así que el encogido de los nombres largos deja de ser exclusivo del fondo. La
  regla del cliente era "elijo el tamaño con Mónica y que quepan 8 letras como máximo", y se expresa
  sin números en píxeles: la caja del nombre mide **8,6ch** (`--pro-nombre-max`), o sea 8 letras
  típicas en la fuente y tamaño configurados ("Fernanda" son 8,33ch en Delius y cabe entera;
  "Valentina" ya roza). `ch` y no una medida fija porque el límite **escala con el tamaño y con la
  fuente del producto**; en mayúsculas los nombres ocupan más y encogen antes, que es lo que pasa en
  la prenda de verdad. Tres detalles que costaron encontrar y conviene no reintroducir:
  - **El factor de encogido va en el TEXTO, no en el elemento**: la caja se mide en ch del tamaño
    configurado, y si encogiera con el texto el límite se movería con cada medida (bucle).
  - **La fuente del bordado se declara en el ELEMENTO** (`.pro-ficha__preview--unicase` y hermanas,
    antes en el `-text`): el ch de la caja depende de la fuente, y con la fuente solo en el texto la
    caja medía en Archivo, más estrecha que Delius, y el límite salía más duro de lo configurado.
    Mismo arreglo en el widget del backoffice (la fuente en la marca, el `b` hereda).
  - **El select de fuente de los 279 productos migrados vale `_none`**, no vacío: `viste()` en
    montaje.js apagaba las tres clases de fuente y la marca del backoffice caía a la tipografía del
    administrador, con otro ancho que el bordado real. `_none` = unicase, la de defecto de la ficha.
    El bug era anterior (solo desviaba el ancho de la muestra); con la caja de 8,6ch pasó a mover el
    límite y por eso se encontró.
  - La altura solo limita **dentro de la nube**: sin fondo la caja mide lo que mida el texto y el
    tope sería circular. Y la marca del backoffice enseña la caja punteada de 8,6ch aunque "Mónica"
    no la llene: al colocar se ve la huella máxima que puede ocupar un nombre largo.
- **La foto del bordado se puede elegir (2026-08-13, cliente)**: `field_bordado_foto` en el
  producto (referencia a media, opcional, compartida entre traducciones), para cuando el bordado va
  en una cara que la foto principal no enseña (un body con el dibujo delante y el nombre en la
  espalda: la vista previa pintaba el nombre encima del dibujo). Revisa la resolución "la foto del
  montaje es siempre `field_imagen_principal`" sin romper su invariante: sigue habiendo **una sola
  foto de referencia para medir y para pintar**, solo que ahora se puede decir cuál
  (`MontajeHooks::fotoDeMontaje()` y `FichaHooks::fotos()` hacen la misma elección: bordado_foto →
  principal). Lo demás que se decidió con el cliente:
  - **La foto del bordado NO pasa a ser la primera de la galería**: la primera vende el producto (el
    dibujo) y una espalda lisa no. La vista previa **se ancla a la foto esté donde esté** en la
    cuadrícula (`foto.bordado` en la plantilla, una sola lo lleva; si no está en la galería o quedó
    fuera del corte de seis, se añade al final). Se descartó también el intercambio en vivo de la
    primera foto al activar el bordado (más JS, frágil con el AJAX del add-to-cart).
  - **La señal es un resplandor turquesa + traerla a la vista**: al activarse el bordado de verdad
    (casilla + texto, no solo la casilla), la foto recibe `pro-ficha__shot--brilla` y, si está fuera
    de pantalla, `scrollIntoView` **solo en el flanco de apagado a encendido**: desplazarse en cada
    tecla pelearía con el usuario por el scroll. Respeta `prefers-reduced-motion` (scroll sin
    animar y sin transición del glow). Se descartó el sombreado oscuro: oscurece justo lo que se
    quiere mirar.
  - **El contenedor de medida viaja con la vista previa**: `container-type` vive en
    `pro-ficha__shot--preview` (la foto del bordado), ya no en `--main`. Sin él, los `cqw` de la
    altura de la letra medirían contra otra caja.
  - En el backoffice el campo entra el **primero** del grupo de montaje: todo lo demás se mide sobre
    esa foto. Cambiarla pide guardar y volver, igual que ya pasaba con la principal.
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
- **Y ahora también lupa, dentro del lightbox (2026-08-13, cliente)**: acercar la foto y recorrerla
  con el cursor, al estilo de Amazon. Revisa en parte la resolución anterior, pero no la contradice:
  la lupa **solo se ofrece donde hay píxeles que enseñar** y el acercamiento no es un número fijo,
  sale de dividir los píxeles reales de la foto entre los que ocupa en pantalla. Medido sobre las
  366 fichas: **363 dan lupa** (354 entre 2x y 3x) y **3 no** (fotos apaisadas anchas, que en
  escritorio ya se ven a tamaño real). Lo que conviene no reinventar:
  - **El "no se vería bien" de julio venía de un dato falso**: 893 de las 1165 medias guardan en
    `field_media_image` el ancho y alto del original de **antes del dedupe** de la migración
    (`tote-1v1.jpg` dice 860x842 y mide 1200x1600), así que la mediana de 945px del catálogo no es
    real. `datosDeEstilo()` lee las medidas **del fichero** con `image.factory` y no del campo, en
    una sola lectura para los dos estilos. **Queda pendiente** corregir ese metadato en los 893
    medias: afecta a los `width`/`height` de todas las imágenes del sitio, no solo a la lupa.
    `scripts/refrescar-dimensiones.php` hace justo eso, pero solo para `public://2026-08/`.
  - **Estilo `pronens_zoom`** (2600 máx, `image_scale` sin ampliar, WebP) para el detalle, y se pide
    **solo al acercar** y solo si el original pasa de los 1400 del lightbox: son unos cientos de KB
    que no hacen falta para ver la foto entera. Como los dos estilos escalan sin recortar, el tamaño
    en pantalla no cambia al llegar el detalle y no se mueve nada.
  - **La escala va en una clase CSS y el punto de origen en línea**: así la transición suaviza el
    acercar y el alejar sin arrastrar el recorrido, que tiene que ir pegado al cursor. Ojo con
    **`getBoundingClientRect()`, que ya viene multiplicado por la escala**: recalibrar con él al
    llegar la foto de detalle daba factor 1 y apagaba la lupa sola. Se mide con `offsetWidth`.
  - **Con el dedo, un toque acerca y otro aleja**, y con la foto acercada el arrastre la recorre en
    vez de pasar de foto. Un arrastre se descarta como toque **también con la foto entera** (umbral
    de 10px desde el `pointerdown`): en táctil el `pointerup` llega antes que el `touchend` del
    gesto de pasar de foto, así que sin eso un swipe cambiaba de foto **y** la dejaba acercada.
  - Las dos cadenas del aviso ("Pasa el ratón…" / "Toca la foto…") se traducen en
    `scripts/traducir-lupa.php`.
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
  **Y lo mismo con `->toUrl()`**: la URL sale en el idioma DE LA ENTIDAD, no en el de la página
  (la entidad `es` fuerza el prefijo `/`), así que un complementario del flyout en la ficha
  francesa llevaba a la ficha española. Siempre `$this->traducido($entidad)->toUrl()`. Corregido
  el 2026-08-11 en los cuatro sitios que generaban URL desde entidad cargada a mano: sugerencias
  del flyout, líneas del carrito, tarjetas de "También te puede gustar" y teselas de la home. Ojo:
  `Url::fromRoute()` sin entidad no tiene este problema. Y el view builder tampoco salva aquí:
  `view($producto, 'tarjeta')` deja `#commerce_product` sin traducir y el preprocess de la tarjeta
  genera la URL desde él.
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
- **Qué campo se traduce y cuál se comparte (2026-08-11)**: solo es traducible lo que hay que
  **redactar** en cada idioma (títulos, textos, etiquetas de enlace). Todo lo demás (referencias a
  entidades, imágenes, números, booleanos, precios, colores) va **compartido**: se cambia una vez y
  vale para los cinco idiomas. Auditados producto, variación, valores de atributo, términos,
  párrafos, nodos y bloques:
  - **El producto y la variación ya estaban bien**: los 18 campos del producto y los 9 de la
    variación son compartidos salvo `body`, `field_composicion` y el `title` base. Se comprueba en
    los datos: las tablas de campo compartido solo tienen fila `es` (`field_tipo_de_producto` 338,
    `field_galeria` 514) y las traducibles tienen una por idioma (`body` 340 × 5).
  - **La talla se comparte, el valor se traduce**: `attribute_talla` en la variación apunta al mismo
    valor de atributo en los cinco idiomas; lo que se traduce es la entidad
    `commerce_product_attribute_value` a la que apunta. Igual `field_relacionados` y
    `field_tipo_de_producto`.
  - **Lo que estaba mal era la home**: 10 campos traducibles cuyo valor no depende del idioma. Los
    de párrafo (`field_secciones`, `field_items`) significaban traducción **asimétrica**: traducir
    la home habría obligado a rehacer las 7 secciones enteras en cada idioma. Ahora son simétricos,
    la estructura es única y lo que se traduce es el texto de dentro de cada párrafo. Verificado
    creando una traducción francesa de prueba: hereda las 7 secciones, las mismas fotos (media 883
    y 1028) y los mismos hijos. También se comparten `field_termino` y `field_imagen_media` de los
    párrafos de categoría y del hero.
  - **El problema histórico de los párrafos simétricos ya no existe** (Paragraphs 8.x-1.21,
    comprobado el 2026-08-11 sobre la propia home). El miedo razonable era: traduzco la home y si
    luego añado una sección en castellano, no aparece en las traducciones. Medido en los dos
    sentidos, añadiendo un párrafo **después** de crear la traducción francesa:
    - **Simétrico** (como está ahora): es 8 secciones, fr 8. El formulario de edición francés pinta
      las 8, incluida la nueva, y no ofrece añadir ni quitar, que es lo correcto: desde una
      traducción solo se traduce el texto.
    - **Asimétrico** (como estaba): es 8, fr **0**. No solo no aparece la nueva, es que la home
      francesa se quedaría vacía hasta rehacerla entera a mano.
    O sea que el asimétrico era justo lo que causaba el problema que se le atribuía al simétrico.
    Lo que sí se pierde con el simétrico: no se puede tener una estructura distinta por idioma (una
    sección promocional solo en español, u otro orden de secciones). Si algún día hace falta eso,
    se vuelve a poner traducible ese campo concreto y se asume el mantenimiento manual.
  - `paragraphs_asymmetric_translation_widgets` **queda sin uso**: los form displays usan el widget
    `paragraphs` estándar y ya no hay campos asimétricos. Se puede desinstalar.
  - **Las líneas de pedido guardan el título en castellano** (`Mochila infantil Sirenita`), porque
    `commerce_order_item` no es traducible y copia el título de la variación, y las 1123 variaciones
    solo existen en `es`. Arreglarlo pide primero traducir los 78 valores de atributo y luego
    regenerar las variaciones por idioma; en ese orden, porque el título de la variación se compone
    del título del producto más el valor del atributo.
- **Las views tienen que filtrar por idioma**: `commerce_product_field_data` tiene una fila por
  idioma y el índice de Search API indexa los cinco, así que sin filtro cada producto salía **cinco
  veces** (74 productos en una categoría daban "370 productos" y 24 tarjetas eran 5 productos
  repetidos). Añadido `search_api_language` en `catalogo` y `langcode` en `productos_destacados`,
  las dos con `***LANGUAGE_language_content***`, que Views sustituye por el idioma de la página: no
  hacen falta displays por idioma. Cualquier view nueva que liste productos necesita lo mismo.
- **Dos tipos de recomendación (2026-08-11, revisado el mismo día)**: similares y complementarios,
  cada uno en su sitio. Los **similares** ("También te puede gustar") son alternativas de la misma
  categoría y salen solos en la ficha: `field_relacionados` manda si algún día se rellena y si no,
  los 4 más recientes del mismo término. Los **complementarios** son el conjunto (la mochila a
  juego, el babero del mismo motivo), viven en `field_complementarios` (compartido entre
  traducciones) y **NO se enseñan en la ficha**: salen en el flyout del carrito como **"Completa el
  conjunto"** (decisión del cliente mirando a la competencia: el complementario convierte al añadir
  al carrito, no antes). Cómo funciona la sección:
  - `CarritoHooks::completaElConjunto()` junta los complementarios de todo lo que hay en el
    carrito, quita lo que ya está dentro, pondera por posición en el campo y enseña **2 como
    mucho**. Se recalcula sola al añadir: probado que al meter la mochila sugerida entra la bolsa.
  - **Añadir directo solo cuando no hay nada que elegir**: los 99 productos de UNA variación llevan
    botón "+ Añadir" contra la ruta `pronens_carrito.anadir` (módulo nuevo `pronens_carrito`, con
    token CSRF; vuelve por el referer y no usa `destination` para no fragmentar la caché del lazy
    builder por página). El resto lleva "Elegir", que abre la ficha. La línea entra lisa, sin
    bordado. Cadenas "Add"/"Choose" con contexto `cart suggestion`: las globales de core ya estaban
    traducidas de otra forma ("Agregar"/"Escoger") y no se pisan.
  - Ojo al verificar con curl: el flyout viaja **escapado dentro del JSON de BigPipe**, un grep del
    HTML plano no lo encuentra.
- **El flyout se abre solo al añadir al carrito (2026-08-11, cliente)**, con la página de detrás
  desenfocada (blur en el overlay, patrón de Natura), en vez del mensaje verde, que confundía. La
  mecánica: `pronens_carrito` escucha `CART_ENTITY_ADD` (cubre el formulario de la ficha Y el
  añadir directo del flyout, los dos pasan por `CartManager::addEntity()`) y deja una marca de un
  solo uso en la sesión que el **preprocess del bloque del carrito** convierte en
  `drupalSettings.pronensCarrito.abrir`, con max-age 0 al consumirla para que el render "abierto"
  no se quede en la caché de render. La señal tiene que viajar en el bloque y NO en
  `hook_page_attachments`: los attachments de página se guardan en la Dynamic Page Cache, así que
  en una ficha ya visitada (el caso normal: acabas de mirarla) el hook de página ni corre y la
  señal se perdía; el bloque es un placeholder de BigPipe que se rehace en cada petición, con
  página cacheada o sin ella. Verificado con caché caliente, como anónimo y como autenticado, y
  que una sesión limpia no hereda la señal. `carrito.js` abre el panel en el attach en el que el
  panel por fin existe (llega por BigPipe, no en el primer attach). El **mensaje verde de Commerce se conserva a propósito** como
  única confirmación sin JS; con JS lo retira `carrito.js` al abrir, reconociéndolo por su enlace a
  la cesta (`a[href$="/cart"]`), que vale en los 5 idiomas. El botón "Seguir comprando" del pie se
  quitó (cliente): para cerrar quedan la X, el overlay y Escape.
- **La descripción de la ficha va plegada** (2026-08-11, cliente): las descripciones migradas son
  largas y el acordeón abierto empujaba el resto fuera de pantalla. Solo se quitó el `open`.
- **La cuadrícula de tarjetas es library propia** (`pronens/grid`, 2026-08-11): `.pro-grid` vivía
  en `catalogo.css` y la franja "También te puede gustar" de la ficha salía en columna, una tarjeta
  tras otra, porque esa hoja solo se carga en el catálogo. Ahora `pronens/catalogo` y
  `pronens/ficha` dependen de `pronens/grid`. Si otra pantalla pinta tarjetas en cuadrícula
  (búsqueda, por ejemplo), que dependa de esa library y no copie las reglas.
- **`field_complementarios` se rellenó con un análisis de los pedidos reales del D7 (2026-08-11)**:
  191 productos con 461 relaciones. El entorno ddev `tiendapronens` (el D7, parado) tiene los 1672
  pedidos facturados; de ahí salieron los pares co-comprados (374 pedidos con 2+ productos), que
  dibujan dos patrones: el **kit de uniforme** (polo + pantalón + chándal + bata, sobre todo GOAR) y
  el **conjunto a juego por motivo** (bata Sakura + mochila Sakura). Señales del motor, por orden de
  peso: mismo motivo cruzando categoría (los motivos se extraen del título quitando el vocabulario
  genérico, con raíces para que Zombi/Zombie y Panda/Oso Panda emparejen, y **los colores no son
  motivo**: "Body rosa + Mascarilla rosa" no es conjunto), co-compra, los 302 relacionados cruzados
  que el D7 tenía curados a mano en `field_productos_relacionados` (la migración no los trajo), y
  misma escuela. El script que aplica **solo escribe si el campo está vacío**, así que lo curado a
  mano no se pisa. Propuesta completa navegable: artefacto "Complementarios Pronens". Copia previa:
  snapshot `pre-complementarios`.
  - **Segunda pasada por categoría (2026-08-11)**: los 168 productos sin señal directa se
    rellenaron con reglas categoría → categoría (bolsa → colchoneta/mochila, body → babero,
    cojín → merch Mikoshin, sudadera con iniciales → mochila con inicial 373...), sacadas de la
    misma matriz de co-compra donde había señal y del contexto donde no. Dentro de la categoría
    destino se eligen los más vendidos del D7, con rotación estable por id para que toda una
    categoría no enseñe la misma franja. `Prendas sanitarias` es la excepción a "complementario =
    otra categoría": el kit (batas + gorros + manguitos) son piezas de la misma. Cobertura final:
    **359 de 360 publicados con complementarios (923 relaciones)**; el que falta es "Test sudadera"
    (260), basura publicada y sin categoría, hermana de los productos 5, 6, 7 y 359.
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
- **El menú `main` es a mano y se dejó fuera un cuarto del catálogo (2026-08-12)**: el mega menú no
  se genera de la taxonomía, es un menú de Drupal (`menu_link_content`) que el tema solo maqueta, y
  al construirlo enlazaba 5 de los 10 términos raíz de `tipo_de_producto`. Faltaban las ramas
  **Moda y Mascarillas** (194 Mascarillas 35 productos, 202 Merch Mikoshin 9, 201 Sudaderas con
  iniciales 8) y **Decoración infantil** (181 Cojines divertidos 38, 196 Láminas decorativas 2): 92
  productos publicados sin ninguna ruta de navegación, incluida la línea de inicial bordada. La
  migración no tuvo culpa: los 30 términos están, y las 47 filas "ignoradas" del mapa son los
  términos ca/en/fr del D7, descartados a propósito. Añadidas las 2 ramas con sus 5 hijas en los 5
  idiomas (script `scripts/menu-categorias-faltantes.php`). Lo que conviene no reinventar:
  - **Las etiquetas salen de los datos**, no de una traducción propia: del nombre del término ya
    traducido, o del idioma hermano cuando hay que acortar (`Sudaderas personalizadas con
    iniciales` da el "sudaderas/dessuadores/sweats/felpe" de `Sudaderas con mensaje`). Esa etiqueta
    ES es la que usaba el menú del D7 para el término 202, cuyo nombre real
    ("Official Merch Mikoshin Saga by Ede Minmore") no dice nada a quien navega.
  - **La estructura del panel se controla con clases en el enlace**, no con código:
    `pro-featured` marca el hijo que aporta foto y pie a la columna destacada, `pro-col-2` dónde
    rompe la segunda columna y `pro-sale` el chip de rebajas. Destacadas nuevas: 201 en Moda (tiene
    foto propia, mientras 194 comparte la del padre) y 181 en Decoración.
  - **"Sanitaria" pasa a "Batas"** (2026-08-12, cliente): de las 5 hijas de esa rama solo una es
    sanitaria (6 productos) frente a 28 de batas escolares, y el término se llama "Batas escolares y
    batas sanitarias". Etiquetas de los 5 idiomas tomadas de ese término.
  - **Desactivados, no borrados**, los enlaces a categorías sin producto: 203 Bandanas y las tres
    repeticiones de Rebajas dentro de los paneles (183 Outlet está vacío). Al irse los tres
    `pro-col-2`, el reparto vuelve al 50% de `splitMegaColumns()` y las columnas quedan 2+1 / 3+2 /
    2+2 en vez de 5+1. Rebajas se queda en la barra.
  - **Los enlaces de menú son contenido, no configuración**, así que no viajan en `config/sync`.
    No hace falta hacer nada al respecto: ver la resolución sobre el volcado literal.
  - **Efecto lateral corregido**: con 7 entradas el nav es más ancho que su columna `1fr` y, al
    llevar `white-space: nowrap`, empujaba el logo 56px a la derecha a 1025px. Franja
    `1025px-1180px` en `header.css` que aprieta hueco y cuerpo del nav; verificado centrado a 1025,
    1100, 1200 y 1280 y con el juego de etiquetas más largo (francés).
  - **Sigue pendiente y es decisión de negocio**: **Rebajas (183 Outlet) y Personaliza (185) llevan
    a páginas de 0 productos**: o se llenan o los enlaces sobran.
- **"Batas guardería" unificada dentro de "Batas Babis Escolares" (2026-08-12, cliente)**: el término
  200 tenía 6 productos (el 240 publicado y los 5 despublicados 15, 23, 24, 25 y 31, los mismos que
  la traducción por IA convirtió en baberos) frente a los 23 del término 176, y separarlos no
  aportaba. Los 6 pasaron a 176, que queda en **24 publicados**, y el término 200 se borró
  (script `scripts/unificar-batas-guarderia.php`, snapshot `pre-unificar-batas-guarderia`). Lo que
  importa de cómo se hizo:
  - **La palabra "guardería" sigue en la navegación**: el enlace 16 del panel Batas no se borró, se
    **repuntó** al término 176 conservando su etiqueta en los 5 idiomas. Así el mismo catálogo tiene
    dos puertas con dos nombres, "Batas guardería" en el panel Batas y "Batas babis escolares" en el
    de Escuela, que es lo que resuelve de paso que la rama grande no estuviera en su propia rama.
    Drupal admite enlaces duplicados al mismo destino; ya lo hacía Rebajas antes de desactivarse.
  - **Los 5 alias del término borrado son ahora 301 al término superviviente**, uno por idioma. Sin
    eso serían 404: son URLs que venían del D7. Se crean **después** del borrado, porque
    `redirect` limpia en `hook_entity_predelete` las redirecciones que apuntan a la entidad que se
    va; como estas apuntan a 176, sobreviven. Verificado un solo salto al alias de cada idioma.
  - **El alias del producto 240 sigue siendo `/productos/batas-guardería/bata-guardería-zombi`**:
    lleva dentro el slug de la categoría desaparecida, pero es un alias migrado del D7, funciona y
    moverlo tiene coste de SEO. No se toca.
  - **La foto destacada del panel Batas sigue siendo Prendas sanitarias** (6 productos) cuando la
    entrada grande pasa a ser Batas guardería (24). Mover la clase `pro-featured` del enlace 18 al 16
    es un cambio de una línea si se quiere.
- **El marquee no está traducido al italiano**: el bloque de contenido tiene ca/en/es/fr pero no
  `it`, así que la home italiana enseña la barra superior en castellano. El italiano se añadió
  después que los otros cuatro idiomas; conviene revisar si hay más bloques en el mismo caso.
- **Redirect activo (2026-08-12)**: `drupal/redirect` 1.13 instalado y encendido, con los valores
  de fábrica, que son los que hacen falta: `auto_redirect: true` crea un **301** de la URL vieja a
  la nueva cada vez que cambia un alias, y `passthrough_querystring: true` conserva la query. Se
  administra en `/admin/config/search/redirect`. Cómo encaja con lo que ya había:
  - **Pathauto ya estaba en `update_action: 2`** (crear el alias nuevo y borrar el viejo), que es
    justo la combinación buena: pathauto **actualiza** la entidad `path_alias` en vez de borrarla y
    crear otra, así salta `hook_path_alias_update` y redirect deja el 301. Verificado en los dos
    caminos: renombrando un término (pathauto regenera `/productos/prueba-redirect-uno` →
    `…-dos` y aparece el 301) y editando un alias a mano en el formulario. Y al **borrar** la
    entidad, `hook_entity_predelete` se lleva sus redirecciones, así que no quedan huérfanas.
  - **Los 366 productos migrados tienen `pathauto_state` a 0**, o sea alias manual: cambiarles el
    título no mueve la URL ni genera redirección. Esto solo actúa sobre lo que cree el cliente de
    aquí en adelante y sobre las ediciones manuales de alias.
  - **El normalizador de rutas queda encendido** (`route_normalizer_enabled`), así que
    `/product/373` y `/taxonomy/term/181` mandan un 301 a su alias en el idioma de la petición.
    Probado que no rompe nada de lo montado: el catálogo con `?page=1`, con facetas y la ficha
    siguen en 200, la query viaja intacta y los menús ya enlazan al alias, así que la navegación no
    paga ningún salto.
  - **No se ha instalado `redirect_404`** (el submódulo que registra los 404 para crear
    redirecciones desde el backoffice) ni se ha dado el permiso *administer redirects* a
    `content_editor`: las dos cosas son decisión del cliente.
  - **Ojo con `drush en` y las traducciones**: instalar el módulo disparó una importación de
    traducciones que **pisó con catalán 8 etiquetas castellanas** de la config por defecto
    (`media.type.image` "Imagen" → "Imatge", los `field_media_*`, `user_picture`). Se han devuelto
    a mano con `drush cset`. Conviene mirar el `git diff` de `config/sync` después de cada
    `drush en` en este sitio; el resto del ruido de ese export (ajustes de `content_translation`,
    `core.base_field_override.media.*`) es deriva vieja, no del módulo, y se dejó sin exportar.
- **Patrones de pathauto, migrados del D7 (2026-08-12)**: pathauto estaba instalado desde la fase 2
  con dos patrones que **no describían el sitio**: `/productos/[title]` genera dos segmentos y 1460
  de los 1822 alias de producto tienen tres. Los patrones del D7 se leyeron de la tabla `variable`
  del dump `/var/www/tiendapronens/www/backup_tienda.sql` (20 filas `pathauto_*`; de las opciones
  generales solo estaba guardada `pathauto_punctuation_hyphen`, el resto eran los valores de fábrica
  de pathauto 7.x-1.2, con `transliterate` apagado: de ahí los 1217 alias con tildes). Todo se aplica
  con `scripts/pathauto-patrones.php`. Lo que hay ahora:
  - **Producto**: `/productos/[commerce_product:field_tipo_de_producto:entity:name]/[commerce_product:title]`,
    el `productos/[categoría]/[title]` del D7. **Los 28 productos sin categoría no necesitan un
    patrón de reserva**: `AliasCleaner::cleanAlias()` colapsa la barra sobrante y deja
    `/productos/titulo`. `field_tipo_de_producto` es multivalor pero los 338 productos que lo usan
    tienen un solo término, y el token sin delta toma el primero.
  - **Prefijo traducido por idioma** (cliente): `productos` / `productes` / `products` / `produits`
    / `prodotti`. Los tres primeros no son invención, ya estaban en el corpus migrado. Va como
    **override de configuración por idioma** (`config/sync/language/<lc>/pathauto.pattern.*.yml`),
    que es lo que lee `PathautoGenerator::getPatternByEntity()` **con el idioma de la entidad**.
    **No usar la condición "Language"** del patrón para esto: `PathautoPattern::applies()` resuelve
    los contextos con `getRuntimeContexts()`, o sea el idioma de **interfaz** de la petición, y
    elegiría mal al guardar una traducción desde un backoffice en otro idioma. Y ojo: pathauto no
    trae fichero `config_translation`, así que **estos overrides no se ven ni se editan en el
    backoffice**; se tocan en el script.
  - **Página**: `/[node:title]` para el tipo `page`, como `pathauto_node_page_pattern`. El tipo
    `home` no lleva patrón, es la portada.
  - **`ignore_words` vaciada**: era la lista inglesa de fábrica en una tienda es/ca/fr/it. No
    filtraba `de`, `la`, `con` ni `para`, y en cambio borraba `a`, `in`, `on`, `per`, `via`, `like`,
    que en esos idiomas significan algo. **`enabled_entity_types` vacío**: `user` inyectaba un campo
    "URL alias" en el perfil de 1580 usuarios sin patrón ni un solo alias, y el cliente ha decidido
    no replicar el `users/[user:name]` del D7.
  - **Se movieron 179 alias y quedaron sus 179 redirecciones 301**, comprobadas por HTTP. Solo se
    mueve lo que pathauto gobierna de verdad: `updateEntityAlias()` sale por
    `$entity->path->pathauto != PathautoState::CREATE`, así que los **351 productos migrados en
    estado manual no se tocan** ni con un bulk update. El total de `path_alias` no cambió (2033):
    fueron actualizaciones, no altas ni bajas.
  - **Las 3 categorías del menú que estaban en estado manual** (190 Moda y Mascarillas, 194
    Mascarillas tela reutilizables, 201 Sudaderas con iniciales) tenían el alias **en castellano en
    los cinco idiomas** y se pasaron a automático: sin eso el menú francés mezclaba
    `/fr/produits/…` con `/fr/productos/…`. Ahora las 30 categorías siguen el patrón.
  - **Conviven dos prefijos a propósito** en los idiomas no castellanos: las categorías van en
    `/ca/productes/…` y los 1694 alias de producto migrados siguen en `/ca/productos/…` porque están
    congelados. Unificarlo pide re-aliasar el catálogo entero, que es otra decisión y de otro tamaño.
  - **Sin patrón para `escuelas`** (el `escuelas/[escuela]/[title]` del D7) ni para `guia_tallas`,
    `recomendaciones_de_lavado`, `color_letra`, `fuente_bordado`, `extras` y `tags`: comprobado con
    curl que **todas** sus páginas de término dan 404 desde que la view del catálogo se quedó
    `entity.taxonomy_term.canonical`. Un alias para una página que no existe no sirve de nada. En el
    D7 sí respondían, por eso allí había un `[term:vocabulary]/[term:name]` genérico.
  - **Los 19 productos de uniforme escolar no tienen `field_tipo_de_producto`**, solo
    `field_escuela`, así que no salen en ninguna página de categoría y su término da 404. Es el
    mismo agujero de navegación que el del menú, y sigue pendiente de decisión de negocio.
  - Copia previa: snapshot `pre-patrones-pathauto`.
- **Teselas de la home con degradado en vez de chip blanco (2026-08-12, cliente)**: el chip blanco
  con el nombre en versalitas se sustituye por el tratamiento del prototipo: degradado suave hacia
  negro anclado abajo (`.pro-tile::before`), recuento en versalitas, nombre en blanco y flecha en
  círculo blanco que pasa a turquesa al hacer hover. Lo que conviene no reinventar:
  - **El recuento se cuenta filtrando por idioma**, porque `status` es traducible: sin el filtro un
    producto despublicado solo en castellano seguiría sumando (la consulta encontraría publicada su
    traducción catalana) y el número no cuadraría con el del catálogo, que ya filtra por idioma en
    la view. Comprobado: con el producto 45 despublicado en `es`, la home española decía 88 y la
    catalana 89. Los productos referencian términos **hoja**, así que el término de la tesela suma
    su subárbol entero, igual que los chips de best_sellers (`Bodys bebé` está dentro de
    `Baberos bebé y complementos`, de ahí que 89 incluya esos 41).
  - **Etiqueta `commerce_product_list`** en el preprocess, no solo las del término: el recuento
    cambia al publicar o despublicar, no al editar la categoría. Verificado sin vaciar cachés.
  - **La cadena es "1 piece"/"@count pieces" con contexto `category tile`** y no venía traducida en
    ningún idioma, así que las cuatro traducciones las crea
    `scripts/traducir-piezas-mosaico.php`. Contexto propio para no pisar los "piece(s)" de core, que
    hablan de contenido. Ojo: en Drupal 11 el separador de plurales es `PoItem::DELIMITER`, ya no
    existe `LOCALE_PLURAL_DELIMITER`.
  - **No se han tocado las proporciones de la cuadrícula**, que en el prototipo son más altas y
    dejan la tesela grande en vertical. Las cuatro fotos de término son horizontales y ninguna pasa
    de **876px de alto** (1332×876, 1789×844, 1063×741, 1232×868), así que una tesela vertical de
    ~730px obligaría a ampliar. Si el cliente la quiere igual, hace falta primero foto nueva y un
    estilo de imagen vertical; `pronens_mosaico` es 900×700.
  - **Contraste**: el degradado mantiene 0,58 de negro hasta 96px de alto porque ahí vive el texto y
    muchas fotos son bodegones sobre fondo claro. En el peor caso (foto blanca) el blanco da 5,3:1 y
    el recuento al 92% 4,7:1. La flecha turquesa del hover con la flecha blanca da 3,2:1, por encima
    del 3:1 que piden los elementos no textuales.
  - Los títulos largos se acortan con el campo **"Etiqueta"** de la tesela, que es lo que ya hace el
    cliente ("Baberos y bodys bebé" en vez del nombre del término).
- **La puesta en producción es un volcado literal de este ddev (2026-08-12, cliente)**: se migra la
  base de datos entera y después se ajusta en destino lo que haga falta. Consecuencias prácticas
  para todo lo que se haga de aquí en adelante:
  - **No hay que hacer portable el contenido**. Enlaces de menú, bloques, productos, términos,
    alias y redirecciones viajan en el volcado. Los scripts de `scripts/` valen como registro de
    qué se hizo y para repetir el trabajo si hubiera que rehacerlo, no como paso de despliegue.
  - **`config/sync` sigue importando** porque es lo que va en git y lo que documenta la estructura,
    pero deja de ser el vehículo del despliegue.
  - Lo que sí hay que revisar en destino es lo que depende del entorno y no del contenido:
    credenciales de las pasarelas (**las tres siguen en modo test**), rutas de ficheros y permisos
    de `sites/default/files`, el dominio, y el cron.
- **Las páginas del CMS se montan con Paragraphs; NO hay tipo "landing" (2026-08-12)**: el tipo
  `page` ya tenía alias, menú y traducciones, y lo único que le faltaba era el campo de secciones.
  Se le añadió `field_secciones` reutilizando el storage de la home, así que cualquier página puede
  ser una landing y las de texto corrido (Aviso legal, Envíos, Formas de pago) siguen con `body`.
  Lo que distingue a una landing es **tener párrafos**, no el bundle: `PronensHooks::esLanding()`
  mira si `field_secciones` está lleno y, si lo está, quita el contenedor central (`main_boxed`) y
  el bloque de título, porque el H1 lo pone el hero de la primera sección. El campo acepta también
  los párrafos de la home (hero, beneficios, mosaico, best_sellers, historia, newsletter), así que
  una página puede reutilizarlos sin duplicar nada.
  - **Tipos nuevos, solo los que la home no cubría**: `cifras`/`cifra` (la franja de datos),
    `texto_medios` (texto con una o dos fotos escalonadas), `valores`/`valor` (rejilla de tarjetas
    con icono) y `cta` (bloque de cierre, con `field_estilo` para elegir entre banda oscura y
    cierre centrado: son el mismo bloque con dos pieles, no dos tipos).
  - **"Cómo lo hacemos" reutiliza `pasos_personalizacion`**, el mismo párrafo que la
    personalización de la home. Se le añadieron dos campos opcionales que la home deja vacíos:
    `field_texto` (entradilla) y `field_icono` en `paso`. Con más de tres pasos el template los
    reparte en dos columnas, y eso lo decide el **número de items** en el preprocess, no un campo:
    un "estilo" que el editor pudiera contradecir con el contenido sobra.
  - **Las bandas de color se alternan solas**: `pasos_personalizacion` pinta crema y lleva un
    `margin-top` que en la home es aire (encima tiene una sección blanca), pero detrás de `valores`
    dejaba una franja blanca entre dos cremas que parecía un fallo. Ahora, cuando la sección
    anterior es de las que pintan crema (`PronensHooks::SECCIONES_CREMA`), sale en blanco, que es
    lo que hace el prototipo. **No vale un selector CSS de hermanos**: Drupal envuelve cada párrafo
    en su `field__item`, así que las secciones no son hermanas en el DOM; lo resuelve
    `seccionAnterior()` en el preprocess.
  - **Los iconos son un juego cerrado** (`field_icono`, lista de 9 valores) pintado inline por
    `templates/misc/pro-icono.html.twig`. No hay fichero de sprite: son 9 trazos cortos y ninguno
    se repite lo bastante para amortizar una petición más.
  - **Ojo al crear campos de lista por código**: `FieldStorageConfig::create()` con
    `allowed_values` revienta en este Drupal (11.4.4) con *"the configuration property
    settings.allowed_values.0.label.0 doesn't exist"*, en cualquier entidad y también en un segundo
    save. Config cachea el esquema antes de tener los datos completos y resuelve `label` como si
    fuera un array. El rodeo, en `scripts/landing-paragraphs.php`: crear el storage con la lista
    vacía y rellenarla con el config factory.
- **"Quiénes somos" montada con secciones (2026-08-12)**: el nodo 4 pasa de texto corrido del D7 a
  las 7 secciones del prototipo (`scripts/quienes-somos.php`). El texto **no se inventó**: sale del
  `body` que ya tenía, troceado; el `body` se vació para que no salga dos veces y queda en la
  revisión anterior. Lo que se cambió respecto al prototipo y por qué:
  - El prototipo pone **"3 países con envío gratis"** y el marquee real dice "España, Portugal y UE
    desde 60 €": esa cifra se sustituyó por las **72 h** del bordado, que sí están en el marquee.
    El "desde 1986" del hero también sale de ahí, no del prototipo.
  - El segundo botón del cierre **no puede llevar a la categoría "Personaliza" (185)**, que está
    vacía: lleva a la ficha de la mochila con inicial (373), que es la que mejor enseña el bordado.
  - El título se corrigió a "Quiénes somos" (con tilde). El alias no se mueve: el nodo está en
    `pathauto_state` 0.
  - **Las cuatro fotos salen del propio prototipo**, extraídas de los base64 de
    `pronens-nosotras-prototipo.html` y guardadas en `design/assets/nosotras-*.jpg` (taller,
    máquina bordando, prendas con hilos, equipo). **Su XMP dice `trainedAlgorithmicMedia`: son
    imágenes generadas por IA**, no fotos de Pronens. Enseñan un taller y un equipo que no existen,
    así que sirven para ver la página terminada pero **hay que sustituirlas por fotos reales antes
    de publicar**. Las dos verticales usan `pronens_card` (600x800) y no `pronens_cuadro`: el
    estilo apaisado las recortaba una vez en el servidor y otra en el CSS.
  - **El alias solo existía en castellano**, así que cambiar de idioma desde la página daba 404
    aunque el nodo sí responde en los cinco (`/ca/node/4` → 200). Creados los otros cuatro con el
    mismo slug, que es lo que ya hacía el Aviso Legal en ca/fr/it. El nodo está en estado manual,
    de modo que traducir el título más adelante no moverá ninguno.
  - La página **solo está en castellano**: los párrafos son traducibles (simétricos), así que
    traducirla es rellenar textos, pero esa redacción está pendiente.
- **Todo el correo pasa por Mailer Plus (2026-08-13)**: `drupal/symfony_mailer` 2.0.2 con sus tres
  submódulos (`mailer_policy`, `mailer_transport`, `mailer_override`). Se descartó el plugin
  `symfony_mailer` **de core**, que es `@internal` y **solo manda texto plano** (su `format()` pasa
  el HTML por `MailFormatHelper::htmlToText()` y su `mail()` hace `->text()`), y también
  `symfony_mailer_lite`, que da transporte y HTML pero ni envoltorio común, ni asunto y cuerpo
  editables, ni CSS inline. Lo que conviene no reinventar:
  - **`mailer_override` sustituye `plugin.manager.mail` para todo el sitio**, así que
    `system.mail.yml` deja de mandar y cualquier correo nuevo de un contrib hereda la marca sin
    tocar nada. Los overrides se activaron con **`import`, no con `enable`**: `enable` deja las
    políticas por defecto **en inglés**, mientras que `import` convierte lo que ya había y conserva
    el copy castellano y los interruptores de `user.settings.notify`.
  - **El `drush mailer:override import user` de fábrica revienta** (`preg_replace()` sobre un
    TranslatableMarkup, `MailerOverrideCommands.php:58`). El rodeo:
    `ddev drush ev 'Drupal::service(\Drupal\mailer_override\OverrideManagerInterface::class)->action("user", "import");'`.
  - **El `sendmail` por defecto NO llega a Mailpit en este ddev**: Symfony ejecuta
    `/usr/sbin/sendmail -bs`, que en el contenedor es **exim4**, no el binario de Mailpit que
    php.ini declara en `sendmail_path`. Por eso hay un transporte SMTP contra `127.0.0.1:1025`. El
    que se **exporta** es `sendmail` (la puesta en producción es un volcado literal de esta base de
    datos y un `default_transport: mailpit` allí dejaría la tienda muda) y el de Mailpit se fuerza
    como override en `settings.local.php`. Funciona porque `Transport::isDefault()` llama a
    `getOriginal()` a propósito para ignorar los overrides.
  - **Maquetación en el tema, hooks en un módulo.** `hook_mailer_*` los invoca
    `moduleHandler->invokeAll()`, o sea que **un tema no los recibe**: `pronens_mail` adjunta las
    libraries y resuelve el destinatario, y el tema pone plantillas (`templates/email/`), CSS
    (`css/email/`) y variables. No se creó un tema de correo aparte porque
    `InlineCssEmailAdjuster` **solo inlinea las libraries del propio correo** (no arrastra
    `base.css`), y porque el recibo pinta la línea de pedido con `LineaPedidoTrait`, que vive en el
    tema y ya comparten flyout, cesta y checkout.
  - **El logo del correo es texto, no imagen**: la marca de la tienda es el wordmark "pronens." con
    el punto turquesa, así que se pinta con texto y se ve igual con las imágenes bloqueadas. No
    hace falta el PNG que pedía el plan.
  - **En el CSS de correo no se puede usar `var()`**: el inliner copia la declaración literal y
    Outlook descarta las custom properties. `css/email/email.css` lleva los hexadecimales a mano
    con la tabla de equivalencias con `tokens.css` en la cabecera. Y **las fuentes self-hosted no
    existen en el correo**: la maqueta está pensada para Arial.
  - **Cuidado con la especificidad al inlinear**: `CssToInlineStyles` resuelve por especificidad
    igual que un navegador, así que `.pro-mail__body a` se comía a `.pro-mail__btn-link` y el texto
    del botón salía **turquesa sobre naranja**. Las utilidades van con doble selector
    (`.pro-mail__body .pro-mail__btn-link`). El `<style>` de la plantilla **sí sobrevive**
    (`convert()` lee sus reglas pero no borra la etiqueta), que es donde viven las media queries y
    el modo oscuro.
  - **El idioma se le pregunta al correo, no al gestor de idiomas.** `MailerPlus::doSend()` lo
    deduce SOLO de la dirección de destino, y `Address::create()` devuelve langcode vacío para un
    correo suelto: como el checkout es de invitado, **sin corrección el 100% de los recibos saldría
    en castellano**. Lo arregla `IdiomaPedido` (idioma del pedido → preferido de la cuenta →
    interfaz → sitio) con `IdiomaPedidoSubscriber`, que apunta el idioma de compra en
    `$order->setData('pronens_langcode', …)` porque `commerce_order` **no tiene columna `langcode`**.
    Y medido aquí: durante el `build` el idioma activo es el bueno, pero **cuando se pinta el
    envoltorio ya ha vuelto al del sitio**, así que las URLs del pie salían sin prefijo en un correo
    francés. Por eso el preprocess usa `$variables['email']->getLangcode()` y pasa
    `['language' => $idioma]` a `Url` y a `getTranslationFromContext()`.
  - **El recibo no usa el formateador de Commerce.** El override descarta
    `commerce-order-receipt.html.twig` y el modo de vista `email` de fábrica trae
    `commerce_order_item_table` (que no enseña bordado, ni extras, ni ajustes) más un
    `commerce_shipping_information` que devuelve **la tarjeta del backoffice sin la dirección de
    entrega**, y un `billing_profile` que sale **vacío** porque
    `symfony_mailer_preprocess_commerce_order()` borra `billing_information` como rodeo del issue
    2949726. Todo eso lo pinta el tema: líneas con `LineaPedidoTrait`, totales con
    `commerce_order.order_total_summary` (los mismos números que el checkout) y las direcciones
    desde los perfiles.
  - **En correo mandan los atributos `width`/`height` del `<img>`**, no el CSS (Outlook usa el motor
    de Word): la miniatura se sirve a 148 y se declara a 72, que además la deja nítida en pantallas
    de densidad doble. Van en `#attributes` y no en `#width`/`#height`, que son las medidas del
    original y las vuelve a pasar por el estilo de imagen.
  - **Las Mailer Policy sí son traducibles** (asunto es `label` y cuerpo es `text_format`, y
    `changeActiveLanguage()` aplica el override del idioma al enviar), pero el módulo **no trae
    fichero `config_translation`**, igual que pasó con pathauto: lo aporta
    `pronens_mail.config_translation.yml` y la pestaña sale en
    `/admin/config/system/mailer/policy/<id>/translate`. **El copy va solo en castellano** por
    decisión del cliente (2026-08-13); las traducciones se rellenan desde ahí cuando se quiera. Lo
    que sí está en los cinco idiomas es el marco (cadenas de interfaz de las plantillas).
  - **Aviso de pedido a la tienda (2026-08-13)**: Commerce **solo escribe al cliente**, así que un
    pedido podía entrar sin que en el taller se enterara nadie hasta abrir el backoffice.
    `PedidoAdminMailer` escucha `commerce_order.place.post_transition` con **la misma prioridad
    (-100) que `OrderReceiptSubscriber`**, de modo que el aviso y el recibo salen a la vez. Se
    descartó el `receiptBcc` del tipo de pedido, que es lo que trae Commerce: manda a la tienda una
    copia del correo DEL CLIENTE ("¡Gracias por tu pedido!"), sin teléfono, sin pasarela, sin enlace
    a la ficha del pedido y en el idioma en el que compró el cliente. Este lleva cliente, correo,
    teléfono (que vive en el **perfil de facturación**, `field_telefono`, no en el pedido),
    pasarela, idioma del cliente, botón al backoffice y el pedido entero. Va **siempre en castellano**
    (no se le pasa langcode, así que cae en el idioma del sitio: lo lee Pronens, no quien compra) y
    con **`Reply-To` al cliente**, que es a quien se acaba escribiendo. El destinatario está en la
    política (`<site>`), no en el código, para que se pueda añadir a alguien del taller sin tocar
    nada.
  - **Correo de expedición nuevo**: `EnvioMailer` escucha `commerce_shipment.ship.post_transition`,
    no el alta de la expedición: `GestorExpediciones::generar()` deja el envío en `ready` porque la
    mercancía sigue en el almacén, y es la sincronización del seguimiento la que aplica `ship`
    cuando el transportista la recoge. La pantalla de gracias del checkout ya lo prometía por
    escrito y no lo mandaba nadie.
  - **Formulario de contacto nuevo** (`contact.form.contacto`, `/contact`), con enlace en
    `footer-ayuda` en los 5 idiomas. El destinatario **no vive en el formulario** sino en la
    política `contact.page.mail`: `ContactOverride` quita ese campo de la pantalla de
    administración. Hubo que dar `access site-wide contact form` a anónimo y autenticado, que no lo
    tenía nadie. Antispam: de momento el control de inundación de core.
  - **Remitente único `pronens@pronens.com`** (cliente): antes convivían el `.es` del sitio y el
    `.com` de la tienda de Commerce, y dos dominios obligan a dos configuraciones de SPF y DKIM.
  - Scripts: `correo-base.php` (transportes y remitente), `correo-politicas.php` (copy de los 14
    correos y modo de vista del pedido), `contacto.php`, `traducir-correo.php`,
    `correo-pruebas.php` (dispara todos o uno) y `correo-verificar.php` (revisa la bandeja de
    Mailpit contra 8 invariantes). Mailpit: https://tiendapronensd11.ddev.site:8026.
  - **Pendiente**: el SMTP de producción (host `smtp.odisean.net`, puerto 587 con STARTTLS, usuario
    `pronens.com@c.odisean.net`, contraseña en `settings.local.php` vía `getenv()`, nunca en
    `config/sync`), y comprobar SPF/DKIM/DMARC de `pronens.com` autorizando a `odisean.net`.
- **Área de cliente montada (2026-08-20)**: login, "Mis pedidos", ficha de pedido con línea de
  tiempo del envío, resumen de cuenta y navegación lateral común (`CuentaHooks`, libraries
  `pronens/cuenta` y `pronens/login`, cadenas en `scripts/traducir-cuenta.php`). Lo que conviene no
  reinventar:
  - **Se entra con el correo**: contrib `login_emailusername` 3.0.1 (D11, estable). Imprescindible
    porque solo 118 de los 1578 usuarios migrados tienen el correo como nombre de usuario; el resto
    son `victor1`, `educoland`… que nadie recuerda. La etiqueta del campo la pone el tema con el
    correo por delante ("Correo electrónico o nombre de usuario").
  - **El estado de cara al cliente sale del ENVÍO, no del pedido**: el workflow `order_default`
    pasa a `completed` en el momento de comprar, así que todos los pedidos dirían "Completado" con
    la caja aún en el taller. `CuentaHooks::estadoDelPedido()` mira el estado del shipment y la
    situación del seguimiento de Correos Express (`cex_ultimo_estado`, que escribe el
    sincronizador): En preparación → Enviado → Entregado (y Devuelto/Cancelado). La ficha pinta la
    línea de tiempo con esas mismas fuentes y enlaza el seguimiento público
    (`https://s.correosexpress.com/c?n=…`, la misma URL que el correo de expedición).
  - **Un tema no puede implementar el mismo preprocess dos veces** (ya documentado en la view del
    catálogo): `preprocess_page` despacha desde PronensHooks, `preprocess_views_view` desde
    CatalogoHooks y `preprocess_commerce_order` desde CorreoHooks hacia CuentaHooks.
  - **Las líneas y los totales son los del trait compartido** (`LineaPedidoTrait` + el servicio de
    totales), así que la ficha del pedido enseña exactamente lo mismo que el flyout, la cesta, el
    checkout y el recibo por correo: bordado, formato, extras y recargos incluidos.
  - **La lista de pedidos no pinta los campos de la view** `commerce_user_orders`: las tarjetas se
    montan desde las entidades del resultado (miniaturas y estado no existen como campos de Views).
    El H1 "Mis pedidos" va por traducción de interfaz porque el título de la view solo existe en
    castellano.
  - **Permisos nuevos del rol autenticado**: `view/update own customer profile` y
    `create customer profile` — sin ellos la libreta de direcciones de Commerce daba 403. En
    `config/sync` van ese cambio y el alta del módulo en `core.extension`.
  - **El bloque de título y las pestañas de core se quitan solo en la cuenta PROPIA** (y en el
    login): las pestañas ("Ver", "Editar", "Agenda de direcciones", "Medios de pago") duplican la
    navegación lateral con etiquetas de backoffice. Un administrador mirando a otro usuario las
    conserva. Los H1 de editar (salía el nombre de usuario) y direcciones se realinean en
    `preprocess_page_title`, con contexto de caché `user` porque el mismo H1 cambia según quién
    mira.
  - **La URL de logout lleva token CSRF de sesión**: la cáscara añade el contexto `session` para
    que otra sesión del mismo usuario no herede un token ajeno de la Dynamic Page Cache.
  - **"Medios de pago" queda fuera del menú lateral a propósito**: con Redsys y PayPal en modo
    redirect no hay tarjetas guardadas que gestionar. La ruta sigue existiendo.
  - **Decidido con el cliente (2026-08-20)**: (1) se crea cuenta desde la pantalla de gracias
    (ver la resolución siguiente); (2) la libreta se queda con UNA dirección (`allow_multiple: 0`),
    es suficiente; (3) los pedidos de invitado SÍ se enlazan por correo (módulo `pronens_cuenta`,
    resolución siguiente); (4) "Medios de pago" sigue fuera del menú lateral.
  - **Datos de prueba**: usuario `cliente-prueba` (uid 1590, cliente@pronens.test) con los pedidos
    47, 69 y 70 asignados y el envío 5 simulado como entregado (tracking `PRUEBA1234567890`).
    Son datos de desarrollo: limpiar antes del volcado a producción.
- **Cuenta nueva desde la pantalla de gracias y pedidos de invitado enlazados (2026-08-20,
  cliente)**: el registro del sitio sigue en `admin_only` (no hay /user/register público) y lo que
  se activa es el pane `completion_register` del checkout, que NO mira esa opción: quien acaba de
  comprar puede ponerse contraseña en la pantalla de gracias, sin tocar el embudo de compra. El
  pane se esconde solo si el correo ya tiene cuenta, que encaja con el enlazado. Detalles:
  - El pane construye sus campos con el form display `register` del usuario y arrastraba la FOTO
    DE PERFIL; la esconde `CheckoutHooks::formCheckoutAlter`, que también quita las descripciones
    de core y alinea el copy con el tuteo de la tienda ("Crea tu cuenta", pisando el "Cree su
    cuenta" de la traducción de Commerce, en `scripts/traducir-cuenta.php`).
  - **Los pedidos de invitado se enlazan con la cuenta de su correo** (módulo `pronens_cuenta`):
    al REALIZARSE el pedido si la cuenta ya existe (subscriber en el `pre_transition` de place, a
    propósito: ahí basta `OrderAssignment::assign(..., FALSE)` sin save, el de la transición lo
    persiste, y el AddressBookSubscriber de Commerce y el recibo ya ven el pedido asignado), y al
    CREARSE una cuenta para los pedidos anteriores (`hook_user_insert`, carritos draft excluidos:
    los gobierna la sesión de commerce_cart). Probado en los dos sentidos y que el carrito no se
    toca. Para lo ya existente en producción: `scripts/enlazar-pedidos-invitado.php`, idempotente.
  - **Ojo al probar el paso `complete` a mano**: colocar el pedido por drush y abrir
    /checkout/N/complete vale UNA vez por sesión anónima (la visita invalida el id de carrito de
    la sesión y no lo pasa a completado, eso solo lo hace el flujo real de pago). La compra entera
    con registro queda por probarse con la tarjeta de test de Redsys.
- **Chrome headless no baja de 500px de ancho** en macOS: `--window-size=390` da `innerWidth=500` y
  el recorte parece un desbordamiento horizontal que no existe. Antes de dar por buena una captura
  estrecha, comprobar el viewport real (`innerWidth`) o mirar en el navegador de verdad.
- **La BBDD local es un import de producción (2026-09-01)**: la tienda ya está viva y el volcado
  vino de vuelta para depurar con datos reales (4 pedidos, el nº 4 = id 79 con bordados y nubes).
  Consecuencias: los productos basura 5, 6 y 7 ya no existen (los borraron en producción; 260 y 359
  siguen), producción desinstaló el módulo `update` (exportado en `config/sync`, es su realidad), y
  el transporte de correo por defecto ES el SMTP de Odisean con credenciales (el override de
  Mailpit de `settings.local.php` sigue mandando en local). Datos de prueba de esta sesión
  limpiados: pedido 80 borrado, su transacción de stock revertida y la secuencia de numeración
  devuelta al 4.
- **La ficha admin del pedido enseña el detalle de cada línea (2026-09-01, cliente)**: la view
  `commerce_order_item_table_admin` que Commerce embebe en /admin/commerce/orders/N solo daba
  título, cantidad y precios: no había forma de ver el bordado, la nube ni el SKU comprado.
  `PedidoAdminHooks` (en `pronens_personalizacion`, que es quien posee esos campos) lo añade bajo
  el título vía preprocess del campo `title`: SKU enlazado a la ficha con `?v=ID` (preselecciona la
  variación, el mismo patrón de los chips), enlace "Editar variación", los campos de
  personalización de la línea (texto, formato, fondo/nube, extras, con la etiqueta de la definición
  de campo, que sigue los renombres del cliente) y el desglose de recargos `fee` (las columnas de
  precio de la tabla NO los incluyen). No vale el `LineaPedidoTrait` del tema: el backoffice se
  pinta con el tema de administración, que no ejecuta los hooks del tema de la tienda. Ni columnas
  nuevas: cinco campos más dejarían la tabla inusable.
- **Variaciones buscables por SKU en el backoffice (2026-09-01, cliente)**: view nueva
  `pronens_variaciones` (/admin/commerce/variaciones, en el menú de Commerce) con las 1123+
  variaciones filtrables por SKU, título, producto y estado, y además filtro de SKU en
  /admin/commerce/products (relación `variations` + DISTINCT, o cada producto sale una vez por
  variación coincidente). Ojo con la relación variación→producto: `commerce_product_field_data`
  tiene una fila por idioma y sin el filtro `default_langcode` del producto cada variación salía
  5 veces. Script: `scripts/admin-variaciones.php`.
- **El buscador de la lupa existe (2026-09-01, cliente)**: /buscar era un enlace muerto del tema.
  Ahora la lupa abre un `<dialog>` con sugerencias en vivo (foto, precio "desde", SKU coincidente y
  enlace con `?v=ID`) y Enter lleva a /buscar, la página completa; sin JS la lupa va directa a
  /buscar. La referencia funcional es el `activity_search_pro` del D10 (overlay + nombre O
  referencia), corrigiendo lo que allí estaba mal: aquí filtra publicados (entity_status del
  índice), por idioma, con límite y cacheable. Piezas y trampas:
  - **Misma consulta en los dos sitios**: la view `buscar` (índice `catalogo`, fulltext expuesto
    sobre `titulo` + `sku`) y el endpoint `/buscar/sugerencias` del módulo nuevo `pronens_buscador`
    usan los mismos campos, idioma y matching, así que sugerencias y resultados no discrepan.
  - **El parámetro expuesto es `texto`, no `q`**: Views descarta `q` de la exposed input a
    propósito (el viejo parámetro de ruta de Drupal). Con identifier `q` el filtro no aplicaba y
    /buscar devolvía el catálogo entero.
  - **El `sku` del índice (`variations:entity:sku`) tiene que estar en los procesadores
    `ignorecase` y `transliteration`**: la consulta se lowercasea entera (porque `titulo` está en
    ignorecase) y el SKU indexado en mayúsculas no lo encontraba nadie (la columna `word` es
    case-sensitive). Y el tokenizer ya descarta `. _ -`, así que BG.OSOTRIB.PEQ se indexa
    `bgosotribpeq` y "bg osotrib" también lo encuentra.
  - **`matching: partial` en el server**: sin ello un fragmento de referencia ("OSOTRIB") no
    encuentra nada. Solo afecta a consultas fulltext; el catálogo no las usa.
  - **Excluye los productos sin categoría** (filtro `tipo` not empty en la view y en el endpoint):
    la basura del D7 ("Test sudadera" 260, "Pedido 7682" 359) está publicada y saldría. Esto
    también deja fuera los 19 productos de escuela, que hoy no tienen ninguna ruta de navegación
    (decisión de negocio pendiente, ya documentada).
  - Título de la view traducido por override de config por idioma (como los patrones de pathauto),
    cadenas de interfaz en `scripts/traducir-buscador.php` (incluye las dos del detalle de pedido
    admin), montaje en `scripts/buscador.php`. Tras tocar el índice: `sapi-c && sapi-i`.
- **El aviso de pedido a la tienda también sale en el "place" manual (2026-09-01, verificado)**:
  `PedidoAdminSubscriber` escucha la transición, no el checkout, así que colocar un pedido desde el
  backoffice dispara el mismo "Pedido nuevo #N" a pronens@pronens.com además del recibo al cliente
  (comprobado en Mailpit con un pedido de prueba). Si en producción no llega, lo que hay que mirar
  es el buzón/spam y el SPF/DKIM de pronens.com, no el código.
- **El tipo de paquete tiene que estar en TODOS los métodos de envío (2026-09-02)**: la primera
  expedición real de la tienda (pedido 4) la rechazó Correos Express con **«ALTO BULTO: FORMATO
  INCORRECTO. FORMATO VALIDO - 99999.99»**. La causa no estaba en el alto: los cinco métodos de
  Correos Express llevaban `pronens_bolsa`, pero ese pedido pasó de 60 € y se envió con **«Envío
  gratuito desde 60 €», que es un `flat_rate`** y se había quedado con el `custom_box` de contrib,
  de **1x1x1 milímetros** (le pasaba lo mismo a «Recoger en Pronens»). Con eso el bulto salía con
  `alto: 0.001` y el campo solo admite dos decimales. Tres cambios, y los tres hacen falta:
  - **Los dos métodos que faltaban ya llevan `pronens_bolsa`**, con
    `scripts/cex-tipo-paquete.php`, que además repara los envíos pendientes que se quedaron con el
    `custom_box` (`setPackageType()` recalcula el peso solo, porque suma la tara). Los métodos de
    envío son **contenido, no configuración**: no viajan en `config/sync`, así que el script hay
    que ejecutarlo (o cambiarlo a mano) en cada entorno.
  - **`Normalizador::metros()` escribe siempre dos decimales**, que es el formato del campo. Antes
    redondeaba a tres, y con medidas normales (0,25 m) no se notaba: solo reventaba con
    milímetros. Por debajo de un centímetro manda `0.00`, que es lo que la API acepta cuando no se
    conoce la medida y lo que hace la integración oficial.
  - **`GestorExpediciones::medidasInsuficientes()` avisa antes de crear nada**, en el formulario de
    alta y en el masivo, cuando el paquete no llega al mínimo de Correos Express (15x10x1 cm). El
    mensaje de la API no nombraba el tipo de paquete por ningún lado, y una expedición **no se
    puede anular**: el aviso vale más que el diagnóstico a posteriori.
  - **El alta se puede lanzar desde la ficha del pedido (2026-09-02, cliente)**:
    `CorreosExpressHooks::accionesEnLaFichaDelPedido()` pone los botones de Correos Express dentro
    de la tarjeta «Información de envío» de `/admin/commerce/orders/N`, reutilizando
    `operacionesDeEnvio()` para no tener dos sitios donde decidir qué acción toca. Tres cosas que
    costaron encontrar: **`links` es un theme hook, no un tipo de elemento**, así que el render
    array lleva `#theme` y no `#type` (con `#type` no pinta nada y no da ningún error); la tarjeta
    de Commerce **solo pinta el primer envío** (`ShippingInformationFormatter::viewElements`), de
    modo que en un pedido con varios hay que seguir yendo a la pestaña; y **no se añade
    `destination`** porque el formulario ya redirige a la etiqueta al crear la expedición, que es
    lo siguiente que se hace, y un destino la pisaría.
  - **La recogida en tienda no ofrece el botón**: `GestorExpediciones::seExpide()` mira la lista
    `metodos_sin_expedicion` de los ajustes, que es **configuración y no código**, porque los ids
    de los métodos son de cada tienda y mañana puede haber otro punto de recogida. Se marca en
    `/admin/commerce/config/correos-express`; aquí está marcado «Recoger en Pronens» (id 6). El
    filtro vive en `operacionesDeEnvio()`, así que desaparece a la vez de la ficha del pedido y de
    la pestaña de envíos.
  - **La pantalla de envíos ya no está en inglés (2026-09-02)**: Commerce Shipping no traduce ni
    sus estados ni sus transiciones, así que la pestaña «Envíos» decía «Draft» y ofrecía «Finalize
    shipment» y «Cancel shipment», que son justo los botones que mueven un envío hasta «Enviado» y
    disparan el aviso al cliente. Lo arregla `scripts/traducir-envios.php`. Tres cosas que hay que
    saber para tocarlo:
    - **Los contextos son obligatorios**: `WorkflowTransition::getLabel()` pide la cadena con
      contexto `workflow transition` y `WorkflowState::getLabel()` con `workflow state`.
      Traducidas sin contexto no las mira nadie, y no da ningún aviso: la pantalla sigue en inglés.
    - **«Tracking link» no es una cadena de interfaz**, es la etiqueta de un campo de la vista
      `order_shipments`, o sea configuración: va como override por idioma
      (`config/sync/language/<lc>/views.view.order_shipments.yml`), igual que los prefijos de
      pathauto. El valor base se queda en inglés y hay override en los cinco idiomas.
    - **Las transiciones no se traducen literalmente**: «Finalize shipment» sería «Finalizar el
      envío», que suena a terminarlo cuando lo que hace es marcar que el paquete está preparado.
      Se traducen por lo que hacen y con el vocabulario de los estados: «Marcar como preparado» →
      «Preparado», «Marcar como enviado» → «Enviado».
    - **«Shipment #1» se queda en inglés a propósito**: no es interfaz, es el TÍTULO del envío que
      escriben los empaquetadores de Commerce y se guarda en la entidad, en el idioma en que
      navegaba quien compró. Traducirla llenaría el backoffice de títulos en cuatro idiomas.
  - **Y el `custom_box` ya no se puede elegir**: `CorreosExpressHooks::escondeElPaqueteDeRelleno()`
    lo quita del campo «Package Type» del envío y del «Default package type» del método de envío.
    **El plugin no se borra, y no es por dejadez**: `PackageTypeManager` no llama a `alterInfo()`,
    así que los tipos de paquete **no tienen hook de alteración**, y
    `ShippingMethodBase::defaultConfiguration()` devuelve `custom_box` a fuego, de modo que sin el
    plugin reventaría la creación de cualquier método de envío. La opción se deja visible cuando es
    el valor ya guardado (el envío 5, expedido con él): quitarla haría que el formulario cambiara
    el valor solo al abrirlo. Se busca por el contenido de `#options` y no por la ruta, porque en
    el envío cuelga de la raíz del formulario y en el método está seis niveles dentro, en la
    configuración del plugin.

- **Numeración de pedidos y facturas (2026-09-02, cliente)**: en el D7 el número visible del pedido
  ERA el número de factura: commerce_billy lo sobreescribía al completarse con el patrón
  `[date:custom:Y]-{id}` y reinicio anual (1530 facturas, 2014-1 … 2026-11 en el dump del 26/07;
  la tienda antigua pudo facturar después, así que **el último número real hay que leerlo del D7
  vivo** antes de tocar producción). Los carritos se quedaban con el `order_id`. Aquí las dos series
  van separadas y la decisión está tomada:
  - **Pedidos: `P-AÑO-NNNN`** (`order_default`, plugin `yearly`, relleno 4, por tienda). Los cuatro
    pedidos que habían salido como «1, 2, 3, 4» se renumeraron con `scripts/renumerar-pedidos.php`
    (P-2026-0001 … 0004), que además deja la fila de `commerce_number_pattern_sequence` en el último
    número dado: sin eso el siguiente pedido repetiría número. Redsys no usa el número de pedido (manda
    el id) y la referencia de Correos Express admite 30 caracteres, así que el prefijo no rompe nada.
    **Los números y la secuencia son contenido**: el script hay que ejecutarlo en producción.
  - **Facturas: `AÑO-N`, la misma serie del D7**, con `commerce_invoice` 2.2 (+ `entity_print` y
    dompdf) como **puente hasta Verifactu**: la autónoma está obligada desde el **01/07/2027** (RD
    1007/2023 aplazado por RDL 15/2025) y la norma cubre también las facturas simplificadas B2C. Un
    Drupal que emita facturas pasa a ser «sistema informático de facturación» (huella encadenada, QR,
    remisión a la AEAT) y no hay módulo Drupal para eso, así que **antes de esa fecha se apaga
    `order_placed_generation` en el tipo de pedido y la factura pasa al programa homologado de la
    gestoría, continuando la misma serie**. La Crea y Crece (RD 238/2026) es solo B2B y no afecta.
  - **La continuidad NO se hace con `initial_number`**: el plugin yearly vuelve a ese número cada
    enero (un 12 daría 2027-12). `scripts/facturas.php -- <último del D7>` **siembra la fila de
    secuencia** de `invoice_default` (number = último del D7, generated = ahora): la siguiente sale
    2026-12 y en enero 2027-1. Con `--generar` factura lo ya vendido sin factura, en orden de compra,
    marcándolas pagadas (por `isPaid()` O estado `completed`: el pedido 4 real no tiene pago
    registrado porque el retorno de Redsys falló con «Bad feedback response» y se colocó a mano) y
    **sin correo al cliente** salvo `--con-correo`. Pendiente del cliente: confirmar que los 4 pedidos
    no se facturaron ya por otra vía antes de lanzarlo en producción.
  - **La factura se genera al realizarse el pedido** (`third_party_settings.commerce_invoice` del tipo
    `default`), el equivalente exacto de la Rule «Invoice order on completion» del D7. Ojo: el módulo
    marca la factura pagada solo si el evento `order.paid` llega DESPUÉS de crearla; con las pasarelas
    de redirección el pago entra antes del `place`, así que las facturas nacen `pending` aunque el
    pedido esté cobrado. Es un estado interno del módulo, no cambia el PDF ni el correo.
  - **Módulo `pronens_factura`**, no el tema, por dos motivos medidos: los `hook_mailer_*` no llegan
    a un tema, y **entity_print renderiza con el tema ACTIVO** (una factura regenerada desde el
    backoffice saldría con la plantilla de fábrica si la nuestra viviera solo en el tema). Así que
    la plantilla `commerce-invoice--default.html.twig` y su preprocess (`FacturaHooks`) van en el
    módulo, registrados con `hook_theme` como sugerencia por bundle; la hoja `css/factura.css` sí es
    del tema, porque entity_print la toma **siempre del tema por defecto** (clave `entity_print` de
    `pronens.info.yml`). Sin `var()` ni fuentes del sitio: dompdf usa DejaVu Sans.
  - **dompdf descarga el CSS por HTTP desde la propia web**, con el host de la petición: desde drush
    el host es «default» y la primera prueba estuvo cinco minutos colgada con la transacción abierta,
    bloqueando la caché de configuración del resto de peticiones. `CssIncrustadoSubscriber` resuelve
    las mismas libraries que entity_print (sin agregar: el agregado se genera bajo demanda y puede no
    existir en disco), las lee del disco y las mete en un `<style>`; el PDF no necesita red. Y
    entity_print **no crea la subcarpeta privada** del tipo de factura (`private://facturas`):
    `CarpetaPdfSubscriber` la prepara en PRE_SEND; sin eso el correo salía sin adjunto.
  - **Ficheros privados**: `private://` no estaba configurado. En ddev va en `settings.local.php`
    (`DRUPAL_ROOT . '/../private'`, carpeta `private/` en la raíz, ignorada en git). **En producción
    hay que crearla fuera del docroot y declararla igual**; sin ella no hay PDF.
  - **El correo de la factura es de Mailer Plus, no el de commerce_invoice**: `FacturaOverride`
    (plugin `Override` con id `pronens_factura`, que tiene que coincidir con el `base_tag` del
    `FacturaMailer`) intercepta `commerce.invoice_confirmation` y manda la política
    `pronens_factura.confirmacion` (asunto y cuerpo editables, envoltorio de la tienda, PDF adjunto,
    idioma del pedido vía `IdiomaPedido`, enlaces de descarga solo si el cliente tiene cuenta). **El
    override está apagado hasta que se enciende en `mailer_override.settings`** (`override.
    pronens_factura: 1`, ya en config/sync): con el plugin solo, el correo seguía saliendo por el
    camino legacy.
  - **NIF opcional en el checkout**: el perfil `customer` ya traía `tax_number` oculto; ahora sale en
    la información de pago como «NIF / CIF», con ayuda «Solo si necesitas factura completa…», en los
    cinco idiomas (overrides de idioma del campo). **Sin verificación VIES** (`verify: false`): un
    NIF de particular no está en el VIES y el checkout habría fallado; sí valida el formato, y ojo,
    Commerce exige el prefijo de país (`ES12345678Z`). Por debajo de 400 € sin NIF vale la factura
    simplificada; con NIF sale completa, y la dirección pinta la provincia por su nombre.
  - **Descarga desde la cuenta**: botón «Descargar factura (PDF)» en la ficha del pedido
    (`CuentaHooks::urlDeFactura`, ruta `entity.commerce_invoice.download`, permiso `view own
    commerce_invoice` añadido al rol autenticado). El acceso lo comprueba el módulo: propia 200,
    ajena 403, anónimo 403. Cadenas en `scripts/traducir-factura.php`.
  - **Datos fiscales del emisor** en el «Texto del pie» del tipo de factura
    (/admin/commerce/config/invoice-types): el D7 decía «c/Alcúdia 199» en la cabecera y «C/Alcúdia
    100» en el pie; **la buena es Alcúdia 100** (cliente, 2026-09-02). Copias previas: snapshots
    `pre-numeracion-pedidos` y `pre-facturas`. Las 4 facturas y los correos de la prueba local se
    borraron. **El último número real del D7 vivo es 2026-20** (cliente, 2026-09-02): la serie local
    está sembrada en el 20 y en producción el script se lanza con `-- 20`, así que la primera
    factura de la tienda nueva será la **2026-21**.
  - **Pendiente de producción, en este orden**: crear la carpeta privada y `file_private_path`;
    `composer install` + `drush cim`; `scripts/renumerar-pedidos.php`; `scripts/facturas.php -- 20`
    (con `--generar` si el cliente lo confirma);
    `scripts/traducir-factura.php`.
  - **Por qué Redsys no registró el pago del pedido 4 (2026-09-02, cliente)**: producción vive
    todavía en la URL temporal `https://tienda-pronens-es.b476.odisean.com/` y el TPV está dado de
    alta para `tienda.pronens.es`. El watchdog lo confirma: el retorno llegó a
    `/checkout/79/payment/return` del dominio temporal a las 18:20:57 **sin los parámetros de
    Redsys** («Bad feedback response, missing feedback parameter») y **no hay ni una entrada de la
    notificación servidor a servidor**, así que no se creó ningún `commerce_payment` y el pedido se
    colocó a mano. `commerce_sermepa` manda `UrlOK`, `UrlKO` y `MerchantURL` construidas con el
    dominio de la petición (`SermepaForm.php`), de modo que **no hay nada que cambiar en Drupal**: al
    pasar al dominio definitivo hay que comprobar en el panel del TPV que las URL de respuesta y
    notificación no estén fijadas a otro dominio, que «parámetros en las URL» esté activado (el
    módulo necesita `Ds_MerchantParameters` en el retorno) y que la notificación llegue a
    `/payment/notify/redsys` (debe aparecer en el watchdog). Sin pago registrado el pedido figura
    sin cobrar y la factura se queda en `pending`.

- **"Producto Personalizado" (185) pasa a ser "Iniciales" (2026-09-03, cliente)**: el término
  llevaba desde la migración con 0 productos y dos enlaces apuntándole (la barra "Personaliza" y el
  pie "Personalización"), además de los dos botones de la home ("Empezar a personalizar" y
  "Personalizar"), que llevaban a una página vacía. Ahora es la puerta de la línea de inicial
  bordada: `scripts/categoria-iniciales.php` renombra el término y el enlace 21 de la barra en los
  5 idiomas (Iniciales / Inicials / Initials / Initiales / Iniziali), y **añade** el 185 a
  `field_tipo_de_producto` de todos los productos de modo `inicial` (9: las 8 sudaderas del 201 y
  la mochila 373). Se resuelve así la pendiente "Personaliza lleva a 0 productos" del menú. Lo que
  conviene no reinventar:
  - **Se añade DETRÁS de la categoría que ya tenían**, no se sustituye: la miga, el patrón de alias
    de pathauto y "También te puede gustar" leen el **primer** término (`termFromField()`), así que
    las sudaderas siguen siendo de "Sudaderas con iniciales" y la mochila de "Mochilas", y ningún
    alias de producto se mueve (verificado el 373, que está en pathauto automático).
  - **Entran los de modo `inicial`, no los personalizables**: el criterio es el campo
    `field_modo_personalizacion`, no una lista de ids, así que al pasar otro producto a *inicial*
    basta relanzar el script (idempotente) o añadirle la categoría a mano.
  - **Pathauto no recorre las traducciones al guardar un término**: `updateEntityAlias()` solo
    regenera el idioma del objeto guardado. El script lo llama traducción a traducción; los 5 alias
    viejos (`/productos/producto-personalizado`…) quedaron como 301 automáticos de `redirect`
    (rids 200-204), y los 4 del D7 con prefijo cruzado (`/ca/productos/producte-personalitzat`,
    rids 126-129) siguen funcionando porque apuntan a la entidad.
  - **La página lee del índice**: sin el `indexItems()` final la categoría seguiría diciendo 0.
  - **Todo es contenido** (término, enlace, productos, alias, redirecciones): el script hay que
    ejecutarlo en producción. Copia previa: snapshot `pre-categoria-iniciales`.
  - **Se deja sin tocar, a decisión del cliente**: la etiqueta del enlace 29 del pie
    ("Personalización" → /productos/iniciales), y la cadena "@count products" sin traducir al
    italiano (la categoría italiana dice "9 products"; afecta a todas las categorías, no a esta).

- **SEO y GEO montados sobre contrib (2026-09-03)**: auditoría con el plugin claude-seo-ai (36/100 en
  búsqueda y 36/100 en visibilidad IA, las dos F) y remedio con módulos de la comunidad más un
  poco de código en `pronens_seo`. Lo que hay ahora y lo que conviene no reinventar:
  - **Metatag con Open Graph y Twitter Cards activados**: el `og_type: product` de la config del
    producto llevaba meses sin salir porque el submódulo no estaba instalado y metatag descarta en
    silencio las etiquetas sin plugin. Tarjeta social con la foto principal del producto, la foto
    del término y el hero de la home, todas por el estilo nuevo `pronens_og` (1200, sin recorte y
    **sin convertir a WebP**, que WhatsApp y Facebook no siempre previsualizan). Campo
    `field_metatag` en producto, categoría y páginas para sobreescribir por entidad.
  - **La meta description la limpia `pronens_seo`** (`Descripcion`, con pruebas): metatag hace
    `strip_tags` a secas y dos párrafos salían pegados ("…la letra.Disponible…"); las de las
    categorías del D7 medían 551 caracteres. Ahora se corta en la última frase que cabe en 160 (o en
    palabra) y el JSON-LD lleva el texto entero con los párrafos separados. Los textos de las
    categorías siguen siendo el copy repetitivo del D7: **reescribirlos es tarea de contenido**.
  - **JSON-LD con schema_metatag**: OnlineStore (con la dirección del Aviso legal, C/ Alcúdia 100,
    08016 Barcelona, y `sameAs` **vacío a propósito**, no hay perfil social enlazado en la web) y
    WebSite con SearchAction a `/buscar?texto=` en todas las páginas; Product en la ficha y
    WebPage+BreadcrumbList en ficha y categoría. **Una Offer por variación con stock real**: los
    tokens de entidad solo llegan a la primera variación, así que `TokenHooks` publica
    `[commerce_product:pronens-ofertas-precio|url|disponibilidad]` como listas separadas por comas
    y el bloque `offers` va con `pivot`, que es lo que schema_metatag convierte en N objetos. La
    disponibilidad sale de `commerce_stock` (lo mismo que mira el botón de comprar). Sin
    AggregateRating: no hay reseñas y no se inventan.
  - **La miga la construye ahora el módulo** (`CatalogoBreadcrumbBuilder`, prioridad 1010 sobre la de
    taxonomy), no el preprocess del tema: con el patrón de alias de tres tramos, core deducía
    "Mochilas" del alias y el tema añadía los ancestros otra vez ("Inicio / Mochilas / Complementos /
    Mochilas / Ficha"). Y el BreadcrumbList del JSON-LD lee el servicio `breadcrumb`, así que con
    la miga en el tema habría publicado otra distinta. El tema solo la pinta (y la quita en el
    checkout).
  - **simple_sitemap 4.2**: productos, `tipo_de_producto` y páginas en los cinco idiomas con
    alternates hreflang dentro (1941 URLs). `skip_untranslated` deja la home solo en `es`, que es lo
    que existe. Los productos basura 260 y 359 van excluidos por instancia mientras sigan
    publicados. Se regenera por cron; a mano, `drush simple-sitemap:generate`.
  - **robots.txt lo sirve el módulo robotstxt** (`web/robots.txt` borrado y excluido del scaffold en
    composer.json). Añade `Disallow` de buscador, cesta, checkout y cuenta, y nombra los bots de IA
    (GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended, CCBot…) con permiso explícito.
    **La línea `Sitemap:` la pone `SeoHooks::robotstxt()`** con el host de la petición: así vale en
    ddev, en la URL temporal y en `tienda.pronens.es` sin tocar nada. El buscador lleva además
    `noindex, follow` por `hook_metatags_alter`.
  - **hreflang 2.0 con `defer_to_content_translation`**: aporta el `x-default` (al castellano) y deja
    a core los alternates de las entidades, que solo emite para las traducciones que existen. Por
    eso **la home sigue con solo `es` + `x-default`: el nodo 5 no está traducido**, y `/ca`, `/fr`…
    enseñan la home en castellano con la interfaz traducida. Traducirla es la tarea SEO pendiente
    de más peso.
  - **llms.txt** (módulo llms_txt, texto en `scripts/seo-base.php`) con las categorías reales y los
    tres datos que se repiten en toda la web (1986, 72 h, 60 €), idénticos al marquee.
  - **GTM `GTM-KQMTNQ9S` con google_tag 2.0**: fuera de `/admin`, `/user` y `/batch`, y con los
    eventos de comercio GA4 en el dataLayer (view_item, add_to_cart, remove_from_cart,
    begin_checkout, add_shipping_info, add_payment_info, purchase, refund, login, sign_up). **GA4 no va
    en Drupal**: se inyecta desde GTM. No había ningún GA anterior que quitar.
  - **Bug del tema corregido**: los `preload` de las fuentes se montaban con `url('<front>')` +
    `directory`, que en los idiomas con prefijo daba `/cathemes/…` (404): en cuatro de cinco idiomas
    las fuentes no se precargaban. Ahora `file_url()`. Y la ficha precarga la foto principal (LCP)
    como ya hacía la home con el hero.
  - **Alt de las fotos** (`scripts/alt-fotos.php`, contenido: ejecutar en producción): 864 medias
    con el nombre del fichero como alt pasan al nombre del producto, categoría, color o fondo que las
    usa (la galería numera a partir de la segunda). Los 144 que quedan no los referencia nadie.
  - **Todo lo de configuración está en `config/sync` y en `scripts/seo-base.php`** (idempotente); lo
    de contenido (alt, sitemap generado) hay que ejecutarlo en producción.
  - **Los literales de envío y devolución dicen lo mismo en toda la tienda (2026-09-03, cliente)**:
    la verdad la fija la configuración de Commerce, no el copy. El método «Envío gratuito desde 60 €»
    (id 7) solo aplica a **España peninsular** (excluye 07, 35, 38, 51 y 52); Baleares (7,95 €),
    Canarias/Ceuta/Melilla (12 €), Portugal (9,95 €) y el resto de la UE (15 €) van con coste, y
    fuera de la UE no se envía. El marquee decía "España, Portugal y UE" y la ficha "España, Francia y
    Portugal. Enviamos a todo el mundo": las dos falsas. Y los plazos son **dos conceptos**: 30 días
    para **iniciar** la devolución (ficha y ahora home, que decía 10) y unos 7 días hábiles para
    recibir el abono (la página de envíos, cuyo texto del D7 sigue hablando de 7 días para pedirla y
    de que "no se realizarán abonos": **pendiente de que el cliente apruebe la redacción nueva**).
    `scripts/politicas-copy.php` (contenido + cadenas: ejecutar en producción) alinea marquee (y le
    añade la traducción italiana que faltaba), beneficios de la home, acordeón de la ficha y llms.txt,
    y pone la **dirección postal en el pie**, la misma de la Organization. Sigue pendiente
    `hasMerchantReturnPolicy`/`shippingDetails` en el JSON-LD: schema_metatag 3.0 no trae esos tipos
    de propiedad, habría que aportar un plugin propio.
  - **La primera fila del catálogo carga eager** (`PrimeraFila`, 2026-09-03): la tarjeta se cachea por
    producto y no sabe en qué posición sale, así que no se decide en ella: un `#post_render` en el
    envoltorio de cada una de las 4 primeras filas de la **primera página** (no en las de «cargar
    más») cambia el `loading="lazy"` de su primera foto por `eager` + `fetchpriority="high"`. El
    envoltorio no lleva `#cache keys`, así que la tarjeta cacheada sigue lazy y no contamina otras
    listas.
  - **Pendiente y de decisión del cliente**: "Rebajas" y el CTA de la home llevan al Outlet vacío (el
    cliente lo irá llenando); el H1 de la home es "Rebajas verano" bajo un eyebrow "AW26"; los
    enlaces "Novedades" del mega menú van a la misma URL que "Ver todo"; el `sameAs` de la
    Organization espera la URL de Google Business (el enlace share.google no se resuelve sin JS);
    "Bordado a mano" cuando el taller borda a máquina; el módulo de reseñas (AggregateRating); y
    traducir la home (el cliente lo hace con los literales ya alineados).
- **Bizum revisado a nivel de código (2026-09-03)**: la pasarela `bizum` es el mismo plugin
  `commerce_sermepa` que la de tarjeta, con los mismos FUC 329583926, terminal 001 y clave
  SHA-256 (verificado que la clave coincide byte a byte con la de `redsys`), y con
  `merchant_paymethods: z`. Simulado el formulario de redirección para un carrito: manda
  `Ds_Merchant_PayMethods: z`, importe en céntimos, moneda 978, `MerchantURL` a
  `/payment/notify/bizum` y las URL OK/KO del checkout, firmado con HMAC_SHA256_V1, que es
  exactamente lo que pide la guía de Bizum de Redsys. El retorno y la notificación se procesan por
  el mismo `processRequest()` que la tarjeta (firma, `Ds_Response` ≤ 99, pago por
  `Ds_AuthorisationCode`), así que si la tarjeta funciona Bizum funciona: **no hay nada que tocar en
  Drupal**, solo activar la pasarela. Lo que sí hay que mirar es el panel de Redsys, que es donde
  falló el pedido P-2026-0004: «Parámetros en las URLs» activado (sin eso el retorno llega sin
  `Ds_MerchantParameters` y el módulo lanza «Bad feedback response») y la notificación HTTP online
  apuntando a la `MerchantURL` que manda el módulo, no a una URL fija de otro dominio.
  - **Parche local a commerce_sermepa** (`patches/commerce_sermepa-onnotify-log.patch`): `onNotify()`
    se tragaba cualquier excepción sin registrarla, así que una notificación que **llegaba y
    fallaba** (firma mala, pedido desconocido, bloqueo) no se distinguía de una que no llegaba. La
    afirmación de que en el pedido 4 «no hay ni una entrada de la notificación» hay que leerla con
    eso en mente. Ahora cada fallo queda en el watchdog con el mensaje y los `Ds_MerchantParameters`.
    Probado con una notificación con firma falsa: entrada `commerce_sermepa` y ningún pago creado.
  - Ojo con `payment_method_types: ["Tarjeta de crédito"]` en las dos pasarelas de Redsys: es una
    etiqueta, no el id `credit_card`. Para pasarelas offsite Commerce no lo usa (el checkout lista
    la pasarela entera), así que no rompe nada; en PayPal el mismo error sí ocultaba la opción.


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
