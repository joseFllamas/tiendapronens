# Pronens: atribución de partners (educoland)

Marca cada pedido que llega desde **educoland.com** —el directorio de centros
educativos con el que hay convenio— con su colocación y su creatividad, **use
cupón o no**, y saca un informe mensual con el que liquidar la colaboración.

GA4 ya recibe los UTM por su cuenta (el contenedor de GTM tiene activo el evento
`commerce_purchase` de `google_tag`, comprobado), pero GA4 no sirve para
liquidar: pierde a quien rechaza la analítica, modela, muestrea y retiene 14
meses. Esto es el libro mayor.

## Por qué es un módulo propio y no un contrib

Se buscó en drupal.org («utm», «attribution», «referral», «affiliate»,
«commerce attribution») antes de escribir nada. Lo que hay:

- **[persistent_visitor_parameters]** (1.0.1, D10/D11, cubierto por el equipo de
  seguridad, unos 14 sitios) es lo más cercano: guarda parámetros GET en una
  cookie. Pero la escribe **desde PHP**, que es justo lo que aquí no se puede
  hacer —un `Set-Cookie` en una página cacheada se lo lleva el siguiente
  visitante—, no tiene gancho de consentimiento, y no toca Commerce: ni campos
  en el pedido, ni cupones, ni informe. Cubriría un 20 % del encargo, y la
  parte que cubre la hace de la forma que aquí está descartada.
- **commerce_cookie_condition** es para condicionar promociones y **no está
  cubierto por la política de seguridad**: no entra en la ruta de compra.
- `link_param_propagator`, `utm_source` (filtro), `webform_utm` y
  `smart_content_utm` resuelven otras cosas.

Lo que sí es contrib es el CSV: **views_data_export** 1.10 (arrastra
`csv_serialization`, `rest` y `serialization`, todos del core o cubiertos).

[persistent_visitor_parameters]: https://www.drupal.org/project/persistent_visitor_parameters

## Contrato de parámetros (lo emite educoland, no se toca desde aquí)

| Parámetro | Valor |
|---|---|
| `utm_source` | `educoland` (fijo; configurable en la tienda por si mañana hay otro partner) |
| `utm_medium` | `referral` (fijo; no se usa para decidir nada) |
| `utm_campaign` | la **colocación**: `ficha_publica`, `ficha_familia`, `ficha_profesional`, `landing_tipo`, `dashboard_familia`, `dashboard_centro`, `perfil_educadora`, `lead_familia`, `email_candidatura`, `email_resena`, `email_lead_centro`, `email_alta`, `email_oferta`, `desconocido`. Texto libre corto: se admite cualquiera que aparezca |
| `utm_content` | la **creatividad**: `banner<id>` o `banner<id>-s<id>`. Opcional |

La URL puede traer además la query propia de una categoría o de un producto.
**Nunca** llega un identificador de persona (ni uid, ni correo, ni `o=`), y
desde aquí **no se llama a educoland** ni se le manda nada.

Cupones que cuentan como del partner: el prefijo `EDUCO-` (B2B por centro) y la
lista de códigos públicos, que educoland rota. Los dos se editan en
`/admin/commerce/config/pronens-referral`.

## Cómo funciona

1. **Captura (navegador)**. `js/referral.js` mira `location.search`; si trae el
   `utm_source` del partner escribe la cookie propia `pronens_ref` con
   `{s, c, ct, t}`: 90 días, `Path=/`, `SameSite=Lax`, `Secure`. **Last-click**:
   cada llegada nueva pisa la anterior y reinicia la ventana. Los valores se
   sanean a `[a-z0-9_-]` y 64 caracteres.
2. **Consentimiento**. Ese JS **no se llama solo**: lo llama el servicio
   «Atribución de partners (educoland)» de Klaro (finalidad *Analítica*), desde
   su `callback_code`, que corre en cada página con el consentimiento ya
   resuelto y cada vez que cambia. Sin aceptar no se escribe nada; al rechazar
   se borra la cookie (y Klaro la borra también por su cuenta, porque está
   declarada en el servicio). El servicio lo crea
   `scripts/atribucion-educoland.php`.
3. **Copia al pedido**, en dos momentos:
   - al **entrar algo en el carrito** (`CART_ENTITY_ADD`), que es cuando seguro
     que hay una petición del navegador del cliente con su cookie;
   - al **colocarse el pedido** (`commerce_order.place.pre_transition`), donde
     la cookie de esa petición manda (last-click), se miran los cupones y se
     fija el método.
   Sin cookie y sin cupón el pedido **no se toca**.
