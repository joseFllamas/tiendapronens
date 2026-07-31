=== Correos Ecommerce ===
Contributors: Correos
Tags: shipping, woo commerce, e-commerce, woo, shop, checkout, downloads, payments, paypal, sales, sell, cart
Requires at least: 5.4.2
Tested up to: 5.8
Stable tag: 1.3.0.6
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Plugin de Correos y Correos Express España para la gestión de envíos. Integra servicios de paquetería nacional e 
internacional, haciendo de la gestión de sus pedidos una tarea rápida y sencilla.

== External services ==

The plugin use correos.es web services to manage the shipping. Only the data necessary to manage a shipment will be sent.

Correos service:
https://www.correos.es

Correos service's terms of use:
https://www.correos.es/ss/Satellite/site/pagina-aviso_legal/sidioma=es_ES

== Installation ==

1. Navigate to _Dashboard – Plugins – Add New_;
2. Search for _Correos Oficial_;
3. Click _Install_, then _Activate_.

== Changelog ==

= 1.9.1 11-02-25 =

* Add - compatibilidad con el módulo de logística
* Fix - error al tener una versión de WooCommerce anterior a la 8.5.3. Reportado en INC000053104225
* Fix - descripción aduanera no se copiaba correctamente en el bulto 2. Reportado en INC000053125717
* Fix - enlace de seguimiento del envío dentro del email venía mal formateado. Reportado en INC000053135993

= 1.9.0 14-01-25 =

- Add - Copia del teléfono del destinatario a <NumeroSMS> según formato y destino
- Add - Eliminación del bloque para agregar datos de contrato, ahora visible solo al pulsar "Nuevo contrato" o "Editar"
- Add - Traslado del campo "empresa" al pre-registro: en Correos, al bloque Destinatario; en CEX, concatenado con el nombre del destinatario
- Add - Comprobación de que la extensión Soap está instalada al añadir las credenciales de Correos

= 1.8.6 22-10-24 = 

* Add - cambio a las consultas a la hora de cargar las tablas en la pestaña de utilidades para mejorar el rendimiento
* Add - indices para las tablas del módulo que disminuyen la tiempos de carga en las consultas
* Fix - tratamiento de los carácteres especiales durante el preregistro. Reportado en INC000052947920 y INC000052965826

= 1.8.5 02-08-24 = 

* Add - animación "en proceso" cuando se apretan los botones de imprimir hasta que se termina de descargar el documento pdf
* Add - control a la hora de añadir un código de usuario ya existente en la base de datos
* Add - botón para eliminar el archivo a subir a la base de datos antes de guardar los ajustes de usuario
* Add - botón para copiar al portapapeles los datos guardados en el buscador de Oficina/CityPaq dentro de un pedido
* Add - confirmación para cancelación de pedidos de Oficina/CityPaq dentro de un pedido
* Fix - Corregido mensaje de error en el apartado devoluciones del pedido no aparece
* Fix - Corregido checkbox desaparecia al cancelar un envio dentro de un pedido
* Fix - Corregido mensaje de error proveniente de webservice no aparece cuando se cancelaba un pedido
* Fix - Corregido Nif Personalizado era requerido cuando el checkbox de "añadir campo NIF en el checkout" estaba desmarcado
* Fix - Corregido subida de logo personalizado distinto al guardado en la base de datos
* Fix - Corregido búsqueda y generación de envío mediante Oficina/CityPaq desde dentro de un pedido
* Fix - Corregido error provocado al intentar cargar pedidos reembolsados en gestion masiva. Reportado en INC000052919275

= 1.8.4 15-07-24 =

* Fix - Corregido redondeo en el campo peso del manifiesto. Reportado en INC000052857281
* Fix - Corregido pérdida de configuración en las reglas de coste cuando se utiliza una clase de envio personalizada. Reportado en INC000052854671
* Fix - Corregido redondeo erróneo del campo "valor neto" dentro de la documentación aduanera. Reportado en INC000052857881
* Fix - Corregido error al cargar la tabla "Resumen pedidos" en utilidades. Reportado en INC000052843328

= 1.8.3 02-07-24 =

