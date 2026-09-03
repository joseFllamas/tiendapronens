<?php

/**
 * @file
 * Rescata con redirecciones 301 las URLs del Drupal 7 que hoy dan 404.
 *
 * Se ejecuta con `ddev drush php:script scripts/redirecciones-d7.php`.
 *
 * La tienda se puso online conservando los alias migrados, pero no todos,
 * así que había URLs del D7 con tráfico real cayendo en 404. El diagnóstico
 * se hizo con dos fuentes: el export de GA4 de tienda.pronens.es (del 01/06
 * al 02/09 de 2026, en `salida_unificacion/`) y, sobre todo, la tabla
 * `url_alias` del dump del D7, que es el conjunto completo y cubre también
 * los enlaces externos que GA4 no ve. Probadas una a una contra el sitio: de
 * los 707 alias del D7 (sin contar los 1578 de usuario) 367 ya daban 200 y
 * 311 daban 404.
 *
 * Cada 404 se resolvió a su entidad del D11 con los mapas de la migración
 * (`migrate_map_pronens_producto` mapea NID del D7 a product_id del D11, más
 * los de página y taxonomía), y lo que los mapas no cubrían, por dos vías
 * más, porque en el D7 las traducciones eran entidades independientes:
 * - Los nodos de producto traducidos («TABLIER ÉCOLE SAKURA» era un nodo
 *   aparte de «Bata escolar Sakura») COMPARTEN VARIACIÓN con el nodo
 *   castellano migrado: 68 de 68 resueltos así.
 * - Los términos traducidos comparten `i18n_tsid` con el castellano: 37 más.
 *
 * Los destinos son la RUTA INTERNA (`/product/N`, `/taxonomy/term/N`), no el
 * alias, así que siguen valiendo si el alias cambia. Y son la entidad final,
 * no otra redirección: nada de cadenas. Se declaran como ruta y NO como URI:
 * `Redirect::setRedirect()` ya le añade el esquema `internal:`, y pasándole
 * un `internal:/product/18` guardaba `internal:/internal:/product/18`, que
 * redirigía a una ruta inexistente (un 301 a un 404).
 *
 * Las redirecciones son CONTENIDO, no configuración: no viajan en
 * config/sync y este script hay que ejecutarlo también en producción. Es
 * idempotente, así que relanzarlo no duplica ni pisa lo que se haya creado a
 * mano; para mover una hay que borrarla antes en
 * /admin/config/search/redirect.
 *
 * Lo que se deja a propósito en 404 (46 alias, ninguno con visitas): los
 * nodos de producto de prueba o borrados del D7 (`prueba-camiseta`, `bolsa-
 * recambio-*`), las páginas de Commerce Kickstart (`/about`, `/terms-use`,
 * `/403-error`), los banners (no eran páginas) y el `/product-category/` de
 * Kickstart. Y `//contacto/` (2 visitas), que con la doble barra Drupal no
 * normaliza y redirect no puede tomar.
 *
 * Además, 14 filas de la tabla se SALTAN solas, y no es un error: son
 * términos de `escuelas`, `tablas-tallas`, `color_letra` y recomendaciones
 * de lavado cuyo alias sigue vivo en `path_alias` aunque su página dé 404
 * (la view del catálogo se quedó con `entity.taxonomy_term.canonical`).
 * Redirigirlos sería más amable para quien llegue, pero taparía el alias, y
 * en `escuelas` está justo pendiente de decidir si esas páginas listarán los
 * 19 productos de uniforme. Ninguna de las 14 tuvo visitas: las que sí las
 * tuvieron están todas cubiertas.
 *
 * Copia previa: snapshot `pre-redirecciones-d7`.
 */

declare(strict_types=1);

use Drupal\redirect\Entity\Redirect;

