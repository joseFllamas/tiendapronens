# Pronens: Correos Express

Da de alta expediciones en Correos Express desde el pedido de Drupal, imprime la
etiqueta y sincroniza el seguimiento. El objetivo es que nadie vuelva a teclear
direcciones en el panel del transportista.

No hay módulo contrib de Correos Express para Drupal, ni para 10 ni para 11. Lo
más cercano, `commerce_shipping_carrier`, es alpha y solo genera enlaces de
seguimiento con patrones de URL: no habla con ninguna API.

La especificación de la API no es pública. Todo lo que hace este módulo está
transcrito de la integración oficial de Correos para WooCommerce, que estaba en
`correosoficial/` en la raíz del repo (sobre todo
`classes/Apis/CorreosOficialCEXRest.php` y `install.php`).

## Cómo se usa

1. **Credenciales**: `/admin/commerce/config/correos-express/credenciales`. Son
   tres: código de cliente, usuario y contraseña. Se guardan en `State`, no en
   configuración, así que no viajan en un `drush cex` ni acaban en git, y cada
   entorno tiene las suyas.
2. **Ajustes**: `/admin/commerce/config/correos-express`. Entorno, datos del
   remitente que la tienda no guarda (NIF, contacto, teléfono), formato de
   etiqueta, pesos estimados y sincronización del seguimiento.
3. **Dar de alta un envío**: en el pedido, en la lista de envíos, operación
   «Generar expedición CEX». Viene todo prerrellenado.
4. **Dar de alta varios**: en `/admin/commerce/orders`, se seleccionan pedidos y
   se elige «Generar expediciones de Correos Express» en el desplegable. Lleva a
   una tabla con una fila por envío antes de crear nada.
5. **Etiqueta**: operación «Etiqueta CEX». Con más de un bulto se descarga un ZIP
   con una etiqueta por bulto.
6. **Seguimiento**: se sincroniza por cron. El enlace público aparece solo en la
   ficha del envío.

## El entorno de preproducción está activo por defecto

`entorno: PRE` en la configuración de instalación, a propósito: en producción cada
alta crea un envío real que Correos Express factura, y **la API no permite anular
expediciones**. El informe de estado del sitio avisa de en qué entorno está
mientras no sea producción, y el formulario de alta lo repite.

El entorno con el que se creó cada expedición se guarda en el envío: un número de
preproducción no es real y seis meses después no hay forma de distinguirlo.

## Métodos de envío

Los cinco métodos de zona apuntan al plugin de Correos Express conservando su
identificador, su precio y sus condiciones geográficas. Lo que se gana es que
cada envío sabe con qué producto se despacha, así que el alta sale prerrellenada
y el enlace de seguimiento aparece sin tocar el tema.

| id | Método | Producto de Correos Express | Código |
|---|---|---|---|
| 1 | Envío España peninsular, 5,95 € | Paq 24 | 63 |
| 2 | Envío Islas Baleares, 7,95 € | Paq 24 | 63 |
| 3 | Envío Canarias, Ceuta y Melilla, 12 € | Islas Express | 26 |
| 4 | Envío Portugal, 9,95 € | Paq 24 | 63 |
| 5 | Envío resto de la Unión Europea, 15 € | Internacional Express | 91 |

Los métodos 6 (recoger en tienda) y 7 (envío gratuito desde 60 €) siguen en
tarifa plana: el primero no lo transporta nadie y el segundo es un descuento
comercial, no otro transportista. El tema lee el id 7 para la barra de progreso
del carrito, así que no conviene tocarlo.

**Por qué el método 5 usa Internacional Express (91) y no Internacional Estándar
(90)**: el 90 solo llega a 28 países, y entre ellos no están Chipre ni Malta, que
sí están en las condiciones del método. Con el 90 esos clientes se quedarían sin
opción de envío en el checkout. Conviene confirmar con el comercial cuál de los
dos está contratado; cambiarlo es un desplegable en
`/admin/commerce/config/shipping-methods`.