4. **Red de seguridad**: `hook_commerce_order_presave` rellena un pedido ya
   colocado al que le falte la marca, y **nunca pisa** lo que ya hay.

Por qué la captura entra ya en el carrito: hay pedidos que se colocan **sin el
navegador del cliente delante**. En esta tienda ha pasado —el P-2026-0004 se
colocó a mano desde el backoffice días después, porque el retorno de Redsys
falló—, y ahí la única cookie es la del administrador.

## Campos del pedido (bundle `default`)

| Campo | Tipo | Qué guarda |
|---|---|---|
| `field_ref_source` | string 32 | el partner (`educoland`) |
| `field_ref_campaign` | string 64 | la colocación (`utm_campaign`) |
| `field_ref_content` | string 64 | la creatividad (`utm_content`) |
| `field_ref_method` | lista | `cookie`, `cupon` o `cookie+cupon` |
| `field_ref_seen` | timestamp | cuándo hizo clic el visitante |

Están **ocultos en todos los displays**, también en el del cliente y en el del
recibo. En `/admin/commerce/orders/N` sale en su lugar el bloque
**«Procedencia»**, que los junta y traduce el método; se pinta solo en el modo
de vista `default`, que es el del backoffice.

**Invariante**: `field_ref_seen` lo escribe únicamente la cookie, así que es la
señal de que hubo cookie. Un pedido reconocido solo por el cupón lo tiene vacío.

Los campos son del módulo (dependencia forzada), así que **desinstalarlo los
borra con sus datos**: si algún día se desinstala, exporta antes el informe.

## El informe

`/admin/commerce/reports/educoland` (menú de Commerce, permiso *access
commerce_order overview*, el mismo que ya tiene la logística):

- **Resumen por mes** encima: pedidos y facturación, con los filtros aplicados.
  El mes es una columna de la consulta (`MesDeCompra`, una expresión SQL con el
  desfase horario del sitio): sin ella la agregación de Views agruparía por la
  marca de tiempo, o sea un grupo por pedido.
- **Detalle** debajo, con filtros de fecha, método y colocación.
- **CSV** con `;` y BOM, que es lo que abre Excel de una vez.

Dos avisos de lectura:

- El filtro de fecha es un rango y **necesita las dos casillas**: con una sola
  Views no filtra (así funciona el filtro de fecha del core).
- La columna «Facturado» es un `SUM` y por tanto ha perdido la moneda: se pinta
  con el símbolo € a mano. La tienda vende solo en euros; si algún día no fuera
  así, habría que agrupar también por moneda.

## Configuración

`/admin/commerce/config/pronens-referral`: interruptor general, `utm_source`
que se reconoce, días de la ventana, prefijo de los cupones B2B y lista de
códigos. Apagar el interruptor deja de escribir la cookie y de marcar pedidos
nuevos; lo ya marcado se queda.

## Legales

La cookie está descrita en la política de cookies de la tienda y en la
descripción del servicio de Klaro, en los cinco idiomas. Las dos cosas las
escribe `scripts/atribucion-educoland.php` (el texto completo de la página vive
además en `scripts/cookies-klaro.php`, que es quien la reescribe entera).

## Pruebas

```
ddev exec vendor/bin/phpunit --testsuite unit   --filter AtribucionTest
ddev exec vendor/bin/phpunit --testsuite kernel --filter AtribuidorDePedidosTest
ddev exec node --test web/modules/custom/pronens_referral/tests/js/referral.test.mjs
```

- **Unit** (`Atribucion`): saneado, reconocimiento de cupones, método y lectura
  de la cookie, incluida la manipulada, la caducada y la de otra fuente.
- **Kernel** (`AtribuidorDePedidos`): cookie → campos; cupón → método; las dos;
  ninguna (el pedido no se toca); interruptor apagado; last-click; lo capturado
  en el carrito sobreviviendo a un `place` sin cookie; y un pedido de verdad
  colocado con la transición.
- **JS**: la captura con el navegador fingido, con el ejecutor de pruebas que
  trae Node (el proyecto no tiene infraestructura JS).

## Despliegue

1. `composer install` y `drush cim` (trae el módulo, los campos y el informe).
   Repite el `cim` si el alta del módulo disparó la importación de traducciones
   que pisa configuración, como está documentado en el CLAUDE.md del proyecto.
2. `ddev drush php:script scripts/atribucion-educoland.php` — **es contenido y
   configuración de idioma: hay que ejecutarlo en producción**. Crea el
   servicio de Klaro en los cinco idiomas, añade el apartado a la política de
   cookies y traduce el enlace del CSV.
3. Comprueba en `/admin/commerce/config/pronens-referral` que los cupones
   siguen siendo los que educoland está repartiendo.