/**
 * Las redirecciones: `origen|idioma|destino`, una por línea.
 *
 * El origen va SIN el prefijo de idioma (lo aporta la columna `language` de la
 * redirección) y el idioma es el del alias del D7; `und` vale para cualquiera,
 * que es lo que toca en los alias que el D7 no tenía traducidos.
 *
 * Va como texto y no como array de PHP por los alias del D7, que son largos y
 * llenos de tildes: en un array pasaban de la columna 120 y el comentario con
 * las visitas de cada uno no cabía en la línea.
 */
$tabla = <<<'TABLA'
# --- Productos, alias castellanos del D7 (12). Los de más tráfico del informe:
# la colchoneta naranja (32 visitas) y la marino (28) cambiaron de categoría en
# el alias, y las batas escolares colgaban de una categoría
# («batas-escolares-personalizadas») que la migración no trajo. ---
productos/márfegas-y-accesorios-escolares/colchoneta-plegable-márfega-naranja|es|/product/91   # 32 visitas
productos/colchonetas-márfegas-y-fundas/colchoneta-escolar-plegable-márfega-marino|es|/product/267   # 28 visitas
productos/batas-escolares-personalizadas/bata-escolar-sakura|es|/product/20   # 12 visitas
productos/batas-escolares-personalizadas/bata-escolar-pirata|es|/product/83   # 7 visitas
productos/batas-escolares-personalizadas/bata-escolar-princesa|es|/product/116   # 7 visitas
productos/batas-escolares-personalizadas/bata-escolar-caperucita|es|/product/78   # 6 visitas
productos/batas-escolares-personalizadas/bata-escolar-tigre|es|/product/134   # 2 visitas
productos/official-merch-mikoshin-saga-ede-minmore/bolsa-tote-ede-minmore-bag-dragon-ball-mikoshin|es|/product/366   # 2 visitas
productos/batas-escolares-personalizadas/bata-escolar-superheroe|es|/product/86   # 1 visitas
productos/batas-escolares-personalizadas/bata-escolar-zombie|es|/product/18
productos/camiseta-oversize-de-colección-ede-minmore-mikoshin-saga-broly-episode-11|es|/product/360
productos/cojín-dragon-ball-mikoshin-saga-broly-fan-art-ede-minmore|es|/product/364