El botón de expedición **no depende** del método de envío elegido: un pedido con
envío gratuito o con recogida en tienda también se puede despachar por Correos
Express, porque el gratis es un descuento y no otro transportista.

## Los pesos

No había ni un peso en la base de datos: los campos del módulo physical estaban
vacíos en las 1096 variaciones, y el Drupal 7 de origen no tenía ningún campo
métrico, así que no había nada que migrar. Correos Express exige kilos en el
alta, de modo que el peso hay que estimarlo. Tres capas:

1. **El campo de la variación es la verdad.** El formulario de ajustes tiene un
   botón que escribe el peso estimado en las variaciones que no tienen ninguno,
   con vista previa. Nunca sobrescribe un peso existente.
2. **La tara sale del tipo de paquete.** `Shipment::recalculateWeight()` suma el
   peso del `commerce_package_type` al de los artículos, así que el embalaje no
   se estima en ningún sitio: se declara en
   `pronens_correos_express.commerce_package_types.yml`. El único tipo que trae
   contrib mide 1x1x1 milímetros, por debajo del mínimo de 15x10x1 cm de Correos
   Express, así que no servía.
3. **Red de seguridad en ejecución.** `PackerConPesoEstimado` sustituye la única
   línea del empaquetador de Commerce que pone cero gramos cuando la variación no
   tiene peso.

**Lo que hay que pedir al taller**: que pese una unidad de cada uno de los 18
tipos de producto con una balanza de cocina. Media hora y el problema
desaparece. Correos Express factura por peso medido, así que subestimar no
rechaza el envío: genera un recargo en la factura, que es peor porque no se ve.
Ojo con las colchonetas, donde el error no es de gramos sino de kilos.

## Cambios en la tienda que trae este trabajo

- **Teléfono del destinatario**: campo `field_telefono` en el perfil de cliente,
  obligatorio, que se pide **solo en el paso de envío**. Correos Express lo usa
  para el aviso por SMS y para que el repartidor llame. El paso de facturación no
  lo pide: usa otro modo de formulario y Drupal ignora las violaciones de los
  campos que no están en el formulario que valida.
- **`required` de los campos de variación**: `dimensions` y los cinco
  `attribute_*` pasan a opcionales. No era un problema de Correos Express: con
  ellos obligatorios era imposible guardar una variación desde el admin sin
  inventarse un peso, tres dimensiones y hasta tres atributos, porque cada
  producto usa un solo eje y los otros cuatro están estructuralmente vacíos.
- **Unidades del formulario de variación**: gramos y centímetros en lugar de la
  unidad base, que era kilos y metros para una camiseta de 150 gramos.
- **`phpcs.xml.dist` en la raíz**: no existía, así que phpcs corría con PSR-12 y
  fallaba en todo el código del proyecto. Ahora usa el estándar de Drupal.

## Lo que este módulo no hace, y por qué

- **Tarifas desde la API**: no existen. Es categórico: en toda la integración
  oficial no hay una sola llamada que devuelva precios. Cualquier petición de
  «que el precio lo diga Correos Express» se responde con este hecho. Por eso
  `calculateRates()` no cachea nada: no hace red.
- **Anular expediciones**: la API no lo permite. La integración oficial simula la
  anulación borrando su fila local, lo que esconde un envío que el transportista
  va a facturar. Aquí no hay botón, y la interfaz dice que hay que llamar al
  comercial.
- **Puntos de recogida** (Paq 24 Oficina Elegida y PaqPunto): el catálogo y el
  alta ya los soportan, pero el checkout no ofrece el selector. Falta el panel de
  elección, y el listado de oficinas **solo existe en producción**, así que no se
  puede probar en preproducción. El mapa exigiría además infraestructura de
  consentimiento de cookies que el sitio no tiene.
