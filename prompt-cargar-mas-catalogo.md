# Cargar más (AJAX) en el catálogo, sobre paginación rastreable

Tarea para Claude Code en la raíz del repo. Lee este documento entero antes de tocar nada.
Manda el CLAUDE.md del repo (reglas de calidad, caché, accesibilidad); esto lo concreta para
esta tarea. Antes de codificar, lee: `config/sync/views.view.catalogo.yml`,
`web/themes/custom/pronens/templates/views/views-view--catalogo.html.twig`,
`views-view-unformatted--catalogo.html.twig`, `templates/navigation/pager.html.twig`,
`js/catalogo.js`, `js/card.js`, `src/Hook/CatalogoHooks.php`, `pronens.libraries.yml` y
`config/sync/metatag.metatag_defaults.taxonomy_term.yml`.

## Objetivo

De cara al usuario, sustituir el paginador numerado del listado de categoría por un botón
"Cargar más" con AJAX que añade el siguiente lote de tarjetas al grid. Por debajo se mantiene
la paginación real del servidor (URLs `?page=N` con enlaces `<a href>` rastreables) y se
corrigen las canónicas. Mejora progresiva estricta: sin JavaScript todo sigue funcionando con
el paginador actual.

Por qué este diseño y no otro:

- Googlebot no pulsa botones ni hace scroll: descubre contenido por enlaces `<a href>`. Un
  "load more" solo de JS dejaría los productos de la página 2 en adelante fuera del rastreo.
  Por eso el botón se monta ENCIMA del paginador real, no en su lugar.
- La investigación de usabilidad en e-commerce (Baymard) sitúa "load more" por delante de la
  paginación clásica (más exploración de catálogo) y del scroll infinito (footer inaccesible,
  escaneo superficial, pérdida de posición al volver).
- Google pide URL única por página y canónica propia en cada página (nunca canonicalizar la
  página 2 hacia la 1). Hoy eso está mal en este repo: ver "Bug de canónicas".

## Estado actual (verificado, no lo redescubras)

- View `catalogo` (`config/sync/views.view.catalogo.yml`): base Search API
  `search_api_index_catalogo`, display `page_1` con path `taxonomy/term/%` (sobreescribe la
  ruta canónica del término). Pager `full`, 24 por página, `quantity: 5`, `use_ajax: false`
  (y debe seguir en false). Los enlaces del pager conservan la query entera (facetas `f[]` y
  orden `sort_by`), así que interceptar el enlace real preserva filtros y orden gratis.
- Plantillas del tema (`web/themes/custom/pronens`):
  - `views-view--catalogo.html.twig`: cabecera (título, recuento `catalogo.total`), barra de
    filtros y `{{ pager }}`.
  - `views-view-unformatted--catalogo.html.twig`: grid `[data-pro-grid]` con celdas
    `div.pro-grid__cell`.
  - `navigation/pager.html.twig`: override genérico del starterkit que usa TODO el sitio. No
    lo especialices salvo necesidad real; el catálogo ya se distingue por CSS bajo
    `.pro-catalogo` y por el JS de su library.
- JS del tema: `js/catalogo.js` (behaviors con `once()`: facetas, toggle 2/4, orden) y
  `js/card.js` (`once('pro-card-cycle', '[data-pro-cycle]')`, el hover-cycle de tarjeta). Las
  tarjetas añadidas por AJAX necesitan `Drupal.attachBehaviors(nodo)` para cobrar vida.
- La library `pronens/catalogo` se adjunta en `CatalogoHooks::preprocessViewsView()`, y esa
  clase ya tiene el patrón para reconocer la ruta (`view_id === 'catalogo'`) y para cargar el
  término del argumento traducido.
- Metatag activo: `metatag.metatag_defaults.taxonomy_term.yml` lleva
  `canonical_url: '[term:url]'`.
- Idiomas: es sin prefijo; ca, fr, en, it con prefijo. Los alias bonitos existen solo en es
  (en el resto la categoría es `/ca/taxonomy/term/TID`, y así enlazan los menús). Cadenas
  nuevas con fuente en inglés y `t()` / `Drupal.t()`, como el resto del tema.
- BigPipe activo. Algunas tarjetas llevan lazy builder (el "+ Añadir" de los productos de una
  variación, con token CSRF): para un usuario autenticado, el HTML que traigas por `fetch()`
  puede llegar con placeholders sin resolver más sus
  `<script type="application/vnd.drupal-ajax" data-big-pipe-replacement-for-placeholder-with-id="...">`
  al final del body. Ver "Detalles delicados".