# --- Productos traducidos (67). En el D7 cada idioma era un NODO distinto con
# su propio alias; aquí las traducciones son del mismo producto, así que los 67
# apuntan al producto del D11 que comparte variación con el nodo castellano.
# Ojo al prefijo: el D7 dejaba «productos» en los cuatro idiomas. ---
productos/alfombreta-de-ratolí-dragon-ball-súper-mikoshin-saga-broly-fan-art-ede-minmore|ca|/product/365
productos/bavoirs-bébé/bavoir-bébé-5-little-monkeys|fr|/product/188
productos/bavoirs-bébé/bavoir-bébé-baby-shark|fr|/product/162
productos/bavoirs-bébé/bavoir-bébé-ballerine|fr|/product/164
productos/bavoirs-bébé/bavoir-bébé-hibou-indien|fr|/product/160
productos/bavoirs-bébé/bavoir-bébé-lapin-indien|fr|/product/158
productos/bavoirs-bébé/bavoir-bébé-lapin-noir|fr|/product/190
productos/bavoirs-bébé/bavoir-bébé-lion|fr|/product/189
productos/bavoirs-bébé/bavoir-bébé-ours-indien|fr|/product/157
productos/bavoirs-bébé/bavoir-bébé-ours-polaire|fr|/product/161
productos/bavoirs-bébé/bavoir-bébé-renard-indien|fr|/product/159
productos/bodies-naissance/body-bebé-amor-infinito|fr|/product/61
productos/bodies-naissance/body-bebé-smoking|fr|/product/76
productos/bodies-naissance/body-bébé-darth-vader|fr|/product/45
productos/bodies-naissance/body-bébé-godfather|fr|/product/48
productos/bodies-naissance/body-bébé-rouge|fr|/product/54
productos/bossa-cotó-tote-bag-dragon-ball-mikoshin-saga-broly-fan-art-ede-minmore|ca|/product/363
productos/broly-cushion-pop-culture|en|/product/95
productos/broly-mikoshin-saga-episode-11-limited-edition-oversize-sweatshirt-collection-ede-minmore|en|/product/362
productos/broly-mikoshin-saga-épisode-11-collection-de-sweat-shirts-oversize-en-édition-limitée-ede|fr|/product/362
productos/coixí-broly-pop-culture|ca|/product/95
productos/coixí-dragon-ball-mikoshin-saga-broly-fan-art-ede-minmore|ca|/product/364
productos/coixí-goku-pop-culture|ca|/product/106
productos/coixí-kakashi-pop-culture|ca|/product/105
productos/coixí-naruto-pop-culture|ca|/product/104
productos/collection-limited-edition-oversize-hoodie-broly-mikoshin-saga-episode-11-ede-minmore|en|/product/361
productos/collection-limited-edition-t-shirt-oversize-broly-mikoshin-saga-episode-11-ede-minmore|en|/product/360
productos/collection-sweat-à-capuche-oversize-en-édition-limitée-broly-mikoshin-saga-épisode-11-ede|fr|/product/361
productos/collection-t-shirt-oversize-edition-limitée-broly-mikoshin-saga-épisode-11-ede-minmore|fr|/product/360
productos/col·lecció-dessuadora-oversize-amb-caputxa-edició-limitada-broly-mikoshin-saga-episode-11|ca|/product/361
productos/col·lecció-dessuadora-oversize-edició-limitada-broly-mikoshin-saga-episode-11-ede-minmore|ca|/product/362
productos/col·lecció-samarreta-oversize-edició-limitada-broly-mikoshin-saga-episode-11-ede-minmore|ca|/product/360
productos/cotton-tote-bag-dragon-ball-mikoshin-saga-broly-fan-art-ede-minmore|en|/product/363
productos/coussin-broly-pop-culture|fr|/product/95
productos/coussin-dragon-ball-mikoshin-saga-broly-fan-art-par-ede-minmore|fr|/product/364
productos/coussin-goku-pop-culture|fr|/product/106
productos/coussin-kakashi-pop-culture|fr|/product/105
productos/coussin-naruto-pop-culture|fr|/product/104
productos/goku-cushion-pop-culture|en|/product/106
productos/kakashi-cushion-pop-culture|en|/product/105
productos/mouse-pad-dragon-ball-super-mikoshin-saga-broly-fan-art-ede-minmore|en|/product/365
productos/naruto-cushion-pop-culture|en|/product/104
productos/sac-tote-bag-en-coton-dragon-ball-mikoshin-saga-broly-fan-art-par-ede-minmore|fr|/product/363
productos/tabliers-décole-avec-boutons/tablier-école-alice|fr|/product/81
productos/tabliers-décole-avec-boutons/tablier-école-aloha|fr|/product/90
productos/tabliers-décole-avec-boutons/tablier-école-camaleon|fr|/product/136
productos/tabliers-décole-avec-boutons/tablier-école-chats|fr|/product/135
productos/tabliers-décole-avec-boutons/tablier-école-cupcakes|fr|/product/17
productos/tabliers-décole-avec-boutons/tablier-école-dragon|fr|/product/79
productos/tabliers-décole-avec-boutons/tablier-école-elsa|fr|/product/85
productos/tabliers-décole-avec-boutons/tablier-école-flamingo|fr|/product/137
productos/tabliers-décole-avec-boutons/tablier-école-fée|fr|/product/114
productos/tabliers-décole-avec-boutons/tablier-école-galaxies|fr|/product/84
productos/tabliers-décole-avec-boutons/tablier-école-les-attraper|fr|/product/92
productos/tabliers-décole-avec-boutons/tablier-école-magicien|fr|/product/113
productos/tabliers-décole-avec-boutons/tablier-école-monster|fr|/product/82
productos/tabliers-décole-avec-boutons/tablier-école-ninja|fr|/product/115
productos/tabliers-décole-avec-boutons/tablier-école-panda|fr|/product/19
productos/tabliers-décole-avec-boutons/tablier-école-petit-chaperon|fr|/product/78
productos/tabliers-décole-avec-boutons/tablier-école-pirate|fr|/product/83
productos/tabliers-décole-avec-boutons/tablier-école-princesse|fr|/product/116
productos/tabliers-décole-avec-boutons/tablier-école-sakura|fr|/product/20
productos/tabliers-décole-avec-boutons/tablier-école-sirène|fr|/product/16
productos/tabliers-décole-avec-boutons/tablier-école-super-heros|fr|/product/86
productos/tabliers-décole-avec-boutons/tablier-école-tigre|fr|/product/134
productos/tabliers-décole-avec-boutons/tablier-école-zombie|fr|/product/18
productos/tapis-de-souris-dragon-ball-mikoshin-saga-broly-fan-art-par-ede-minmore|fr|/product/365

