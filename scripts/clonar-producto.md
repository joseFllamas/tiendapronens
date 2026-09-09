# Clonar un producto para un color nuevo

Guía de uso de [`clonar-producto.php`](clonar-producto.php). El porqué técnico y las trampas de
la API están en el docblock del propio script; aquí está el procedimiento.

## La decisión: un producto por color

Cuando llega un modelo que ya existe en otro color, se **clona el producto**. No se añade el color
como atributo dentro de la misma ficha, y no es por comodidad:

- **La galería es del producto, no de la variación.** `FichaHooks::fotos()` lee
  `field_imagen_principal` y `field_galeria` del producto; la foto de la variación
  (`field_imagenes`) solo se usa en la muestra del selector, en la línea del carrito y en el
  montaje del bordado. Con los colores dentro de una ficha, elegir «Rojo» no cambiaría ni una foto.
- **La vista previa del bordado va anclada a una sola foto** (`field_imagen_principal`, o
  `field_bordado_foto` si está puesta), por decisión del cliente del 2026-07-29. En una ficha
  multicolor la previa saldría siempre sobre el mismo color.
- **El eje color está muerto en los datos**: 39 de 1123 variaciones lo usan, y por eso la faceta de
  color se descartó.
- **El catálogo ya funciona así**: 43 de los 360 productos publicados llevan el color en el título
  (Chándal azul/rojo/pistacho, Body bebé liso Rosa/Azul celeste/Blanco).

Unificar colores en una sola ficha es una funcionalidad aparte (swap de galería por variación más
elegir qué foto lleva el montaje) y habría que aplicarla a todo el catálogo, no a un producto.

## Lo que hay que rellenar en la tanda

Cada encargo es un fichero de `scripts/tandas/` que devuelve un array de productos a clonar. El
script es solo la maquinaria: se le pasa el fichero como argumento. Así cada tanda queda como
registro de lo que se hizo, igual que el resto de scripts del repo. Las que hay:

| Tanda | Qué hizo |
|---|---|
| `269-colores.php` | El blusón de educadora en rojo y blanco (ejecutada en producción el 2026-09-09). |
| `269-estampado.php` | El mismo blusón con el estampado «Teachers are the real influencers», en blanco, pistacho, rojo y lila. |

Para una tanda nueva se copia la más parecida y se cambian estos campos. Lo demás lo pone el
script solo.

| Campo | Qué es |
|---|---|
| `origen` | Id del producto que se clona. |
| `titulos` | El título por idioma. Los que falten se quedan con el del origen y el script avisa. |
| `texto` | Sustituciones dentro del body, por idioma. Se aplican **en orden**, así que las claves largas van primero. |
| `sku` | Sustitución aplicada a cada SKU del origen (por ejemplo `['PIST' => 'ROJO']`). |
| `skus` | Alternativa a lo anterior: mapa explícito SKU viejo a SKU nuevo, para cuando no hay patrón. |
| `stock` | Unidades por variación. A 0 no crea transacción y el producto saldrá «Agotado». |
| `precio` | Opcional. Si falta, se copia el del origen variación a variación; si está, se aplica a todas (una prenda estampada no tiene por qué costar lo mismo que la lisa). |
| `color_attr` | Id del valor de `attribute_color`, solo si el producto de origen usa ese atributo. |
| `hilo` | Nuevo `field_bordado_color`, si el hilo cambia con el color de la prenda. |
| `fotos` | `principal` (una ruta) y `galeria` (lista de rutas), relativas a la raíz del repo. |

Tres avisos sobre el contenido:

- **El color va escrito en el body y cada idioma usa su palabra.** En el blusón 269 el catalán usa
  dos, «festuc» en el título y «pistatxo» en el cuerpo, y el francés cambia de género («couleur
  pistache» pasa a «couleur blanche», no «blanc»). La sustitución es literal: hay que repasar los
  cinco idiomas después.
- **Los SKU no tienen patrón en este catálogo.** Conviven `BLUS.PIST.T-S`, `MO.ACOLCHADA PANDA`,
  `Cojin31 - Vader` y `MaskPineapples - T.Infantill-L`. Si la sustitución no encaja, se usa `skus`.
- **El stock no se copia**: copiar el nivel del original sería inventarse inventario.

Cuando una tanda es el mismo producto en varios colores, el fichero puede montar el array con un
bucle sobre una tabla de colores, como hace `269-estampado.php`: los nombres de foto salen de un
patrón y así una errata en el nombre del fichero se corrige en un sitio y no en ocho.

## Las fotos

Van en la carpeta `fotos-clones/` de la raíz del repo, que está en `.gitignore`.

Con **una foto por color basta** si en el producto de origen todas las variaciones comparten la
foto principal (es el caso del 269: sus cinco variaciones apuntan al mismo media). El script lo
comprueba y reproduce el mismo reparto: si allí compartían la principal, aquí también. Si no,
deja las fotos de variación vacías y lo dice. Las fotos de más van en la lista `galeria`.

## Procedimiento en producción

```bash
cd /ruta/al/proyecto
git pull

# Las fotos, con los nombres que diga la tanda
mkdir -p fotos-clones

# Copia de seguridad antes de escribir
drush sql:dump --gzip --result-file=/ruta/segura/pre-clonar-<producto>.sql

# Sin argumentos, lista las tandas disponibles
drush php:script scripts/clonar-producto.php

# Simulación: enseña SKU, títulos e idiomas y no toca nada
drush php:script scripts/clonar-producto.php -- scripts/tandas/269-estampado.php

# Crear
drush php:script scripts/clonar-producto.php -- scripts/tandas/269-estampado.php --crear
```

En local es lo mismo con `ddev drush` delante, y ahí la copia previa se hace con
`ddev snapshot --name pre-clonar-<producto>`.

Es **idempotente**: si los SKU nuevos ya existen, la tanda se da por hecha y se salta. Si existen
solo algunos, se para y avisa de que está clonado a medias.

## Qué revisar después

El clon nace **despublicado en todos sus idiomas y sin destacar**, así que no hay prisa ni riesgo
para la tienda viva. Antes de publicar:

1. Las fotos: principal, galería y la de cada variación.
2. El stock de cada talla, si se dejó a 0.
3. El texto en los cinco idiomas. La sustitución es literal, y en el catálogo migrado hay bodys con
   restos de castellano (el italiano del 269 es uno) que el clon hereda tal cual.
4. Publicar cada idioma.
5. Si el producto tiene que salir en «Completa el conjunto» de otros, añadirlo al
   `field_complementarios` de esos: el clon copia los suyos, pero nadie apunta a él.

## Lo que el script no hace

- No traduce: sustituye literalmente lo que se le diga.
- No toca el producto de origen (comprobado: título, variaciones y alias intactos).
- No inventa precios: copia los del origen salvo que la tanda declare `precio`.
- No crea valores de atributo. Si el color nuevo no existe en `attribute_color`, hay que crearlo
  antes y pasar su id en `color_attr`.
- No añade el clon a los complementarios de nadie.