- Entorno: ddev `tiendapronensd11` (https://tiendapronensd11.ddev.site), Drupal 11.4,
  PHP 8.4. Para probar usa la categoría más poblada, la de las bolsas (74 productos, 4
  páginas de 24; localiza su URL con drush si hace falta).

## Bug de canónicas (arreglarlo es parte de la tarea)

`[term:url]` resuelve a la URL limpia del término, sin query. Consecuencia: la página 2
(`?page=1`) declara como canónica la página 1, así que Google puede dejar de indexar los
productos enlazados desde la 2 en adelante. Además, cada combinación de facetas genera una
URL indexable casi duplicada.

Implanta esta regla SOLO en la ruta de categoría (la view `catalogo`), en un módulo custom
nuevo `pronens_seo` (`web/modules/custom`), con `hook_metatags_alter()`:

| Situación | canonical | robots |
| --- | --- | --- |
| Sin parámetros (o `page=0`) | URL limpia del término | no tocar |
| `?page=N` con N > 0, sin `f[]` ni `sort_by` | URL del término + `?page=N`, absoluta | no tocar |
| Con `f[]` o `sort_by` (haya o no `page`) | URL limpia del término, sin `page` | `noindex, follow` |

Notas:

- `follow` y no `nofollow`: las páginas filtradas deben seguir transmitiendo rastreo hacia
  las fichas.
- La canónica se construye sobre la URL canónica del término EN EL IDIOMA de la página
  (mismo patrón que `CatalogoHooks::terminoDelArgumento()`), absoluta (`setAbsolute()`).
- Recomendado además: en páginas 2+ añade un sufijo al title (por ejemplo "(página N)") para
  no duplicar titles entre páginas.
- La decisión canonical/robots va en una clase de lógica pura con su test unitario (mismo
  patrón que `ExtrasCalculator`): entrada (query params) y salida (canonical relativa +
  robots), sin acoplarla al request.
- hreflang queda fuera del alcance de esta tarea.
- `rel="prev"/"next"` del template actual no estorban (Google los ignora): déjalos.

## Trabajo en el tema (botón Cargar más)

1. En `views-view--catalogo.html.twig`, envuelve el pager:
   `<div class="pro-cargar-mas" data-pro-cargar-mas data-pro-total="{{ catalogo.total }}">{{ pager }}</div>`.
   El wrapper no se reemplaza nunca; lo que se reemplaza en cada carga es el pager de dentro.
2. Nuevo `js/cargar-mas.js` añadido a la library `pronens/catalogo` (súmale
   `core/drupal.announce` a sus dependencias). Vanilla, sin jQuery, behavior con `once()` en
   el wrapper y delegación de eventos, para sobrevivir a los reemplazos del pager sin
   re-bindear.
   - Decoración: el botón visible ES el enlace `.pager__item--next a` restilado (sigue siendo
     un `<a href>` real en el DOM: rastreable y funcional si el JS falla). Texto con
     `Drupal.t('Load more')` y debajo un contador "Has visto 24 de 74" con
     `Drupal.formatPlural` y `data-pro-total`. El pager numerado se queda visible pero
     discreto bajo el botón (el salto directo a una página sigue siendo útil).
   - Click: `preventDefault`, estado `aria-busy` y bloqueo de dobles clics,
     `fetch(href, { credentials: 'same-origin' })`, parseo con `DOMParser`.
   - Resolver placeholders BigPipe del documento traído: por cada
     `script[data-big-pipe-replacement-for-placeholder-with-id]`, parsea su JSON (array de
     comandos AJAX) y aplica los `insert` sobre el
     `span[data-big-pipe-placeholder-id]` correspondiente dentro del doc parseado (escapa el
     id al seleccionar). Hazlo siempre: en anónimo no habrá nada que resolver y es
     idempotente. Si una tarjeta queda con un placeholder sin resolver, no puede entrar rota:
     degrada a navegación normal con `location.assign(href)`.
   - Extrae las celdas `div.pro-grid__cell` del grid traído y añádelas con un
     `DocumentFragment` al grid actual; por cada celda, `Drupal.attachBehaviors(celda)`
     (hover-cycle, chips, "+ Añadir").
   - Reemplaza el pager de dentro del wrapper por el del documento traído y re-decora. En la
     última página no hay enlace siguiente: retira el botón y deja un mensaje de fin ("Has
     visto los 74 productos", `formatPlural`) en una región `aria-live`.
   - `history.replaceState(null, '', href)` tras añadir: la URL refleja la última página
     cargada. replaceState y no pushState: con push, volver atrás obligaría a deshacer lote a
     lote. Volver desde una ficha restaura todo lo cargado vía bfcache; sin bfcache se
     aterriza en `?page=N`, que es una página válida y rastreable.
   - Accesibilidad: `Drupal.announce(Drupal.t('@count more products loaded', ...))`; foco al
     primer enlace de la primera tarjeta nueva (`tabindex="-1"` +
     `focus({ preventScroll: true })`); nada de scroll animado.
   - Errores de red o respuesta no OK: quita el estado busy y deja el enlace en su
     comportamiento nativo (el siguiente clic navega). Sin reintentos silenciosos.
3. CSS en `css/catalogo.css`, BEM y tokens: `.pro-cargar-mas` centrado bajo el grid, botón
   como CTA secundario con contraste AA (tokens `--pro-orange-cta` / `--pro-orange-ink` según
   el caso), estado de carga (spinner CSS o el icono existente), pager numerado reducido
   debajo. Reserva la altura del bloque para que cargar no mueva el footer (CLS < 0.1).
4. Estilo del código como el resto del tema: nombres y comentarios en español, JSDoc breve,
   sin lógica de negocio en Twig.

## Lo que NO hay que hacer

- No actives `use_ajax` en la view ni instales `views_infinite_scroll`: los dos tiran del
  stack AJAX de Views con jQuery (prohibido en este tema) y rompen la mejora progresiva.
- No cargues lotes automáticamente al hacer scroll (IntersectionObserver): es scroll infinito
  con otro nombre, deja el footer inalcanzable y está descartado a propósito. Solo clic.
- No canonicalices páginas 2+ a la 1 (es justo el bug que arreglas) y no muevas la paginación
  a fragmentos `#` (Google los ignora).
- No toques `pager.html.twig` global salvo necesidad demostrada; si de verdad hace falta una
  variante, hazla con `hook_theme_suggestions_pager_alter()` acotada a la view y justifícalo.
- No rompas Page/Dynamic Page Cache ni BigPipe: nada por sesión en el render del catálogo; el
  JS consume las mismas páginas cacheadas que ve cualquier visitante.

## Verificación (definition of done)

Como anónimo y con caché caliente, por curl (sin JS):

- `curl -s "https://tiendapronensd11.ddev.site/<categoria>?page=1"`: la canónica lleva
  `?page=1` y es absoluta; sin parámetro, canónica limpia; páginas 1 y 2 devuelven grids
  distintos y canónicas distintas.
- Con `f[0]=...`: `robots noindex, follow` y canónica limpia. Con `sort_by`: canónica limpia.
- El HTML fuente de cada página contiene los `<a href>` del pager (siguiente y números):
  rastreable sin ejecutar JavaScript.
- `/ca/taxonomy/term/TID?page=1`: mismo comportamiento con textos en catalán (muestreo
  también en fr, en, it).

En navegador, como anónimo Y como autenticado (BigPipe):

- En la categoría de las bolsas: dos cargas seguidas dejan 72 tarjetas; las nuevas tienen
  hover-cycle, chips y "+ Añadir" funcional (añade la línea y abre el flyout también desde
  una tarjeta cargada por AJAX).
- La URL acaba reflejando `?page=N`; entrar en una ficha y volver restaura posición y
  tarjetas cargadas (bfcache).
- Última página: el botón desaparece y queda el mensaje de fin; el pager numerado discreto
  funciona en todo momento; con JS desactivado todo sigue funcionando.
- Cambiar orden o faceta resetea el listado y el botón sigue funcionando con la nueva query.
- Teclado y lector de pantalla: foco a la primera tarjeta nueva, announce audible,
  `aria-busy` durante la carga; sin errores de consola; sin saltos de layout.
- El toggle Vista 2/4 sigue aplicando a las tarjetas añadidas (las columnas son del grid, no
  de la celda; verifica igualmente).

Calidad de código:

- `phpcs`, `phpstan` y `phpunit` verdes, con el test unitario de la lógica de canónicas.
- `drush cex` limpio tras habilitar `pronens_seo` (solo `core.extension` y la config nueva
  del módulo si la hay; la view NO cambia).
- Lighthouse sobre la categoría: Performance 90 o más en mobile, LCP < 2.5s, CLS < 0.1,
  como exige el CLAUDE.md.
- Commits pequeños por bloque (tema, pronens_seo, verificación), mensajes en el estilo del
  repo.

## Referencias

- Google, Pagination best practices:
  https://developers.google.com/search/docs/specialty/ecommerce/pagination-and-incremental-page-loading
- Baymard (via Smashing Magazine), usabilidad de load more, paginación y scroll infinito:
  https://www.smashingmagazine.com/2016/03/pagination-infinite-scrolling-load-more-buttons/
