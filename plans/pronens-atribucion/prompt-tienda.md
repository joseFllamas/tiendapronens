# Prompt para la sesión de Claude Code en `tiendapronensd11`

Copiar tal cual (ajusta lo que esté entre corchetes si hace falta).

---

Vamos a implementar la **atribución de pedidos que llegan desde educoland.com** (nuestro partner, un
directorio de centros educativos que recomienda la tienda). Educoland ya está hecho: cada enlace hacia
la tienda pasa por su redirección `/out/pronens` y llega aquí con **UTM estándar**, sin cookies ni
identificadores personales. Contrato congelado (no lo cambies; si necesitas otro dato, dímelo):

- `utm_source=educoland` y `utm_medium=referral` (fijos).
- `utm_campaign=<colocación>`: `ficha_publica`, `ficha_familia`, `ficha_profesional`, `landing_tipo`,
  `dashboard_familia`, `dashboard_centro`, `perfil_educadora`, `lead_familia`, `email_candidatura`,
  `email_resena`, `email_lead_centro`, `email_alta`, `email_oferta`, `desconocido`. Trátala como texto
  libre corto (≤ 64) por si aparecen nuevas.
- `utm_content=banner<id>` o `banner<id>-s<id>` (campaña e imagen del banner; opcional).
- La URL puede traer además la query propia de una categoría o producto. Nunca trae `o=`, uid ni email.
- Cupones que cuentan como educoland: prefijo `EDUCO-` (B2B por centro) y una lista configurable de
  códigos (hoy `EDUCOFAM10`, `EDUBABY10`, `EDUCOLE10`; los rotan desde educoland, la lista debe ser
  editable por UI).

Objetivo: **que cada pedido colocado quede marcado como procedente de educoland, use cupón o no**, con
la campaña y la creatividad, y que tengamos un informe mensual fiable para el convenio. GA4 recibirá los
UTM solo: no hay que hacer nada en Drupal para eso salvo comprobar que el contenedor GTM ya dispara el
evento `purchase` de comercio electrónico con Consent Mode; si no lo hace, dímelo antes de tocarlo.

Restricciones:
1. **Contrib-first**: antes de escribir código, evalúa en drupal.org si hay un módulo mantenido y
   compatible con Drupal 11 + Commerce 3 que persista UTM/referral en el pedido (busca "utm", "referral",
   "affiliate", "commerce attribution"). Si encaja al menos al 80 %, úsalo y altéralo; si no, módulo
   custom mínimo `pronens_referral` y justifica en su README por qué.
2. **Consentimiento**: la cookie de atribución es first-party de la tienda, la escribe **JavaScript** y
   **solo después** de que Klaro tenga consentimiento para un app/purpose nuevo «Atribución de partners
   (educoland)» (categoría analítica o marketing, la que ya uséis para GA). Sin consentimiento **no se
   escribe nada** y la atribución cae al cupón. **Nunca `Set-Cookie` desde PHP en páginas normales**: la
   página cacheada la compartirían todos los visitantes.
3. **Nada de datos personales hacia educoland**: no llamar a educoland, no enviar emails ni ids; la
   atribución se queda en la tienda.
4. No tocar precios, promociones ni el checkout visible. No cambiar el bundle `default` de pedido.

Implementación que quiero (adáptala al contrib si lo hay):

- **Captura (JS, library global)**: si `location.search` trae `utm_source=educoland`, guardar en la
  cookie `pronens_ref` un JSON `{s:"educoland", c:<utm_campaign>, ct:<utm_content|null>, t:<unix>}`;
  90 días, `Path=/`, `SameSite=Lax`, `Secure`; **last-click** (cada nueva llegada desde educoland
  sobrescribe). Sanea valores (`[a-z0-9_-]`, ≤ 64). Si Klaro deniega el purpose más tarde, borra la
  cookie (callback de Klaro).
- **Campos en `commerce_order` (bundle `default`)**: `field_ref_source` (string 32),
  `field_ref_campaign` (string 64), `field_ref_content` (string 64), `field_ref_method` (lista:
  `cookie`, `cupon`, `cookie+cupon`), `field_ref_seen` (timestamp de la llegada). Ocultos en todos los
  displays de cliente; visibles en el pedido de administración (sección propia "Procedencia").
- **Copia al pedido**: event subscriber en `commerce_order.place.post_transition` (y de forma
  idempotente en presave si el estado ya es `completed`/`fulfillment` y los campos están vacíos):
  lee la cookie de la petición actual; si `s === "educoland"` rellena los campos y método `cookie`.
  Independientemente, mira los cupones aplicados al pedido (`$order->get('coupons')`): si alguno es
  educoland (prefijo `EDUCO-` o la lista configurable), método `cupon`; si ambos, `cookie+cupon` y
  `field_ref_source = educoland` aunque no hubiera cookie. Sin cookie ni cupón: no toques el pedido.
- **Config** (`/admin/commerce/config/pronens-referral`): lista de códigos educoland, prefijo B2B,
  días de vida de la cookie, interruptor general.
- **Informe** en `/admin/commerce/reports/educoland` (View + `views_data_export` para CSV): pedidos
  colocados con `field_ref_source = educoland`, filtros por mes, método y campaña; totales (nº pedidos,
  suma de `total_price`) con agregación; permiso `access commerce reports` o el que uséis para informes.
  Añade una fila resumen por mes.
- **Legales de la tienda**: describir la cookie `pronens_ref` (finalidad: recordar que llegaste desde
  educoland para atribuir tu pedido a esa colaboración; 90 días; solo con tu consentimiento) en la
  política de cookies y en la descripción del app de Klaro.
- **Tests**: kernel para el subscriber (cookie → campos; cupón → método; ambos; ninguno = intacto) y
  para el saneado de valores; un test JS mínimo del behavior si el proyecto ya tiene infraestructura
  JS, si no, descríbeme cómo lo has probado a mano.

Entrega: código + config exportada + README del módulo (contrato de parámetros de arriba, decisiones,
cómo leer el informe) + lista de comprobaciones manuales (llegar con
`?utm_source=educoland&utm_medium=referral&utm_campaign=ficha_publica&utm_content=banner1-s2`, aceptar
Klaro, comprar con y sin cupón, ver el pedido y el informe). No hagas commit sin que yo lo pida.

---

## Notas para Jose (fuera del prompt)

- En GA4 de la tienda los UTM aparecen como *Session source/medium* = `educoland / referral`,
  *Session campaign* = la colocación y *Session manual ad content* = la creatividad. Si quieres
  atribución por clic en la tienda con ventana propia, es la cookie de arriba, no GA4.
- Cuando la tienda esté, el KPI de educoland (`clic_pronens` con `meta.banner`/`meta.slide`) y el
  informe de la tienda casan por campaña (`banner<id>`): clics vs pedidos por creatividad.
- Pendiente en educoland cuando roten los cupones: mantener la lista de códigos de la tienda al día
  (o pasar a un prefijo común tipo `EDUCO` para no depender de la lista).
