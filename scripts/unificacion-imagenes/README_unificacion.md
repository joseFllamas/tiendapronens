# Unificación de fotos de producto Pronens

Herramienta para dejar todas las fotos con el mismo fondo gris y el mismo
espaciado, en un lienzo vertical 3:4. Pensada para pasar las imágenes **antes
de subirlas** a la tienda, tanto el catálogo actual como cada producto nuevo.

## La idea en una frase

Lo que sale impecable de forma automática se normaliza con el script (coste
cero). Lo dudoso se aparta para Nano Banana. Nunca se toca el original: solo se
lee.

## Qué se queda en el script y qué baja a Nano Banana

Se queda en "ok/" (normalizada) si se cumple una de estas:

1. Es un PNG con transparencia real, es decir, ya venía recortado.
2. El fondo es blanco o claro y uniforme en las cuatro esquinas, y además el
   resultado pasa un control de calidad (el producto queda compacto y con
   margen, no comido ni disperso).

Se copia a "a_nanobanana/" todo lo demás: fondos de color, con textura o de dos
tonos, escenas, atrezo, y el caso de producto blanco sobre blanco que el recorte
automático se comería. Es la aplicación de tu criterio de "todo lo dudoso a IA".

Esta separación es un filtro de seguridad, no un juicio perfecto. Conviene dar
un vistazo final a la carpeta "ok/": si alguna no te convence, la mueves a mano
a "a_nanobanana/".

## Instalación y uso

Requiere Python 3.

```
pip install pillow numpy scipy
python3 unificar_imagenes.py --in CARPETA_ENTRADA --out CARPETA_SALIDA
```

Opcionales (valores por defecto ya acordados):

```
--gray "#f1f1f0"   color de fondo
--w 1200 --h 1600  lienzo 3:4 vertical
--margin 150       margen mínimo alrededor del producto
```

## Qué produce

```
CARPETA_SALIDA/
  ok/                    imágenes normalizadas, listas para subir
  a_nanobanana/          originales que hay que pasar por Nano Banana
  manifest.csv           decisión y métricas de cada imagen
  PROMPT_nanobanana.txt  el prompt, para copiar y pegar
```

## Flujo con Nano Banana

No hace falta ir imagen a imagen: el script procesar_nanobanana.py recorre toda
la carpeta con tu API key de Gemini.

```
pip install google-genai pillow
export GEMINI_API_KEY="tu_clave"     # se consigue en https://aistudio.google.com/apikey
python3 procesar_nanobanana.py --in a_nanobanana --out nb_salida
```

Empieza con una prueba pequeña antes de tirar toda la carpeta:

```
python3 procesar_nanobanana.py --in a_nanobanana --out nb_salida --dry-run   # solo cuenta y estima coste
python3 procesar_nanobanana.py --in a_nanobanana --out nb_salida --max 5      # procesa 5 y las revisas
```

Es reanudable (salta lo ya hecho), reintenta ante límites de cuota y anota los
fallos en errores.log. Por defecto usa gemini-2.5-flash-image (Nano Banana, el
más barato, de sobra para cambiar el fondo). Con --model gemini-3-pro-image usa
Nano Banana Pro, mejor con texto y bordados pero más caro.

Las salidas ya vienen re-encuadradas al lienzo 3:4 con el mismo margen que la
versión no IA (padding integrado): el script detecta el producto sobre el gris y
lo recoloca centrado, sin recortarlo, y además remapea el fondo al gris exacto
#f1f1f0 (el gris que genera la IA a veces se desvía un poco). Si en algún caso no
quieres ese re-encuadre, añade --no-pad para guardar la salida cruda.

Control de calidad automático del blanco sobre blanco: si la IA devuelve la foto
original como un rectángulo blanco sobre el gris (o sin cambiar el fondo), el
script lo detecta y reintenta solo: primero con un pase correctivo y, si no
basta, con una receta en 3 fases (oscurecer el producto, cambiar el fondo a
gris, re-aclarar el producto a blanco). Si aun así no lo consigue, guarda el
último intento en la subcarpeta revisar/ y lo anota en errores.log. Esto puede
suponer hasta 4 llamadas extra en esas imágenes difíciles. Se desactiva con
--no-fix.

Para re-encuadrar salidas de IA que generaste antes sin padding, sin volver a
llamar a la API, usa el modo pad de la otra herramienta:

```
python3 unificar_imagenes.py --in CARPETA_IA --out CARPETA_FINAL --mode pad
```

El modo pad no recorta: solo detecta el producto sobre el gris y añade el margen.
Es seguro con productos blancos sobre gris claro (no se los come).

Revisa siempre las salidas de Nano Banana: puede alterar detalles finos como un
estampado, un texto o un bordado.

### Prompt para Nano Banana

```
Put this product photo on a plain #f1f1f0 light-gray background. The gray
background must cover the ENTIRE image, edge to edge: no white background, no
white rectangle, no frame or border of the original photo may remain anywhere.
If there is a single clear product, cut it out cleanly and center it. If there
are several elements or no single clear object, keep the composition intact and
change only the background. If the product is white or very light, keep it clean
bright white so it clearly stands out against the #f1f1f0 gray. Never move,
resize, add or remove any object or person, and preserve every print, text, fold
and embroidery exactly. No new shadows or reflections.
```

Nota: el prompt ya no pide márgenes ni lienzo 3:4 a la IA a propósito. Aquella
instrucción le daba una vía fácil (montar la foto tal cual sobre un lienzo gris)
y los márgenes los pone siempre el re-encuadre determinista del script.

Revisa siempre las salidas de Nano Banana: puede alterar detalles finos como un
estampado, un texto o un bordado. Ese repaso es la única parte manual del flujo.

## Para productos nuevos

Antes de subir las fotos de un producto nuevo, mételas en una carpeta y corre la
herramienta. Las limpias saldrán normalizadas y las que necesiten IA quedarán
apartadas con su prompt. Mismo criterio para todo el catálogo, presente y futuro.

## Notas

La herramienta no modifica los originales. Sobre el catálogo actual, ten en
cuenta que muchos ficheros de la carpeta de Drupal no son fotos de producto
(banners, ambientes, cabeceras): esos conviene excluirlos de la carpeta de
entrada, o revisarlos en el manifest.