# --- Categorías traducidas (37). Mismo motivo: en el D7 eran términos
# independientes unidos por `i18n_tsid`, y la migración solo trajo el castellano
# (las 47 filas «ignoradas» del mapa de taxonomía). Aquí sí cambia el prefijo:
# el D11 sirve las categorías en /ca/productes/, /en/products/, /fr/produits/. ---
productos/school-smocks-buttons|en|/taxonomy/term/176   # 1 visitas
productos/accessoires-pour-école-maternelle|fr|/taxonomy/term/187
productos/accessories-nursery-school|en|/taxonomy/term/187
productos/baby-accessories|en|/taxonomy/term/178
productos/baby-bodys|en|/taxonomy/term/177
productos/bandanas|ca|/taxonomy/term/203
productos/bates-amb-botons|ca|/taxonomy/term/176
productos/bates-amb-goma|ca|/taxonomy/term/175
productos/bates-escolars|ca|/taxonomy/term/189
productos/bavoirs-bébé|fr|/taxonomy/term/180
productos/bavoirs-et-accessoires-bébé|fr|/taxonomy/term/178
productos/bibs|en|/taxonomy/term/180
productos/bodies-naissance|fr|/taxonomy/term/177
productos/bodys-nadó|ca|/taxonomy/term/177
productos/bosses-escola-infantil|ca|/taxonomy/term/182
productos/coussins-ludiques|fr|/taxonomy/term/181
productos/decoració-llar|ca|/taxonomy/term/186
productos/décoration-de-la-maison|fr|/taxonomy/term/186
productos/educational-toys|en|/taxonomy/term/188
productos/funny-pillows|en|/taxonomy/term/181
productos/home-decoration|en|/taxonomy/term/186
productos/jocs-educatius|ca|/taxonomy/term/188
productos/jouets-éducatifs|fr|/taxonomy/term/188
productos/kids-fashion|en|/taxonomy/term/190
productos/lits-et-cots|fr|/taxonomy/term/193
productos/masques-hygiéniques-lavables-0|fr|/taxonomy/term/194
productos/merch-officiel-mikoshin-saga-par-ede-minmore|fr|/taxonomy/term/202
productos/moda-i-mascaretes|ca|/taxonomy/term/190
productos/mode-et-masques|fr|/taxonomy/term/190
productos/nursery-bags|en|/taxonomy/term/182
productos/official-merch-mikoshin-saga-ede-minmore|ca|/taxonomy/term/202
productos/pitets|ca|/taxonomy/term/180
productos/pitets-nadó-i-complements|ca|/taxonomy/term/178
productos/sacs-ecole-maternelle|fr|/taxonomy/term/182
productos/sacs-à-dos|fr|/taxonomy/term/179
productos/school-smocks|en|/taxonomy/term/189
productos/school-smocks-rubber|en|/taxonomy/term/175