* Add - Mejora la obtención del Manifiesto en Utilidades -> Resumen de Pedidos (Filtrado, Formato y Datatable)
* Add - Se cambia el orden de los estados de CRON en Ajustes
* Fix - Corregido comprobacion y uso de código AT para envíos Portugal-Portugal. Reportado en INC000052809081
* Fix - Corregido error al escribir un numero de telefono en el destinario con espacios
* Fix - Corregido ajustes por defecto en seguimiento de estado del pedido al instalar por primera vez el módulo. Reportado en INC000052827113
* Fix - Corregido error de conexión a la base de datos producido al tener un mapeado diferente a utf8
* Fix - Corregido aparicion de nuevos productos internacionales en zonas. Reportado en INC000052834489
* Fix - Corregido enlace duplicado en el email de pedido completado. Reportado en INC000052821316
* Fix - Se corrige conflicto con clase utils. Reportado en INC000052829728
* Fix - Corregido comprobacion y envio de numero de SMS cuando es preregistrado un envio. INC000052827356
* Fix - Eliminada referencia en listaBultos a la hora de hacer un preregistro con producto CEX

= 1.8.2 10-06-24 =

* Add - Se mejoran los filtros de código postal para encontrar CityPaqs
* Fix - Corregido error a la hora de filtrar u ordenar los campos en las tablas de utilidades. Reportado en INC000052517709 y INC000052662183 
* Fix - Corregido error al imprimir etiquetas con formato "Papel etiquetas (Solo CEX)"
* Fix - Desaparece mensaje "touching.. touching.." por defecto. Reportado en INC000052807916
* Fix - Corregido error al intentar generar recogida de una devolucion junto la impresión de etiqueta. Reportado en INC000052785687
* Fix - Se corrige limpieza incorrecta de teléfonos
* Fix - Se corrige error al parsear json que venía con código HTML, debido a errores previos de programación en PHP
* Fix - Se corrige malfuncionamiento de función de transformación de centímetros a metros
* Fix - Se utiliza un único endPoint para las recogidas de las devoluciones CEX. Reportado en INC000052793910

= 1.8.0 15-04-24 =

* Add - compatibilidad con HPOS y checkout por bloques
* Add - licencia de distribución GPL
* Add - paises China y Zimbabue a paq light
* Add - dimensiones por defecto para paq ligero y cityPaq
* Fix - Corregido error en el CRON, según algunos detallables no se identificaba en el servicio localizador. Reportado en INC000052711979 y INC000052725928
* Fix - Corregido error en pedido, no aparecía el histórico en algunas configuraciones de PHP. Reportado en INC000052721053
* Fix - Corregido error en WooCommerce, en algunos clientes no se recogía todos los estados de pedido. Reportado en INC000052661317
* Fix - Corregido error con caracteres portugueses. Reportado en INC000052693141
* Fix - Corregido error en medidas de alto/largo/ancho de CEX. Reportado en INC000052715139
* Fix - Corregido error en el que no actualizaba el estado del envío tras preregistrarlo. Reportado en INC000052715136
* Fix - Corregido error con las medidas de los bultos de CEX. Reportado en INC000052715139 y INC000052715236

= 1.7.0 08-02-24 =

* Add - Se añade funcionalidad multicliente
* Fix - Corregido problema de devoluciones. Reportado en INC000052646278
* Fix - Corregido prolema de guardado de campo de nif personalizado en Ajustes->Configuración de usuario. Reportado en INC000052657481
* Fix - Corregido envios que no aparecen en la pestaña de resumen de pedidos desde utilidades. Reportado en INC000052634733

= 1.6.1 08-02-24 =

* Fix - Corregido problema de error procesando usuario sin registrar. El cliente tenía que hacer click tres veces. Reportado en INC000052587434
* Fix - Corregido problema de fichero de bloque. Reportado en INC000052598993
* Fix - Corregido problema de no detección de carácter 'Ú'
* Fix - Corregido problema que impedía la correcta instanciación de la clase Smarty. Reportado en INC000052592084
* Fix - Corregido problema que ocurría al intentar descargar los registros desde la ventana de ajustes. Reportado en INC000052616476
* Fix - Corregido el estado del checkbox "checked" o "unchecked" al tratarse de un pedido CEX/Correos. Reportado en INC000052614185
* Fix - Corregido un error que se producía al tener productos sin clases al calcular las reglas de costo. Reportado en INC000052602177
* Fix - Corregido un error que impedía la actualización del cron. Reportado en INC000052609766