- **Manifiesto**: no hay endpoint, lo genera Correos Express.
- **Gestión de recogidas**: la API solo permite crear una recogida dentro del
  alta, y eso sí está. No hay pantalla de gestión de recogidas.
- **Devoluciones, contrareembolso y seguro**: sin proceso de negocio detrás. La
  tienda cobra por pasarela.
- **Aduanas**: el payload no tiene campos para eso. El formulario avisa cuando el
  destino la requiere (Canarias, Ceuta, Melilla o fuera del ámbito nacional).
- **ZPL**: Correos Express solo devuelve PDF. La etiqueta «térmica» es un PDF del
  tamaño de la etiqueta.
- **PDF fusionado de varias etiquetas**: concatenar PDF no produce un documento
  válido, así que con más de un bulto se entrega un ZIP. Fusionar de verdad
  necesitaría una librería de PDF y solo se justifica si el taller pide imprimir
  de una tirada.

## Pendiente de confirmar con el cliente

1. **Qué productos tiene contratados.** El mapeo de la tabla es razonable, no un
   dato. En particular, Internacional Express (91) frente a Internacional
   Estándar (90) para el método 5.
2. **Datos del remitente**: NIF, contacto y teléfono. La tienda no los guarda.
3. **Pesos reales** de una unidad de cada tipo de producto.
4. **Formato de etiqueta e impresora**: etiqueta suelta o hoja A4 de tres. Si
   quiere su logotipo en la etiqueta.
5. **Recogida diaria concertada o a demanda**, para saber si los campos de
   recogida sobran.
6. **Longitudes máximas exactas** de los campos de texto de la API. Las de
   `Normalizador` son conservadoras y hay que ajustarlas con la especificación
   oficial: truncar tarde produce un rechazo a mitad de un lote de 40 pedidos.
7. **Andorra**: el comentario de la integración oficial dice que se trata como
   nacional pero su código la manda como internacional. Además hoy `AD` no está
   en ningún método de envío, así que un cliente andorrano no puede terminar la
   compra.
8. **Correo de confirmación de envío**: hoy el tipo de envío tiene
   `sendConfirmation: false`, así que el cliente no recibe nada cuando su pedido
   sale. Activarlo con el enlace de seguimiento es mucho valor por poco código,
   pero la plantilla de contrib está en inglés.

## Pruebas

```bash
ddev exec vendor/bin/phpunit web/modules/custom/pronens_correos_express
ddev exec vendor/bin/phpstan analyse
ddev exec vendor/bin/phpcs
```

Los unitarios cubren el cliente HTTP completo con el manejador simulado de
Guzzle, sin credenciales: códigos de error de la API, fallos de red, la política
de reintentos y la conversión de codificación. Los de kernel montan un pedido
real con un doble del cliente.

### Checklist manual contra preproducción

Con las credenciales de preproducción puestas y el entorno en PRE:

- [ ] Un pedido nacional a la península. Comprobar el número de expedición y que
      la etiqueta abre.
- [ ] Un pedido a Canarias. Debe avisar de documentación aduanera.
- [ ] Un pedido a Portugal. El código postal tiene que viajar con cuatro dígitos
      en el campo internacional.
- [ ] Un pedido de tres bultos. Deben venir tres números de bulto y un ZIP con
      tres etiquetas.
- [ ] Un pedido con eñes y acentos en el nombre y la dirección. Es la prueba del
      ida y vuelta de la codificación.
- [ ] Un pedido con una dirección más larga que el límite. Debe truncarse, no
      fallar.
- [ ] Un pedido sin teléfono. El formulario debe avisar y el alta entrar igual.
- [ ] Intentar dar de alta dos veces el mismo envío. Debe rechazarse sin llamar a
      la API.
- [ ] Leer el código de barras de una etiqueta impresa con el lector del taller.
- [ ] Ejecutar el cron y comprobar que el estado del envío se actualiza.