# --- Casos resueltos a mano (17). El primero es el 404 más visitado de todos:
# al unificar «Batas guardería» dentro de «Batas Babis Escolares» se creó la
# redirección con el slug transliterado (`batas-guarderia`) y el alias real del
# D7 llevaba tilde. Los webforms de contacto van al formulario nuevo, y las siete
# rutas `node/N` son visitas en francés a la ruta interna, sin alias, que el mapa
# de migración sí sabe resolver. Ojo con esas siete: ocupan rutas de nodo, y si
# algún día existiera el nodo 117 la redirección ganaría sobre él (el subscriber
# de redirect actúa antes del enrutado). El D11 tiene 7 nodos, así que hay
# margen, pero conviene saberlo. ---
productos/batas-guardería|es|/taxonomy/term/176   # 33 visitas
productos/márfegas-y-accesorios-escolars/marfega-colchoneta-plegable|es|/taxonomy/term/193   # 11 visitas
contacto|es|/contact   # 4 visitas
node/117|fr|/product/81   # 3 visitas
vuelta-al-cole|es|/taxonomy/term/176   # 3 visitas
contacto-ventas|es|/contact   # 2 visitas
node/119|fr|/product/83   # 2 visitas
node/118|fr|/product/82   # 1 visitas
node/120|fr|/product/84   # 1 visitas
node/121|fr|/product/85   # 1 visitas
node/124|fr|/product/86   # 1 visitas
node/131|fr|/product/87   # 1 visitas
productos/contacto-ventas|es|/contact   # 1 visitas
productos/contact|fr|/contact
productos/contact-vous-souhaitez-vendre-dans-votre-point-de-vente-pronens|fr|/contact
productos/¿quieres-vender-en-tu-tienda-nuestros-productos|es|/contact
productos/¿quieres-vender-tus-uniformes-en-nuestra-tienda-online|es|/contact