= 1.6.0 09-01-24 =

* Add - Se implementa control de versiones y sistemas de avisos
* Fix - Corregido problema de pérdida de historial en pedidos. Reportado en INC000052577832

= 1.5.12 05-01-24 =
* Fix - Corregido problema de compatibilidad con woocommerce en el apartado de las reglas de costes a partir de la version 8.4.0. Reportado en INC000052574734

= 1.5.11 29-12-23 =
* Fix - No imprimía. Conflicto de espacio de nombres en plugins que usaban la misma librería setasign/Fpdi. Reportado en INC000052552378
* Fix - No informaba el codigo postal en Oficina/CityPaq en checkout en ciertos temas. Reportado en INC000052544817

= 1.5.1 20-12-23 =
* Fix - Corregido error de duplicación a la hora de hacer impresión etiquetas de envío. Reportado en INC000052552378
* Fix - Resuelto problema de duplicidad de pedidos que se presentaba desde la versión 1.4.2.0. Reportado en INC000052552378
Este problema se manifestaba al pre-registrar un pedido desde el menú de gestión masiva de envíos

= 1.5.0 22-11-23 =
* Fix - Se obtienen las etiquetas a traves de servicio web PS2C en lugar de las tablas
* Fix - Se cambia pdo por wpdb para reducir el nº máx de conexiones de usuario (max_user_connections). Reportado en INC000052492688, INC000052495240, INC000052490221
* Fix - INC000052512699 Cuando hay un remitente es el remitente por defecto 

= 1.4.30 21-11-23 =
* Add - Se añade enlace de seguimiento de Correos y CEX en email de pedido completado
* Add - Se añade bloque en la sección de edición de pedidos del backoffice para poder editar seguimientos

= 1.4.2.0 15-11-2023 =
* Add - Se permite la generación de recogidas, directamente desde la página del pedido mediante check
* Add - Se elimina la opción en CEX de usar el endpoint de grabación de recogidas, unicamente se podrán hacer mediante el check de generar recogida al crear un prerregistro
* Add - En la pestaña de Recogidas, unicamente aparecerán las de Correos
* Add - Se elimina la posibilidad de envios con Paq International Light a paises no incluidos en este servicio, además se incluyen a los existentes, Estados Unidos y Kazajistán

= 1.4.1.1 xx-11-2023 =
- Fix - INC000052467958 No genera correctamente el coste de envío. Se corrige que la clase de envío pueda tener caracteres como tildes un otros
- Fix - INC000052481973 No generaba envío con Paq Light Internacional cuando la zona Ubicaciones no cubiertas por tus otras zonas
- Fix - INC000052485872 Error en carrito de woocommerce Se aplica parche directamente. Se enviaban cabeceras en carrito

= 1.4.1.0 30-10-2023 =
* Add - Nueva columna que muestra los articulos en cada envío de la pestaña utilidades
* Add - Permite filtrar envios según el nombre del artículo

= 1.4.0.0 10-10-2023 =
* Add - Adaptación de código al Market Place de Woocoomerce
* Add - Se añade nuevo producto para recogida en oficina para CEX
* Fix - Se corrige las Clases de Envío en Woocommerce, se admiten palabras separadas por espacio en el nombre de la clase. Reportado en INC000052427256
* Fix - Se ha ajustado el mínimo de carácteres necesarios de 8 a 7 para clientes antiguos. Reportado en INC000052404988

= 1.3.4.0 11-09-2023 =

* Add - Se informa el NIF opcional/obligatorio/personalizado en el proceso de compra
* Add - Se informa el código postal en el buscador en el proceso de checkout cuando se elige transportista Oficina o CityPaq
* Add - Apellido opcional en pedido y utlilidades
* Add - Nuevos nombres de campos en Datos de cliente. Descripción dentro de cada campo
* Add - Tooltips en datos de de clientes en Ajustes
* Add - Validaciones en Datos de clientes en Ajustes y mensajes de error
* Fix - Se corrige conflicto entre themes y script que ocultaba los avisos del web service
* Fix - Se corrige conflicto con el theme Appilo y nuestro plugin. Reportado en INC000052374847
* Fix - Se añade carácteres necesarios para interpretar los datos del formulario del Destinatario en la pestaña de envío. Reportado en INC000052393819

