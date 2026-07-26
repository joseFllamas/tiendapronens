# CLAUDE.md — Rediseño tienda Pronens (Drupal 11 + Commerce 3)

Eres el desarrollador front/back de este proyecto Drupal. Tu fuente de verdad visual es
`design/Tienda Pronens.dc.html` (ábrelo en un navegador; la barra superior cambia entre Home,
Categoría y Ficha). El detalle completo de pantallas, tokens y comportamiento está en
`design/README.md`. **Recrea el diseño en el tema — no copies el HTML del prototipo.**

## Contexto del repo
- Drupal 11 (`web/` como docroot), Commerce 3 + PayPal + Sermepa + Shipping + Stock ya en composer.
- No hay tema custom todavía: créalo en `web/themes/custom/pronens` (starterkit de core).
- Idiomas: ES (por defecto), CA, FR, EN — módulos multilingües de core.

## Orden de trabajo
1. **Tema `pronens`**: tokens CSS (custom properties con los colores/tipos del README), fuentes
   self-hosted WOFF2 (Archivo, Nunito Sans, Caveat), layout base, header sticky + marquee + footer.
2. **Estructura de contenido**: `composer require drupal/paragraphs drupal/facets drupal/search_api
   drupal/commerce_cart_flyout drupal/pathauto drupal/metatag`. Paragraphs de la Home; catálogo
   (vocabulario), tipo de producto `prenda` con atributos color/talla y galería media; campos de
   personalización en el order item (`field_nombre_bordado`, `field_color_hilo`).
3. **Home**: templates Twig por paragraph, una library CSS/JS por componente.
4. **Categoría**: View + facets + toggle Vista 2/4 (JS del tema, localStorage) + tarjeta de producto
   (componente compartido con hover-cycle de galería y selección color/talla en tarjeta).
5. **Ficha**: galería grid 3:4, add-to-cart con variaciones, personalizador con vista previa en vivo
   (Caveat + color de hilo), order processor del +5 €.
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