# --- Vocabularios de atributo al catálogo (147). Color, talla, tamaño,
# recomendaciones de lavado, color_letra, tablas de tallas y escuelas: en el D11
# esas páginas de término dan 404 a propósito, porque nunca listaron nada (la
# view del catálogo lista productos y estos vocabularios no los clasifican).
# Entre las 147 sumaban UNA visita en tres meses, así que esto es solo para que
# quien llegue por un enlace viejo vea productos en vez de un error. Destino
# /buscar, la única pantalla que lista el catálogo entero; lleva
# `noindex, follow`, de modo que no transfieren autoridad al índice. ---
color-letra/perfil-blanco-interior-marino|und|/buscar
color-letra/perfil-blanco-interior-negro|und|/buscar
color-letra/perfil-blanco-interior-rojo|und|/buscar
color-letra/perfil-blanco-interior-turquesa|und|/buscar
color-letra/perfil-fucsia-interior-blanco|und|/buscar
color-letra/perfil-marino-interior-blanco|und|/buscar
color-letra/perfil-negro-interior-blanco|und|/buscar
color-letra/perfil-negro-interior-rojo|und|/buscar
color-letra/rosa|und|/buscar
color/amarillo|und|/buscar
color/arena|ca|/buscar
color/azul-celeste|und|/buscar
color/azul-ducados|und|/buscar
color/azul-royal|und|/buscar
color/azzure|en|/buscar
color/bkue-ducados|en|/buscar
color/black|en|/buscar
color/blanc|ca|/buscar
color/blanco|und|/buscar
color/blau-cel|ca|/buscar
color/blau-ducados|ca|/buscar
color/blau-marí|ca|/buscar
color/blau-royal|ca|/buscar
color/celeste|und|/buscar
color/garnet|en|/buscar
color/grana|ca|/buscar
color/granate|und|/buscar
color/groc|ca|/buscar
color/jaune|fr|/buscar
color/kelly-green|en|/buscar
color/lavanda|und|/buscar
color/lavanda-0|ca|/buscar
color/lilac|en|/buscar
color/maduixa|ca|/buscar
color/marino|und|/buscar
color/morado|und|/buscar
color/morat|ca|/buscar
color/naranja|und|/buscar
color/navy|en|/buscar
color/negre|ca|/buscar
color/negro|und|/buscar
color/orange|en|/buscar
color/piedra|und|/buscar
color/pink|en|/buscar
color/red|en|/buscar
color/red-strawberry|en|/buscar
color/rojo|und|/buscar
color/rojo-fresa|und|/buscar
color/rosa|und|/buscar
color/rosa-0|ca|/buscar
color/royal-blue|en|/buscar
color/sand|en|/buscar
color/sky-blue|en|/buscar
color/taronja|ca|/buscar
color/turquesa|und|/buscar
color/turquesa-0|ca|/buscar
color/turquoise|en|/buscar
color/verd-benetton|ca|/buscar
color/verde-benneton|und|/buscar
color/verde-billar|und|/buscar
color/verde-petróleo|und|/buscar
color/verde-pistacho|und|/buscar
color/vermell|ca|/buscar
color/white|en|/buscar
color/yellow|en|/buscar
escuelas/uniformes-de-colegio-goar|es|/buscar
escuelas/uniformes-de-escuela-salesians|und|/buscar
productos/complements-lescola-infantil|ca|/buscar
productos/tablier-décole-élastiques|fr|/buscar
productos/tabliers-décole-avec-boutons|fr|/buscar
productos/tabliers-décole-et-robes-imperméables-adultes|fr|/buscar
recomendaciones-de-lavado/do-not-use-chlorine-based-bleach|en|/buscar
recomendaciones-de-lavado/hot-iron|en|/buscar
recomendaciones-de-lavado/lavage-en-machine-à-30°|fr|/buscar
recomendaciones-de-lavado/lavar-máquina-30º|es|/buscar
recomendaciones-de-lavado/machine-wash-30º|en|/buscar
recomendaciones-de-lavado/ne-pas-blanchir|fr|/buscar
recomendaciones-de-lavado/no-blanquear|und|/buscar
recomendaciones-de-lavado/no-blanquear-0|ca|/buscar
recomendaciones-de-lavado/rentat-màquina-30º|ca|/buscar
recomendaciones-de-lavado/repassage-température-inférieure-à-200°-c|fr|/buscar
recomendaciones-de-lavado/secadora-temperatura-baixa|ca|/buscar
recomendaciones-de-lavado/secadora-temperatura-baja|und|/buscar
recomendaciones-de-lavado/séchage-au-sèche-linge-à-température-modérée|fr|/buscar
recomendaciones-de-lavado/temperatura-de-planchado-inferior-200°-c|es|/buscar
recomendaciones-de-lavado/temperatura-de-planxa-inferior-200-°-c|ca|/buscar
recomendaciones-de-lavado/tumble-dry-low-heat|en|/buscar
tablas-tallas/tallas-babi|es|/buscar
tablas-tallas/tallas-babi-fr|fr|/buscar
tablas-tallas/tallas-cortas|es|/buscar
tablas-tallas/tallas-cortas-fr|fr|/buscar
tablas-tallas/tallas-largas|es|/buscar
tablas-tallas/tallas-largas-fr|fr|/buscar
tablas-tallas/tallas-sudaderas|es|/buscar
talla/0-0-1-years|und|/buscar
talla/0-6-años|und|/buscar
talla/00-8-months|und|/buscar
talla/000-6-months|und|/buscar
talla/10-9-10-years|und|/buscar
talla/12-11-12-years|und|/buscar
talla/14-13-14-years|und|/buscar
talla/16-xs|und|/buscar
talla/18-small|und|/buscar
talla/2-2-3-years|und|/buscar
talla/20-medium|und|/buscar
talla/20-x-30cm|und|/buscar
talla/22-large|und|/buscar
talla/24-xl|und|/buscar
talla/26-xxl|und|/buscar
talla/32x45-cm|und|/buscar
talla/4-3-4-years|und|/buscar
talla/40-x-40cm|und|/buscar
talla/50x70-cm|und|/buscar
talla/6-12-años|und|/buscar
talla/6-5-6-years|und|/buscar
talla/8-7-8-years|und|/buscar
talla/adulto-12-años-12-x-18-cm|und|/buscar
talla/cojín-cushion|und|/buscar
talla/funda-cojin-cushion-cover-only|und|/buscar
talla/grande-38x42-cm|und|/buscar
talla/infantil-large-9-12-años-85-x-17-cm|und|/buscar
talla/infantil-medium-6-9-años-6-x-15-cm|und|/buscar
talla/pequeño-28x32-cm|und|/buscar
tamaño/12m|es|/buscar
tamaño/12m|ca|/buscar
tamaño/12m|en|/buscar
tamaño/12m|fr|/buscar
tamaño/18m|es|/buscar
tamaño/18m|fr|/buscar
tamaño/18m|ca|/buscar
tamaño/3m|es|/buscar
tamaño/3m|ca|/buscar
tamaño/3m|fr|/buscar
tamaño/3m|en|/buscar
tamaño/6m|es|/buscar
tamaño/6m|ca|/buscar
tamaño/6m|en|/buscar
tamaño/6m|fr|/buscar
tamaño/9m|es|/buscar
tamaño/9m|ca|/buscar
tamaño/9m|en|/buscar
tamaño/9m|fr|/buscar
tamaño/grande-37x42cm-muda|es|/buscar
tamaño/manta|und|/buscar
tamaño/manta-ajustable|und|/buscar
tamaño/mini-14x14cm-chupetera|es|/buscar
tamaño/pequeño-25x28cm-almuerzo|es|/buscar
TABLA;