= 1.3.3.0 10-08-2023 =

* Add - Se cambia dispatcher por admin-ajax para evitar bloqueos de plugins de seguridad
* Add - Transportistas Oficina / Citypaq en el checkout. Cargar con búsqueda realizada
* Add - Apellidos opcionales en el formulario Destinatario para pre-registrar
* Add - Nuevos nombres de campos en Datos de cliente. Descripción dentro de cada campo
* Add - Tooltips en datos de cliente en Ajustes
* Add - Validaciones en Datos de cliente en Ajustes y mensajes de error
* Fix - Se disminuye el nº máx de conexiones simultáneas a la base de datos. Reportado en INC000052347955

= 1.3.2.1 01-08-2023 =

* Add -  Se cambian los literales para imprimir etiquetas a Papel 4 etiquetas y Papel 3 etiquetas (Solo CEX)
* Fix -  Corregido error en instalación. No soporte tablas MyiSAm. Reportado en INC000052INC000052303910
* Fix -  Corregido error al preregistrar Paq Light Internacional
* Fix -  Corregido error al preregistrar ePaq24
* Fix -  Actualizados estados de pedido antiguos por estados nuevos de Correos ya que algunos pedidos con estos estados antiguos no se mostraban en la parrilla de pedidos


= 1.3.3.0 develop =

* Add - Transportistas Oficina / Citypaq en el checkout. Cargar con búsqueda realizada
* Add - Apellidos opcionales en el formulario Destinatario para pre-registrar
* Add - Nuevos nombres de campos en Datos de cliente. Descripción dentro de cada campo
* Add - Tooltips en datos de cliente en Ajustes
* Add - Validaciones en Datos de cliente en Ajustes y mensajes de error


= 1.3.2.0 18-07-2023 =

* Add - Mejoras PR00015515, inclusión de impresión de etiquetas formato 3/A4
* Fix - Se corrige el guardado de transportistas en Woocommerce->Ajustes->Métodos de Envío con rango de códigos postales numéricos y alfanuméricos, reportado en INC000052243461


= 1.3.1.2 03-07-2023 =

* Fix - Eliminación de notices a la hora de recuperar estados cuando estos no vienen informados en las devoluciones 
* Fix - Corregido bug de estilos que afectaba a la vista principal de la tienda. Reportado en INC000052289791
* Fix - Corregido error que se producía al recuperar el código iso del país al usar rangos de códigos postales en las regiones. Reportado en INC000052243461
* Fix - Corregido problema que se producía al consultar la tabla correos_oficial_requests mediante el campo id_order. Reportado en INC000052297349


= 1.3.1.1 22-06-2023 =

* Fix - Corregido problema que se producía al recuperar la dirección de oficina/city desde la vista de pedido ya que el hash del carrito de compra causaba problemas con la relación de pedido y dirección de oficina/city elegidas
* Fix - Corregido problema de caracteres en la dirección del destinatario a la hora de preregistrar. Reportado en INC000052272137


= 1.3.1.0 06-06-2023 =

* Add - El peso por defecto (Kg) en Configuración de usuario ahora admite valores con saltos de 100 gramos
* Add - Quitada validación de IBAN
* Add - Quitada obligatoriedad del campo CÓDIGO AT
* Add - Identificación canal pre-registro para envíos desde Pedido y Utilidades (bulto y multibulto) y devoluciones desde Pedido
* Fix - Se escapan caracteres de contraseña antes de enviar el webservice. Solución a INC000052249162


= 1.3.0.6 15-05-2023 = 

* Add - Ampliado juego de caracteres en regex para impedir fallo en preregistro.
* Add - Ejecución del CRON se hace mediante ajax
* Add - Se configura un log de errores del CRON
* Add - Se evita reintentos si el CRON no ha ido correctamente
* Add - Añadida configuración de Smarty para templates compilados
* Fix - Error al cargar los assets de los transportistas de tipo Oficina/CityPaq en el checkout(CarrierExtraContent)
* Fix - Corrección en cambio de estados de pedido de forma automatizada que causaba error con los estados propios de WC.
* Fix - Ampliado número de estados para la automatización de cambios de estados en pedido añadiendo 'wc-cancelled', 'wc-refunded', 'wc-failed'


= 1.3.0.5 18-04-2023 = 

