# Programa · Atribución educoland → tienda Pronens (UTM + pedido marcado)

> **Origen (2026-09-11)**: Jose pregunta si sería complicado añadir UTM que GA4 recoja en la tienda
> (origen ficha/listado/panel, canal educoland.com) y si conviene un sistema de afiliación en la tienda
> para que los pedidos que vengan de educoland queden marcados **usen cupón o no**. La tienda es ahora
> un **Drupal 11 propio** (`/Users/josellamas/var/www/tiendapronensd11`: Commerce 3, `google_tag`,
> `klaro`, `commerce_promotion`, módulos `pronens_*`), así que el bucle que ADR-046 fase 04 no pudo
> cerrar ("nadie recibe los parámetros") ya se puede cerrar. Addendum ADR-046 (2026-09-11).

## Decisión (opinión de producto/marketing, aceptada)

Las dos cosas, con papeles distintos, y **ninguna resucita la cookie de afiliación de educoland**:

| Capa | Qué mide | Para quién | Límite |
|---|---|---|---|
| **UTM en `/out`** (educoland, HECHO) | Sesiones, embudo y compras en el GA4 de la tienda por canal/colocación/creatividad | Marketing: qué colocación y qué imagen convierten; comparar con otros canales | GA4 no es un libro mayor: pierde a quien rechaza analítica (Consent Mode = modelado), muestrea, retiene 14 meses. Sirve para optimizar, no para liquidar |
| **Pedido marcado en la tienda** (Pronens D11, PENDIENTE) | Cada pedido colocado con `referral = educoland` + campaña + método (`cookie`, `cupon`, ambos) | Negocio: base fiable del convenio (comisión, renegociación), independiente del cupón | Necesita consentimiento para la cookie; sin él, queda el cupón como red |
| **Cupón** (ya existía) | Ventas con código educoland | Fallback sin consentimiento y palanca comercial | Solo mide a quien lo usa: infraestima B2B (presupuesto), compras sin descuento y olvidos |

Por qué no "solo UTM": la conversación comercial con Pronens necesita un número que no dependa del
banner de cookies de la tienda ni del modelado de Google. Por qué no "solo cupón": ya lo teníamos y
deja fuera justo los pedidos que el cliente quiere ver marcados. Por qué **no** una cookie en educoland:
ePrivacy exige consentimiento para almacenar en el dispositivo y ya se retiró una cookie "para nada"
(fase 04); la cookie va donde se consume, en la tienda, bajo **su** Klaro.

## Contrato de parámetros (congelado; lo emite `OutController::conUtm()`)

| Parámetro | Valor | Ejemplos |
|---|---|---|
| `utm_source` | `educoland` (fijo) | |
| `utm_medium` | `referral` (fijo) | |
| `utm_campaign` | la **colocación** = parte del origen antes de `:` (**sin el id**) | `ficha_publica`, `ficha_familia`, `ficha_profesional`, `landing_tipo`, `dashboard_familia`, `dashboard_centro`, `perfil_educadora`, `lead_familia`, `email_candidatura`, `email_resena`, `email_lead_centro`, `email_alta`, `email_oferta`, `desconocido` |
| `utm_content` | `banner<b>` o `banner<b>-s<slide>` — solo si el clic viene de una campaña válida | `banner12`, `banner12-s31183` |

Reglas: se **conserva la query propia** del deep-link (una categoría con filtros); si el destino es solo
el host se fuerza la ruta `/`; **jamás** viaja `o=`, el uid/nid, `eco_origen` ni cookie alguna. Los
destinos posibles siguen saliendo de config o de la whitelist de hosts (`tienda.pronens.com`,
`www.pronens.com`). Los cupones que la tienda debe reconocer como "educoland" son los de
`/admin/config/services/pronens` (rotatorios: p. ej. `EDUCOFAM10`), los públicos de producto de los
banners (`EDUBABY10`, `EDUCOLE10`…) y el B2B derivado `EDUCO-<código de centro de 8 dígitos>`.

## Lado educoland (hecho el 2026-09-11)

- `OutController::conUtm()` + `resolverBanner()` (la campaña se registra en `meta.banner` aunque no
  traiga URL propia). Tests `OutControllerTest`, `OutDeepLinkTest`, `OutPublicoTest`; smoke
  «Salida /out/pronens con UTM y sin identificadores».
- Legales (`scripts/model/18-paginas-legales.php`: `/cookies`, `/privacidad`, `/aviso-legal`) pasan de
  "no añaden parámetros de seguimiento" a "no instalan cookies; llevan UTM de campaña sin datos
  personales; el tratamiento en la tienda es de Pronens". Re-ejecutar el script en prod.
- Docs: `docs/tracking.md` (§Salida a Pronens, punto 3), `docs/cookies-rgpd.md`, CLAUDE.md.

## Lado tienda (pendiente; prompt listo en [`prompt-tienda.md`](prompt-tienda.md))

Resumen: módulo mínimo `pronens_referral` (tras evaluar contrib) — captura JS de los UTM de educoland en
cookie first-party `pronens_ref` **solo tras consentimiento Klaro** (app/purpose propio), copia al pedido
al colocarlo (`field_ref_*`, método `cookie` / `cupon` / `cookie+cupon`), detección de cupones educoland,
informe con totales y CSV en la administración de Commerce, textos legales de la tienda. Nada de PII hacia
educoland; nada de Set-Cookie en servidor (rompería la caché de página).

## Qué NO se hace

- Cookie o `sessionStorage` en educoland (ePrivacy, y ya se retiró una).
- Identificadores del visitante hacia Pronens (ni uid, ni nid, ni email).
- Cambiar el modelo de cupones ni el gating de los rotatorios.