$repositorio = \Drupal::service('redirect.repository');
$alias = \Drupal::service('path_alias.repository');
$gestor = \Drupal::entityTypeManager();

/**
 * Comprueba que el destino existe antes de apuntar a él.
 */
$destinoValido = static function (string $ruta) use ($gestor): bool {
  // Las rutas que no son de entidad (/contact, /buscar) las valida el router.
  if (!preg_match('#^/(product|taxonomy/term|node)/(\d+)$#', $ruta, $coincidencias)) {
    return TRUE;
  }
  $tipos = [
    'product' => 'commerce_product',
    'taxonomy/term' => 'taxonomy_term',
    'node' => 'node',
  ];

  return $gestor->getStorage($tipos[$coincidencias[1]])->load($coincidencias[2]) !== NULL;
};

$creadas = 0;
$existentes = 0;
$saltadas = 0;
$total = 0;

foreach (explode("\n", $tabla) as $linea) {
  $linea = trim(preg_replace('/#.*$/', '', $linea) ?? '');
  if ($linea === '') {
    continue;
  }
  [$origen, $idioma, $destino] = explode('|', $linea);
  $total++;

  // Nunca tapar una página viva: el subscriber de redirect actúa ANTES del
  // enrutado, así que una redirección sobre un alias existente lo esconde.
  // Hoy ninguno de los orígenes es un alias del D11, pero esto tiene que seguir
  // siendo seguro si el script se ejecuta más adelante o en otro entorno.
  if ($alias->lookupByAlias('/' . $origen, $idioma === 'und' ? 'es' : $idioma) !== NULL) {
    printf("  SALTADA  /%s [%s]: ya es un alias del sitio\n", $origen, $idioma);
    $saltadas++;
    continue;
  }
  if (!$destinoValido($destino)) {
    printf("  SALTADA  /%s [%s]: el destino %s no existe\n", $origen, $idioma, $destino);
    $saltadas++;
    continue;
  }
  if ($repositorio->findMatchingRedirect($origen, [], $idioma) !== NULL) {
    $existentes++;
    continue;
  }

  $redireccion = Redirect::create();
  $redireccion->setSource($origen);
  $redireccion->setRedirect($destino);
  $redireccion->setStatusCode(301);
  $redireccion->setLanguage($idioma);
  $redireccion->save();
  printf("  creada   /%s [%s] -> %s\n", $origen, $idioma, $destino);
  $creadas++;
}

printf("\nListo: %d creadas, %d ya existían, %d saltadas (de %d).\n",
  $creadas, $existentes, $saltadas, $total);
