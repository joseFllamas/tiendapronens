# Brief de redacción: descripciones de producto de la tienda Pronens

Este documento fija cómo se escribe cada descripción. Es la única fuente de reglas para
quien redacte, sea persona o agente. La guía del cliente (`guia-contenidos.md`) está
incorporada aquí, y además se han añadido los hechos verificados de cada categoría para que
nadie tenga que inventar nada.

## 1. Por qué se reescriben

La tienda se desindexó en marzo de 2026 sin un motivo técnico. Lo que hay hoy en las fichas
es el molde del Drupal 7: 74 bolsas con el mismo bloque de 12 frases y solo el nombre del
motivo cambiado, 41 bodys idénticos salvo una línea, 38 cojines iguales, párrafos enteros
copiados de una enciclopedia sobre el cuento o el animal del estampado, y en todos los
textos la composición, el lavado, los plazos de entrega y el envío gratis, que la ficha ya
enseña en su sitio. Para un buscador eso es contenido duplicado y de relleno. La reescritura
tiene que dejar en cada ficha un texto que solo valga para ese producto.

## 2. Dónde va cada cosa en la ficha (lo que NO se escribe en la descripción)

La ficha de producto ya pinta, fuera de la descripción:

- **Eyebrow** bajo el título: categoría · composición (por ejemplo "Bolsas y sacos ·
  100% Poliéster impermeable").
- **Selector de variación**: todas las tallas, medidas, piezas y colores disponibles.
- **Desglose de precio**: precio y recargo del bordado ("14,52 € + 5,00 € bordado").
- **Lista de confianza**: "Envío gratis desde 60 €", "Bordado en 72 h", "Devolución en
  30 días".
- **Desplegable "Lavado y cuidados"**: temperatura de lavado, lejía, secadora, plancha.
- **Desplegable "Guía de tallas"**: la tabla de medidas.
- **Desplegable "Envíos y devoluciones"**: destinos, umbral de envío gratis, devoluciones.

Por tanto la descripción **NO incluye**:

- Porcentajes de fibra ni gramajes ("50% poliéster", "290 g/m²"). Sí se puede hablar del
  material en palabras cuando es un argumento de venta: "microfibra impermeable",
  "algodón orgánico peinado", "rizo de algodón de doble capa", "tejido vichí".
- Instrucciones de lavado, planchado, secadora o lejía, ni temperaturas. Excepción: cuando
  la reutilización ES el producto (mascarillas, prendas sanitarias) se puede decir una vez
  "aguanta 25 lavados" sin más instrucciones.
- Plazos de entrega, "24/48 h", "3-5 días", "72 h", envío gratuito, "60 €", devoluciones.
- Precios, "+5 €", "gratis", "sin coste", descuentos.
- La lista completa de tallas o medidas. Sí se puede decir la horquilla de edad en palabras
  ("de 3 a 18 meses", "de 4 a 10 años") y para qué sirve cada medida cuando aporta ("la
  chupetera para el chupete, la de almuerzo para el bocadillo y la fruta, la de muda para la
  ropa de recambio").

## 2b. Reparto entre la página de categoría y la ficha de producto

La página de cada categoría ("Baberos bebé", "Bolsas y sacos"…) pinta la descripción del
término como texto de introducción bajo el H1, y esa descripción es también su meta
description. **Ahí es donde viven los rasgos que comparten todos los productos de la
categoría**: el material y cómo se comporta, el tipo de cierre, las certificaciones, las
medidas o tallas que existen, para qué edades sirve y los usos típicos. Ese texto lo
escribe el redactor jefe aparte (`categorias-es.json`).

La ficha de producto, en cambio, se queda con **lo que solo vale para ese producto**:

- el diseño concreto (qué se ve en la foto, colores de la tela, del ribete, del cordón);
- el ángulo de venta elegido para ese modelo (una situación real, un beneficio);
- **dos o tres rasgos compartidos como mucho**, los que encajan con el ángulo, contados
  con palabras propias (no la lista completa de la categoría, y nunca la ristra de
  certificados: si el ángulo lo pide, una sola, por ejemplo "sin BPA" en un babero que
  se va a morder);
- la personalización, según el modo del producto.

Si al releer una ficha el texto valdría igual para el modelo de al lado cambiando el
nombre del motivo, está mal: es texto de categoría y hay que quitarlo o hacerlo propio.

## 3. Lo que sí es la descripción

Texto de venta e información propia de ESE producto, escrito para un padre o una madre que
compra desde el móvil, y cuya primera frase será también la meta description de Google.

### Estructura obligatoria

1. **Primer párrafo** (50-80 palabras, 100% propio de este producto). Su **primera frase
   mide entre 90 y 155 caracteres** y funciona sola: dice qué producto es, qué diseño o
   motivo lleva y para quién o para qué sirve. Esa frase es lo que sale en Google (la
   ficha recorta la meta description en la última frase que cabe en 160 caracteres), así
   que no puede empezar con "Este", "Esta", "Nuestro" ni depender de nada anterior. Nada
   de "Original X de Pronens" ni "Comprar X".
2. **Dos a cuatro bloques cortos**: párrafos de una a tres frases (máximo 3-4 líneas en
   móvil), o UNA lista `<ul>` de tres o cuatro `<li>` que empiecen con la característica en
   `<strong>`. Rota la forma: unos textos con lista, otros solo con párrafos, alguno con un
   `<h3>` breve ("El diseño", "Cómo se usa", "Con su nombre bordado"). Que dos textos
   seguidos de la misma categoría no tengan la misma estructura.
3. **Cierre de personalización** si el producto lo admite (ver punto 5), con redacción
   distinta en cada texto. Sin llamada a la acción ("¡Consíguelo!", "No lo pienses más").

### Longitud

- Productos normales: **110-190 palabras**.
- Uniformes escolares (GOAR, Salesians, chándales sin categoría): **50-100 palabras**.
- Packs sanitarios y merch: 110-170.

### Estilo

- Tuteo, cercano y concreto. Frases cortas. Una idea por frase.
- **Cero paja**: si una frase no informa de algo de ESTE producto o no ayuda a decidir, se
  borra. Fuera las frases genéricas del tipo "sus bonitos diseños son una forma divertida y
  colorida de…", "complemento perfecto para cualquier habitación", "tus amigos te
  preguntarán dónde lo has conseguido".
- **Negritas** (`<strong>`) en 2-4 rasgos clave por texto: "cierre de velcro ajustable",
  "nombre bordado", "interior impermeable". Nunca frases enteras en negrita.
- Máximo **una exclamación** por texto, y mejor ninguna. Sin emojis. Sin puntos
  suspensivos.
- Sin mayúsculas gritadas: la marca es "Pronens", no "PRONENS".
- Sinónimos naturales, sin repetir la misma palabra clave en cada frase: babi / bata
  escolar / mandilón; bolsa / saco / bolsita; guardería / escuela infantil / cole / P3;
  peques / niños y niñas / tu hijo o tu hija; muda / ropa de recambio; almuerzo / merienda /
  bocadillo.
- Palabras y muletillas **prohibidas**: sumérgete, descubre, imprescindible, must have,
  el compañero perfecto/ideal, único y especial, de la mejor calidad del mercado, máxima
  calidad, alta calidad (sin más), no lo pienses más, consigue hoy mismo, "hecho a mano"
  (el bordado es a máquina y la confección no está verificada como manual), "bordado a
  mano", "bordado gratis", "bordado incluido" (salvo en modo inicial, ver punto 5), COVID,
  pandemia, cansinovirus, reapertura, fechas de preventa o de envío.
- **Personajes y marcas**: usa el nombre del motivo tal y como aparece en el título del
  producto y nada más. No añadas franquicias, estudios ni marcas que no estén en el título
  (nada de Disney, Marvel, Nintendo, Nickelodeon, Peppa Pig si el título dice "Pepa", etc.)
  y no afirmes que es producto oficial o con licencia. Describe la ilustración por lo que
  se ve: "un guerrero con capa roja y martillo", "una princesa de trenza larguísima".
  Excepción: la categoría Official Merch Mikoshin Saga sí es merchandising oficial del
  artista Ede Minmore; ahí "Dragon Ball" va en los títulos y se puede nombrar.
- **Describe el diseño mirando la foto** (cada producto trae la ruta de su miniatura):
  color de fondo o de la tela, qué se ve en el estampado, colores del ribete, botones o
  cordón. No inventes lo que no se ve.
- **Responde una duda real** cuando encaje, sin fingir un FAQ: "¿Cabe la sudadera y el
  pantalón de recambio? En la medida grande sí, con los calcetines y el calzado."

## 4. Hechos de marca que se pueden usar (verificados)

- Pronens diseña y confecciona en su **taller propio de Barcelona** desde **1984**. Vende a
  familias en esta tienda y a colegios y empresas en pronens.com.
- El **bordado se hace a máquina en el propio taller**.
- Solo se afirma "confeccionado en nuestro taller de Barcelona" para lo que Pronens
  confecciona: bolsas y sacos, bolsas mochila, baberos, batas escolares y de educadora,
  mochilas de vichí, colchonetas Márfega, sábanas y mantas de hamaca, delantales, prendas
  sanitarias y portasnacks (fabricación de proximidad). NO se afirma para bodys, cojines,
  mascarillas (que son "fabricadas en España"), láminas, cantimplora, fiambrera, estuche,
  sudaderas y camisetas (de la línea de iniciales o del merch): en esos casos lo que hace
  Pronens es estampar, imprimir o **bordar** la prenda, y eso sí se puede decir.
- No lo repitas en todos los textos: en una categoría de 25 productos, que aparezca en
  menos de la mitad y siempre con redacción distinta.

## 5. Personalización: qué se puede prometer, por modo

Cada producto trae su modo en el lote. Lo que dice la tienda de verdad:

- **Modo `texto`** (nombre bordado, 279 productos): el bordado del nombre es **opcional y
  con recargo** (no se cita la cifra). Hasta 30 caracteres. La fuente y el color del hilo
  los fija Pronens para cada prenda, así que **nunca** "elige el color del hilo". Va en
  mayúsculas en algunas prendas y en unicase en otras; no hace falta contarlo.
- **Con nube** (`fondos: Nube marrón, Nube rosa`; las 74 bolsas, 46 baberos y 15 bolsas
  mochila): el nombre no va sobre la tela, va **bordado dentro de una nube de tela**, y el
  cliente elige la nube marrón o la rosa. Es un rasgo muy visible y propio: úsalo.
- **Modo `inicial`** (las 8 sudaderas con iniciales y la mochila 373): la **inicial va
  incluida en el precio**. Se elige una letra de la A a la Z y una de las **seis
  combinaciones de colores de hilo** (contorno e interior). Es UNA sola letra: no prometer
  dos iniciales. La letra es de estilo universitario (collegiate), con contorno.
- **Extras**: solo la mochila 373 ofrece el **llavero de cuadro vichí con el nombre
  bordado**, opcional.
- **Personalizada con foto** (cantimplora 154, fiambrera 155, estuche 156): se personaliza
  con la foto o el diseño que manda el cliente por correo después de comprar, indicando el
  color. No hablar de bordado en estos tres.
- **No personalizable** (`personalizable: False`): no se menciona nada de nombres ni
  bordados. Las láminas tampoco llevan bordado aunque el dato diga lo contrario.

## 6. Fichas de hechos por categoría

Lo que está aquí se ha comprobado en los datos o en el texto original del producto. Lo que
no está, no se afirma. **Ojo**: estas fichas son el inventario de hechos de la categoría, no
un guion para repetir en cada producto. La página de categoría ya los cuenta; cada ficha de
producto toma dos o tres, los que le vengan bien a su ángulo, y el resto se lo deja a la
categoría (ver 2b).

### Bolsas y sacos (74)

Saco de tela **microfibra impermeabilizada** con engomado interior que repele los líquidos
y **seca rápido**; ligero; un compartimento; **cierre con cordón ajustable** que sirve para
colgarlo en la percha o en el carrito. Tres piezas, cada una en su medida (comprueba en el
lote cuáles ofrece cada producto): **chupetera** (mini o pequeña, 14-20 cm: chupete,
pequeños objetos), **bolsa de almuerzo** (25-30 cm: bocadillo, fruta, botella, tupper) y
**bolsa de muda** (37-42 cm: ropa de recambio completa, bañador con toalla pequeña,
pañales). Sirven de 0 a 12 años. El estampado va en la lámina impresa (una cara). Nombre
bordado dentro de nube marrón o rosa. Confeccionadas en el taller de Barcelona.
Ángulos a rotar: la bolsa de guardería que no se moja por dentro; el almuerzo sin tuppers
sueltos; la muda que no se confunde en la percha gracias a la nube con el nombre; piscina
y playa con el bañador húmedo; el juego de tres medidas a juego; el peso pluma dentro de la
mochila del cole; el fin de semana en casa de los abuelos; el regalo de inicio de curso o
de nacimiento; aguanta cursos enteros; el propio cuento o personaje del estampado contado
en una frase, no en un párrafo.

### Baberos bebé (47)

**Microfibra impermeable** (la mayoría): engomado que repele líquidos, **seca en minutos**
(un agua bajo el grifo, se escurre y está listo para la siguiente comida, sin lavadora),
ligero, se lleva enrollado en el bolso, suave y transpirable, **cierre de velcro
ajustable** que vale de 0 a 6 años (OJO: los dos círculos que se ven en el cuello de las
fotos NO son botones, son parte del dibujo impreso; el cierre es velcro, confirmado por el
cliente el 2026-09-14; no describir botones, broches ni "dos posiciones"), ribete de color, libre de BPA y
ftalatos, tejido con certificado Oeko-Tex, cumple la norma EN 15777. Nombre dentro de nube.
Confeccionados en Barcelona. Los tres antiguos (21 Kawaii, 22 Fresa, 44 Búho) NO son
impermeables: son de rizo de algodón con cintas para atar, hasta los 3 años; mira la foto. **Packs de 5 baberos** (263 rojo, 264 azul,
265 verde): rizo de algodón de doble capa, **cuello elástico** (se pone por la cabeza, sin
velcro ni cintas), cinco iguales para la semana de guardería.
Ángulos: las comidas en la guardería; la papilla y la pintura de dedos; el babero que se
seca antes que acabe la siesta; el bolso del cambiador; el regalo de nacimiento con el
nombre; la nube; el personaje del estampado; los packs para tener uno por día.

### Bodys bebé (41)

Body de **algodón orgánico peinado**, muy suave; **corchetes en el hombro para vestirlo sin
tirar de la cabeza** y **tres corchetes en la entrepierna** para cambiar el pañal;
estampado a todo color en el pecho (los 10 lisos van sin estampar); tallas de **3 a 18
meses**; nombre bordado opcional. Estampados: humor de padres (Low Battery, On Off, Not
tired, iPood, Keep Calm), rock (Baby Rock, Milk Mom Rock, Pink Ladies, Grease), cine (Vader,
El Padrino), emociones (los cinco Monstruo de colores: amarillo alegría, azul tristeza, rojo
rabia, gris miedo, rosa amor), celebración (Traje Smoking, Princesa, Hombrecito, Amor
infinito), naturaleza (Perezoso, Wild Leopard, Young wild, Wildflower), mensajes (Good
Vibes, Girl Power, Feliz, Love).
Ángulos: el regalo de nacimiento o de los padrinos; la foto del anuncio; el cambio de pañal
a las tres de la mañana; el body liso con el nombre para la guardería; combinar con lo que
ya tienen; el mensaje del estampado explicado en una frase.

### Cojines divertidos (38)

**Funda de cojín de 40 × 40 cm**, fácil de rellenar; relleno de fibra de poliéster
reciclado **opcional**. Cara impresa de poliéster de **tacto suave tipo algodón que no se
arruga**, impresión a todo color en alta definición; ilustraciones de cultura pop (héroes,
villanos, videojuegos, series). No personalizables. Mira la foto para describir la
ilustración sin nombrar franquicias.
Ángulos: la habitación de un fan; el sofá de la sala de juegos; el regalo para quien ya lo
tiene todo; coleccionar dos o tres personajes de la misma saga; la cabezadita; colores que
combinan con la decoración.

### Mascarillas de tela reutilizables (35)

**Mascarilla higiénica reutilizable** de tela homologada: **doble capa del mismo tejido
hidrófugo** (repele las gotitas), **sin costura central**, goma elástica en las orejas,
antibacteriano, tejido con certificado Oeko-Tex clase I (apto para contacto prolongado con
la piel), cumple UNE 0065:2020 y filtración superior al 95% (UNE-EN 14683), **aguanta 25
lavados a 60 ºC**, fabricada en España. Tres tallas: infantil M (6-9 años), infantil L
(9-12) y adulto; algunas solo en infantil. Debe terminar con una frase de aviso: "Es una
mascarilla higiénica: no es un producto sanitario ni un equipo de protección individual."
Ángulos (sin pandemia ni COVID): temporada de gripe en el cole; visitas al hospital o al
pediatra; alergia al polen; transporte público y viajes; a juego con el estilo de cada uno;
el estampado que hace que el niño quiera ponérsela.

### Batas babis escolares (24)

**Babi escolar de tejido vichí** (cuadro pequeño) o rayas, cada modelo en su color;
**cierre con botones delanteros** de color; **cuello con bies contrastado**; **puños con
elástico**; bolsillo; **motivo estampado en el pecho**; **etiqueta interior para escribir
el nombre**; tallas de 4 a 10 años (algunos solo 4-6 o 4-8). El 240 (Bata guardería Zombi)
va de 6 meses a 4 años. Nombre bordado opcional en el pecho. Confeccionadas en Barcelona.
Ángulos: pintura y plástica sin destrozar la ropa; el comedor; abrochar solo los botones
como paso a la autonomía; no confundirlo con el de otro compañero; el diseño que hace que
quiera ponérselo; los colores que disimulan las manchas (solo si la foto lo apoya);
aguanta el curso entero.

### Mochilas infantiles y escolares (19)

- **26-30 Mochila infantil de vichí** (Cupcake, Sirenita, Panda, Zombie, Sakura):
  **acolchada con guata**, **bolsillo central y dos laterales**, **tiras regulables
  reforzadas y asas de nailon**, nombre bordado opcional **en la solapa**. Talla única,
  para guardería e infantil. Sin nube.
- **218-230 Bolsa mochila de guardería**: saco de tela impermeabilizada con plastificado
  interior, ligera, seca rápido, **cordón lateral para llevarla a la espalda o colgarla del
  carrito**; nombre dentro de nube. Algunas ofrecen dos medidas (almuerzo y muda).
- **373 Mochila personalizada con inicial bordada**: dos tamaños (infantil de 9 litros,
  25 × 30 cm, de 0 a 6 años; adulto de 16 litros, 28 × 40 cm), **diez colores**, **inicial
  bordada en un parche de 9 × 9 cm** con letra universitaria, incluida, con seis
  combinaciones de hilo; llavero de vichí con el nombre, opcional. Bordada en el taller.

### Portasnacks, comida y bebida (15)

- **Envoltorio ecológico reutilizable** (200-212): **doble capa**, exterior de tela de
  poliéster reciclado estampada e impermeabilizada, **interior de EVA apto para alimentos**
  (certificado AITEX), **sin costuras interiores**, se abre y hace de **mantel o
  servilleta**, 20 × 18 cm (abierto 20 × 26), se limpia con un trapo húmedo o en la
  lavadora, sustituye al papel de aluminio y a las bolsas de plástico, sirve también de
  estuche, para niños y adultos. Fabricación de proximidad. Nombre bordado opcional.
- **154 Cantimplora con vaso** (polipropileno, 13,5 × 17,5 × 6,5 cm, tapón de rosca con
  seguro antipérdida, correa ajustable), **155 Fiambrera** (18,2 × 12,4 × 5,8 cm, apta para
  lavavajillas, microondas y congelador) y **156 Estuche metálico** (aluminio, foto en la
  tapa): personalizados con la foto que envía el cliente por correo tras la compra,
  indicando el color (azul, rosa, o metal en el estuche).
- **216 Manopla de baño Unicornios**: rizo de algodón americano, nombre bordado opcional.

### Láminas decorativas (2)

Impresión en **papel reciclado FSC de 300 g**, diseño exclusivo de Pronens. 163 Osito
tribal (32 × 45 o 20 × 30 cm); 266 Dinosaurios Alfabeto (32 × 45 o 50 × 70 cm): el
abecedario con un dinosaurio y su nombre por letra. Sin bordado.

### Colchonetas Márfegas y sábanas ajustables (9)

- **Márfega, colchoneta plegable** (91 naranja, 261 rosa con exterior gris, 262 azul,
  267 marino con raya marinera): **dos espumas de alta densidad de 3 cm, plastificadas e
  impermeables** (sin ftalatos), **funda de tela con asas** para llevarla y colgarla, se
  desenfunda por un **velcro lateral**, 120 × 53 cm estirada y 57 × 53 × 6 plegada,
  aguanta hasta 60 kg; para la siesta en la escuela, casa, excursiones, yoga o playa;
  funda lavable; nombre bordado opcional; confeccionada en el taller.
- **133 Sábana bajera ajustable** para hamaca de guardería (gomas en las cuatro esquinas,
  algodón hipoalergénico Better Cotton, 130 × 60 cm, compatible con hamacas y camitas
  apilables); **369 Sábana saco ajustable** (el peque no se destapa); **370 Sábana
  impermeable** de rizo con lámina de poliuretano que no hace ruido ni da calor, sin BPA;
  **371 Manta polar ajustable** con gomas en las dos esquinas inferiores; **372 Sábana
  encimera** de 180 × 110 con dobladillos reforzados. Tejidos con certificado Oeko-Tex
  Standard 100. Nombre bordado opcional para que no se pierdan. Para colegios con más de
  10 unidades hay precio mayorista (una frase, sin cifras).

### Delantales infantiles (3)

214 Animales y 215 Unicornios: **microfibra impermeable**, seca rápido, **lazo en la
cintura**, dos tallas (0-6 años y 6-12), para pintura, manualidades, cocina y jardinería.
268 Castañera: **tela de farcell** (cuadros negro y marrón, la tela tradicional catalana),
**con bolsillo**, lazo en la cintura, talla única de 2 a 6 años (44 × 54 cm), para la
Castanyada. Nombre bordado opcional.

### Batas para educadoras infantiles (7)

**Blusón para maestras** de infantil y primaria: **patrón evasé** (acampanado en la
cintura) para moverse y agacharse, **dos bolsillos frontales de plastrón**, tejido de punto
resistente que aguanta el curso, tallas S a XXL; colores pistacho, rojo, blanco, lila;
cuatro llevan el estampado **"Teachers are the real influencers"**. Nombre bordado
opcional. Confeccionado en Barcelona.

### Prendas sanitarias (6)

Packs para clínicas, residencias, alimentación, peluquerías, laboratorios y escuelas:
138 batas reutilizables de tela impermeabilizada (pack 5, goma en cuello y puños, 25
lavados, tejido certificado AITEX según EN 13795); 139 batas desechables de vinilo (pack
10); 140 gorros impermeables reutilizables (pack 10, goma elástica); 141 manguitos (pack
20, goma en los extremos); 217 delantales desechables de polietileno (pack 100, un solo
uso); 231 bata sanitaria reutilizable abierta por la espalda con cintas (pack 10, 25-30
lavados). Tono profesional y sobrio, sin pandemia ni "reapertura".

### Official Merch Mikoshin Saga by Ede Minmore (9)

Merchandising **oficial del artista 3D Ede Minmore** (su serie fan de Dragon Ball en
YouTube). Ediciones **limitadas a 500 unidades numeradas** con parche cosido con el número;
algodón orgánico con certificados GOTS, Oeko-Tex y etiqueta vegana. Episodio 11 Broly:
360 camiseta oversize navy con serigrafía delante y detrás, 361 sudadera con capucha
oversize, 362 sudadera oversize en color crudo "Raw Natural", 363 tote bag de 40 × 40 con
fuelle, 364 cojín, 365 alfombrilla de ratón con base antideslizante. Episodio 12 Ultra Ego:
366 tote bag cruda con serigrafía por las dos caras, 367 camiseta unisex de corte recto,
368 sudadera con capucha y bolsillo canguro. Tres llevan el kanji "Certain Victory" en la
espalda. Sin fechas de preventa. Tono de comunidad de fans, sin cursilería.

### Sudaderas personalizadas con iniciales (8)

Sudadera **con felpa perchada por dentro**, de poliéster reciclado y algodón orgánico con
certificado GOTS, unisex, en blanco, celeste, gris, rojo, rosa, negro, lila y marino;
tallas adulto XS-XXL, y la celeste, la negra y la marino también en tallas de niño. La
**inicial va bordada e incluida**: letra de la A a la Z en tipografía universitaria con
contorno, y seis combinaciones de hilo a elegir. Bordada en el taller de Barcelona.
Consejo de talla del original que se conserva: los hombres su talla habitual, las mujeres
una menos. Ángulos: la sudadera de toda la familia con la inicial de cada uno; el regalo
que no se equivoca de nombre; el look urbano con vaqueros; el color.

### Uniformes escolares sin categoría (26)

Prendas del uniforme de dos escuelas concretas y ocho chándales infantiles. Texto corto y
factual (50-100 palabras), sin argumentos emocionales.
- **GOAR** (8-14): bata de vichí de raya marina con bolsillo en escudo con logotipo
  bordado; polos de piqué blanco con cuello de tricotosa marino y rayas en manga o puño;
  pantalón corto; chándal de poliéster mate marino con detalles verdes y logotipos
  bordados (chaqueta con cremallera y franja verde, pantalón con vivo verde).
- **Salesians** (63-75): chándal marino con franja naranja y detalles blancos, logo
  bordado en pecho, cuello y pierna, obligatorio para educación física; camisetas blancas
  con elásticos marino; pantalón corto técnico marino con franjas naranja y blanca; batas
  (saquito P3, botones P4-P5, botones primaria); mochila de parvulario y bolsa de recambio.
- **Chándales infantiles 35-42** (Sirenita, Panda, Sakura, Zombi en celeste, rojo,
  pistacho y naranja): de 0 a 4 años; describe lo que se ve en la foto.

## 7. Formato de entrega

Cada lote se entrega como un fichero JSON con una entrada por producto:

```json
[
  {
    "id": 132,
    "titulo": "Bolsa guardería impermeable Caperucita Roja",
    "angulo": "la muda que no se confunde en la percha",
    "body": "<p>…</p><p>…</p><ul><li><strong>…</strong> …</li></ul><p>…</p>"
  }
]
```

HTML permitido: `<p>`, `<strong>`, `<em>`, `<ul>`, `<ol>`, `<li>`, `<h3>`, `<br>`. Nada
de atributos, estilos, enlaces ni imágenes. Sin saltos de línea dentro del `body` (una sola
cadena). Comillas tipográficas « » o “ ” dentro del texto, nunca comillas rectas sin
escapar. Los caracteres van tal cual en UTF-8 (tildes, eñes, ×, º).

## 8. Ejemplos de referencia

**Bolsa (modo texto, con nube, tres piezas):**

```html
<p>Bolsa de guardería impermeable con la Caperucita Roja, para llevar la muda o el almuerzo de los peques de 0 a 6 años con su nombre bordado dentro de una nube. La lámina pinta a Caperucita con su cesta camino de casa de la abuela, sobre fondo crema, y el cordón rojo remata el conjunto.</p>
<p>Por dentro lleva un <strong>engomado que repele los líquidos</strong>: el bañador húmedo o el pantalón del accidente de la siesta vuelven a casa sin mojar la mochila. Se seca en un rato y pesa muy poco.</p>
<h3>Tres medidas, tres usos</h3>
<p>La <strong>chupetera</strong> para el chupete y los pequeños tesoros, la <strong>bolsa de almuerzo</strong> para el bocadillo, la fruta y la botella, y la <strong>bolsa de muda</strong>, donde cabe la ropa de recambio completa con los calcetines. A juego se identifican de un vistazo en la percha.</p>
<p>Si quieres que ninguna acabe en otro casillero, añade el nombre: va bordado en nuestro taller de Barcelona dentro de una nube de tela, marrón o rosa, a elegir.</p>
```

**Body (modo texto, sin nube):**

```html
<p>Body de bebé Low Battery en algodón orgánico, con el icono de la batería agotada en el pecho, para recién nacidos y hasta los 18 meses. El chiste es para los padres; la suavidad, para el bebé.</p>
<p>El algodón peinado no raspa y los <strong>corchetes del hombro</strong> permiten vestirlo sin pasarle nada apretado por la cabeza. Abajo, <strong>tres corchetes</strong> para el cambio de pañal de las tres de la mañana, que con este dibujo tiene aún más sentido.</p>
<p>Blanco con el icono en negro y rojo, combina con cualquier pantalón o ranita que ya tengas.</p>
<p>Un regalo de nacimiento que arranca una sonrisa, y aún más si lo pides con el <strong>nombre bordado</strong>.</p>
```

**Cojín (no personalizable):**

```html
<p>Funda de cojín de 40 × 40 cm con la ilustración de un guerrero del espacio de armadura blanca y pecho azul, para la habitación de un fan de la saga o el sofá de la sala de juegos. El fondo azul eléctrico hace que destaque sobre una cama oscura.</p>
<ul>
<li><strong>Impresión en alta definición</strong> a todo color, sin bordes pixelados.</li>
<li><strong>Tacto suave tipo algodón</strong> que no se arruga.</li>
<li><strong>Fácil de rellenar</strong>: el relleno de fibra reciclada es opcional.</li>
</ul>
<p>Combina con los otros personajes de la colección para montar el rincón completo.</p>
```

## 9. Control de calidad automático

Antes de importar, `scripts/textos/descripciones/validar.py` comprueba cada texto: HTML
permitido, longitud, primera frase entre 90 y 155 caracteres, palabras prohibidas, cifras
de composición, lavado, envío y precio, y solapamiento de frases entre productos (ninguna
frase de más de ocho palabras puede repetirse en dos productos). Lo que no pase se reescribe.