* Fix - Cambios en los nombres de los estados de pedido de Correos y Cex ya que al ser superiores a 20 caracteres algunos daban fallos.
* Fix - Corregido problema que se producía al preregistrar en algunos casos. Solución a INC000052153207
* Fix - Cambiados literal de advertencia de error en el campo teléfono del estinatario en Pedido
* Fix - Cambiados literal de advertencia para el campo IBAN en Configuración de Usuario
* Fix - Movida carga de assets a la función correosoficialOrderMetaBox para evitar la carga de assets fuera de la edición de pedido ya que esto causaba conflicto con el bootstrap de otros módulos. Reportada en INC000052161795
* Fix - Se divide en dos partes la consulta request (office, citypaq) de checkout ya que cuando la tienda tenía muchos registros en la tabla post_meta se ralentizaba la tienda. Reportado en INC000052164036


= 1.3.0.4 24-03-2023 =

* Fix - Corregido error que hacía que las reglas de coste no funcionaran correctamente cuando había más de un artículo en el carrito, ya que no se estaba sumando el total de esos artículos, sino solamente el último. Soluciona INC000052111121
* Fix - Corregido peso total en reglas de coste cuando este era distinto de entero. Corrige INC000052124489
* Fix - Se cambia a curl la llamada al localizador de Correos
* Fix - Se graba el pedido al llamar el cron en Utils por diferencias de versiones de Woocommerce
* Fix - Se filtra en Utilidades los pedidos eliminados por diferencias de verisones de Woocommerce
* Fix - Cambio de estados mediante el CRON. Se corrige error relacionado con el cambio de estados en pedido para pedidos quq ya se encontraban en estado Entregado en Correos. Corrige INC000052096069


= 1.3.0.3 - 07-03-2023 =

* Fix - Corregido fallo de operación con tipos de datos incompatibles cost (null + string) en clase CorreosOficialAddShippingMethod reportado en INC000052062264
* Fix - Controlados pedidos eliminados de la tienda para así no tenerlos en cuenta en la ejecución del CRON
* Fix - Se corrige el problema de la Excepción 15500 $order->id_address_delivery está vacía si se han borrado pedidos de la base de datos
* Fix - Controlamos si no está definida la api de google en configuración para impedir el error de google is not defined en el checkout. Corrige INC000052095063
* Fix - Corregida posición(de horizontal a vertical) de etiqueta térmica para los productos Paq Estándar Internacional y Paq Premium Internacional. Corrige INC000052096090


= 1.3.0.2 - 20-02-2023 =

* Add - Las reglas de coste por precio se aplicarán después de cupones de descuento
* Add - La clasificación de regla de coste “Todos los productos” pasa a llamarse “Pedido con varias clases”
* Fix - Mostramos todos los productos de Correos en selector para registrar desde pedido
* Fix - Aumentada longitud de fila `sender_city` de la tabla senders
* Fix - Cambiado nombre de plugin a Correos Eccommerce
* Fix - Traducción de información de coste en checkout cuando el coste del envío es gratis


= 1.3.0.1 - 08-02-2023 =

* Add - Mejorada gestión en descarga de etiquetas
* Fix - problema al obtener el país del pedido en Datos destinatario, la forma de obtenerlo podía fallar en algunas tiendas como en la que se reporta en INC000052014777
* Fix - problema con tailing comma tras usar método createButtons al generar el formulario de las reglas de coste
* Fix - Incluído código postal 01000 en la tabla de postcodes y no se generaba un error al filtrar por códigos postal


= 1.3.0.0 - 18-01-2023 =

* Add - Compatibilidad con versión PHP8.0


= 1.2.0.4 =

* Fix - Solucionado problema al obtener la zona de envío, al haber zonas con mezcla de rangos de cp y zonas con provincias
* Fix - Se corrige problema con puerto por defecto de la base de datos


= 1.2.0.3 - 10-01-2023 =

* Fix - Se corrige error al guardar productos cuando la tienda tenía configuradas las zonas de envío con códigos postales
* Fix - Se corrige error al guardar remitente
* Fix - Se corrige excepción de la tabla ws_status


= 1.2.0.2 - 02-01-2023 =

* Add - Se recoge el campo dirección complementaria
* Add - Arreglo de Mensaje de advertencia al comprador en Ajustes->Tramitación Aduanera de Envíos
* Add - Mensaje de advertencia al comprador en checkout envíos internacionales
* Add - Corregida la hora de envío de CEX en histórico de Pedidos
* Add - Dirección complementaria se duplicaba en Utilidades
* Add - Se eliminan espacios antes y después de los campos en Ajustes->Remitentes
* Fix - Se corrige error al guardar productos cuando la tienda tenía configuradas las zonas de envío con códigos postales
* Fix - Se soluciona el error this is incompatible with sql_mode=only_full_group_by al prerregistrar en Utilidades.
* Fix - Prefijado estilos CSS en checkout


= 1.2.0.1 - 19-12-2022=

* Add - Arreglos en etiquetas de devolución de Correos
* Add - Se envía nº de pedido en el campo Referencia en la devolución
* Add - Se recoge el campo dirección complementaria


= 1.2.0.0 =

* Add - Se permiten hasta cinco descripciones aduaneras por envío en el pedido
* Add - Se modifica el bloque Remitentes en pedido simplificándolo
* Add - Se cambian nuevos logos
* Add - Añadida funcionalidad del CRON en Configuración de Usuario
* Add - Se añade la descripción del módulo, plataforma y sus versiones en el campo "refCliente" de CEX
* Add - En el campo "Dirección" en "Pedido" se concatena la dirección complementaria y se envía al prerregistro
* Add - Se añade el número de pedido delante de la referencia del pedido en el campo "Referencia" de "Pedido" y en el prerregistro, en el campo "ReferenciaCliente"
* Add - Redefinido bloque devoluciones


= 1.1.0.3 =

* Fix - Corregido error que impedía que se generara correctamente la etiqueta cuando se trataba de un pedido a contra reembolso.
* Fix - Implementado método getSubTotal() para obtener el valor neto del pedido necesario para envíos con aduanas.
* Fix - Añadido tiempo prudencial de 5 segundos para eliminar las etiquetas del directorio temporal tras generarlas. Esto generaba error ya que se eliminaban antes de descargarse.


= 1.1.0.2 - 07-12-2022 =

* Add - Cambiada forma de tratar las rutas en tiendas instaladas en subdirectorios. Ahora se asume que todas las tiendas están instaladas en raíz


= 1.1.0.1 - 10-11-2022 =

* Add - Se cambian los valores que quedaban de prefijo "wp" para que coja el prefijo que haya puesto el cliente para su BBDD


= 1.1.0.0 - 25-10-2022 =

* Add - Cambio de nombre de los productos (Mayusculas-Minúsculas)
* Add - Cambios en el campo "referenciaCliente3"
* Add - Hipervínculo a pedido desde utilidades
* Add - Mostrar y ocultar campos en las tablas de "Utilidades"
* Add - Bloque devoluciones siempre visible - ya no depende del estado del envío. Cambio de lógica con selector de producto de
    devolución: PaqRetorno / Paq24
* Add - Incluidas nuevas descripciones aduaneras
* Add - Modificación bloque "Datos de remitente" en "Pedido" (Enlace para editar remitente desde pedido)


= 1.0.0.1 - 21-10-2022 =

* Add - Cuota fija en Ajustes de transportista
* Add - Manifiesto no lista los códigos largos (los de envío)
* Add - Enlace de seguimiento en la cuenta de comprador
* Add - Anular recogida dentro del pedido
* Add - Paq Estándar Oficina Elegida - Está mal la modalidad entrega
* Add - Envíos multibulto. Peso del bloque aduana no llega al xml
* Add - Mensaje al usuario cuando el webservice no responda
* Add - Textos en Ajustes del transportista
* Add - Comportamiento países y transportistas disponibles
* Add - Pre-registro desde Utilidades no toma valor del pedido
* Add - ImporteSeguro no es integer en el xml
* Add - NIF Destinatario no va al xml de pre-registro
* Add - Dirección duplicada en etiquetas
* Add - Solo se obtiene cn23 del bulto 1 / Botones DCAF DDP
* Add - Envíos internacionales tienen una letra rara en Reimpresión etiquetas
* Add - Precio y peso no vuelcan al bloque Datos Aduana del pedido
* Add - DCAF/DDP una sola vez


= 1.0.0.0 - 15-09-2022 =

* Add - Entrega inicial de Release
