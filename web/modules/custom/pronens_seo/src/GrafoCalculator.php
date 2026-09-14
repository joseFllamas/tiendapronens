<?php

declare(strict_types=1);

namespace Drupal\pronens_seo;

/**
 * Añade al @graph de schema_metatag lo que ese módulo no sabe expresar.
 *
 * schema_metatag 3.0 no trae plugin para legalName, taxID, foundingDate,
 * currenciesAccepted, hasMerchantReturnPolicy, shippingDetails ni ItemList, y
 * tampoco ofrece un alter del JSON-LD ya montado: solo hook_metatags_alter,
 * que corre cuando las etiquetas aún llevan los tokens dentro y descarta
 * cualquier clave sin plugin. Por eso el enriquecimiento se hace sobre el
 * documento final, en JsonLdHooks, y esta clase es su parte sin Drupal: recibe
 * el @graph decodificado más los datos ya leídos y devuelve el @graph nuevo.
 * Lógica pura, con pruebas unitarias.
 */
final class GrafoCalculator {

  /**
   * Los @type que se consideran la ficha de la empresa.
   *
   * schema_organization_type es configurable (hoy OnlineStore), así que no se
   * puede buscar por un valor fijo.
   *
   * @var array<int, string>
   */
  private const TIPOS_EMPRESA = [
    'Organization',
    'OnlineStore',
    'Store',
    'ClothingStore',
    'LocalBusiness',
  ];

  /**
   * Los @type de página de colección que admiten un ItemList.
   *
   * @var array<int, string>
   */
  private const TIPOS_COLECCION = ['CollectionPage', 'SearchResultsPage'];

  /**
   * Los @type que referencian a la tienda desde su publisher.
   *
   * @var array<int, string>
   */
  private const TIPOS_SITIO = ['WebSite'];

  /**
   * Enriquece el grafo entero.
   *
   * @param array<int, mixed> $grafo
   *   El @graph tal y como lo dejó schema_metatag.
   * @param array<string, mixed> $datos
   *   'empresa' => propiedades sueltas que se añaden a la Organization;
   *   'base' => la raíz del sitio sin prefijo de idioma, para los @id;
   *   'devolucion' => nodo MerchantReturnPolicy o NULL;
   *   'envio' => lista de OfferShippingDetails (puede ir vacía);
   *   'vendedor' => @id de reserva si la Organization no trae ninguno;
   *   'skus' => SKU por posición de Offer, alineado con el orden del pivot;
   *   'coleccion' => ['numberOfItems' => int, 'itemListElement' => array].
   *
   * @return array<int, mixed>
   *   El grafo con las propiedades añadidas.
   */
  public static function enriquecer(array $grafo, array $datos): array {
    // El @id de la tienda se resuelve UNA vez y lo usan los tres sitios que
    // hablan de ella: su propio nodo, el seller de cada Offer y el publisher
    // del WebSite. Así no pueden desincronizarse.
    $datos['tienda'] = self::idDeLaTienda($grafo, (string) ($datos['base'] ?? ''))
      ?? (string) ($datos['vendedor'] ?? '');

    foreach ($grafo as $i => $nodo) {
      if (!is_array($nodo)) {
        continue;
      }
      if (self::esDe($nodo, self::TIPOS_EMPRESA)) {
        $grafo[$i] = self::empresa($nodo, $datos);
        continue;
      }
      if (self::esDe($nodo, ['Product'])) {
        $grafo[$i] = self::producto($nodo, $datos);
        continue;
      }
      if (self::esDe($nodo, self::TIPOS_SITIO)) {
        $grafo[$i] = self::sitio($nodo, $datos);
        continue;
      }
      if (self::esDe($nodo, self::TIPOS_COLECCION)) {
        $grafo[$i] = self::coleccion($nodo, $datos);
      }
    }

    return $grafo;
  }

  /**
   * El @id de la tienda, ya sin el prefijo de idioma.
   *
   * @param array<int, mixed> $grafo
   *   El @graph entero.
   * @param string $base
   *   La raíz del sitio sin prefijo de idioma.
   *
   * @return string|null
   *   El @id, o NULL si la ficha de empresa no trae ninguno.
   */
  private static function idDeLaTienda(array $grafo, string $base): ?string {
    foreach ($grafo as $nodo) {
      if (!is_array($nodo) || !self::esDe($nodo, self::TIPOS_EMPRESA)) {
        continue;
      }
      $id = $nodo['@id'] ?? NULL;
      if (!is_string($id) || $id === '') {
        return NULL;
      }

      return self::sinIdioma($id, $base);
    }

    return NULL;
  }

  /**
   * Quita el prefijo de idioma de un @id conservando su fragmento.
   *
   * El token [site:url] de la configuración de metatag resuelve con el idioma
   * de la página, así que la ficha de empresa salía con un @id distinto en
   * cada uno (…/ca#store) y para un grafo de conocimiento eran CINCO tiendas
   * en vez de una, que es justo lo contrario de para lo que sirve un @id. Y
   * peor: el seller de cada Offer se forma con la raíz sin prefijo, de modo
   * que en cuatro de los cinco idiomas apuntaba a un nodo que no estaba en el
   * documento. El fragmento se conserva porque lo decide la configuración
   * (hoy #store), no este código.
   *
   * @param string $id
   *   El @id tal y como lo dejó schema_metatag.
   * @param string $base
   *   La raíz del sitio sin prefijo de idioma, con o sin barra final.
   *
   * @return string
   *   El @id sin prefijo, o el mismo de entrada si no hay nada que hacer.
   */
  private static function sinIdioma(string $id, string $base): string {
    $almohadilla = strpos($id, '#');
    if ($base === '' || $almohadilla === FALSE) {
      return $id;
    }

    return rtrim($base, '/') . '/' . substr($id, $almohadilla);
  }

  /**
   * La ficha de la empresa: identidad, antigüedad y política de devolución.
   *
   * @param array<string, mixed> $nodo
   *   El nodo Organization.
   * @param array<string, mixed> $datos
   *   Los datos del enriquecimiento.
   *
   * @return array<string, mixed>
   *   El nodo con las propiedades añadidas.
   */
  private static function empresa(array $nodo, array $datos): array {
    // Se normaliza, no se inventa: si metatag no puso @id, no lo pone nadie.
    if (!self::vacio($nodo['@id'] ?? NULL) && !empty($datos['tienda'])) {
      $nodo['@id'] = $datos['tienda'];
    }
    foreach (($datos['empresa'] ?? []) as $clave => $valor) {
      if ($valor === NULL || $valor === '' || $valor === []) {
        continue;
      }
      // Lo que ya venga de la configuración de metatag manda: así el cliente
      // puede corregir un dato desde el backoffice sin tocar código.
      if (!self::vacio($nodo[$clave] ?? NULL)) {
        continue;
      }
      $nodo[$clave] = $valor;
    }
    if (!empty($datos['devolucion']) && self::vacio($nodo['hasMerchantReturnPolicy'] ?? NULL)) {
      $nodo['hasMerchantReturnPolicy'] = $datos['devolucion'];
    }

    return $nodo;
  }

  /**
   * El producto: seller, sku y condiciones de envío en cada Offer.
   *
   * @param array<string, mixed> $nodo
   *   El nodo Product.
   * @param array<string, mixed> $datos
   *   Los datos del enriquecimiento.
   *
   * @return array<string, mixed>
   *   El nodo con las Offer completadas.
   */
  private static function producto(array $nodo, array $datos): array {
    $nodo = self::booleanos($nodo);
    $ofertas = $nodo['offers'] ?? NULL;
    if (self::vacio($ofertas)) {
      return $nodo;
    }
    // Con una sola variación schema_metatag no pivota y deja un objeto suelto.
    $suelta = self::esObjeto($ofertas);
    $lista = $suelta ? [$ofertas] : $ofertas;
    $skus = $datos['skus'] ?? [];

    foreach ($lista as $i => $oferta) {
      if (!is_array($oferta)) {
        continue;
      }
      if (!empty($datos['tienda']) && self::vacio($oferta['seller'] ?? NULL)) {
        $oferta['seller'] = ['@type' => 'Organization', '@id' => $datos['tienda']];
      }
      if (isset($skus[$i]) && $skus[$i] !== '' && self::vacio($oferta['sku'] ?? NULL)) {
        $oferta['sku'] = $skus[$i];
      }
      if (!empty($datos['envio']) && self::vacio($oferta['shippingDetails'] ?? NULL)) {
        $oferta['shippingDetails'] = $datos['envio'];
      }
      if (!empty($datos['devolucionRef']) && self::vacio($oferta['hasMerchantReturnPolicy'] ?? NULL)) {
        $oferta['hasMerchantReturnPolicy'] = ['@id' => $datos['devolucionRef']];
      }
      $lista[$i] = $oferta;
    }
    $nodo['offers'] = $suelta ? reset($lista) : array_values($lista);

    return $nodo;
  }

  /**
   * Devuelve los booleanos de schema.org como booleanos de verdad.
   *
   * representativeOfPage es Boolean en schema.org, pero schema_metatag pasa
   * todos los valores por la sustitución de tokens, que trabaja con cadenas, y
   * lo publica como "1". Los validadores de Google lo toleran; un parser
   * estricto (y varios motores de respuesta lo son) no tiene por qué.
   *
   * @param array<string, mixed> $nodo
   *   El nodo Product.
   *
   * @return array<string, mixed>
   *   El nodo con los booleanos arreglados.
   */
  private static function booleanos(array $nodo): array {
    $imagen = $nodo['image'] ?? NULL;
    if (!is_array($imagen) || !isset($imagen['representativeOfPage'])) {
      return $nodo;
    }
    $valor = $imagen['representativeOfPage'];
    if (!is_bool($valor)) {
      $imagen['representativeOfPage'] = in_array(strtolower((string) $valor), ['1', 'true'], TRUE);
      $nodo['image'] = $imagen;
    }

    return $nodo;
  }

  /**
   * El WebSite: su publisher tiene que ser el mismo nodo que el seller.
   *
   * El @id del propio WebSite se deja como está: cada idioma tiene su portada
   * y su inLanguage, así que ahí el prefijo no sobra. Lo que no puede variar
   * es la referencia a la tienda, que es una sola entidad.
   *
   * @param array<string, mixed> $nodo
   *   El nodo WebSite.
   * @param array<string, mixed> $datos
   *   Los datos del enriquecimiento.
   *
   * @return array<string, mixed>
   *   El nodo con el publisher repuntado.
   */
  private static function sitio(array $nodo, array $datos): array {
    $publisher = $nodo['publisher'] ?? NULL;
    if (!is_array($publisher) || self::vacio($publisher['@id'] ?? NULL) || empty($datos['tienda'])) {
      return $nodo;
    }
    $publisher['@id'] = $datos['tienda'];
    $nodo['publisher'] = $publisher;

    return $nodo;
  }

  /**
   * La página de categoría: qué productos lista y cuántos hay en total.
   *
   * @param array<string, mixed> $nodo
   *   El nodo CollectionPage.
   * @param array<string, mixed> $datos
   *   Los datos del enriquecimiento.
   *
   * @return array<string, mixed>
   *   El nodo con su ItemList.
   */
  private static function coleccion(array $nodo, array $datos): array {
    $coleccion = $datos['coleccion'] ?? [];
    if (empty($coleccion['itemListElement']) || !self::vacio($nodo['mainEntity'] ?? NULL)) {
      return $nodo;
    }
    $nodo['mainEntity'] = [
      '@type' => 'ItemList',
      'numberOfItems' => $coleccion['numberOfItems'] ?? count($coleccion['itemListElement']),
      'itemListElement' => $coleccion['itemListElement'],
    ];

    return $nodo;
  }

  /**
   * Los elementos de un ItemList a partir de las URL y los nombres.
   *
   * Se numera desde 1 y en el orden en el que salen en la página. El nombre
   * viene del título de la entidad y no del DOM ya pintado: hay productos del
   * D7 cuyo enlace y cuyo título no se corresponden (alias cruzados), y leer
   * la tarjeta arrastraría ese cruce a los datos estructurados.
   *
   * @param array<int, array{url: string, nombre: string}> $productos
   *   Los productos de la página, en orden.
   * @param int $desde
   *   Posición del primero (2ª página del paginador empieza más allá de 1).
   *
   * @return array<int, array<string, mixed>>
   *   Los ListItem.
   */
  public static function items(array $productos, int $desde = 1): array {
    $items = [];
    $posicion = max(1, $desde);
    foreach ($productos as $producto) {
      if (empty($producto['url'])) {
        continue;
      }
      $item = ['@type' => 'ListItem', 'position' => $posicion, 'url' => $producto['url']];
      if (!empty($producto['nombre'])) {
        $item['name'] = $producto['nombre'];
      }
      $items[] = $item;
      $posicion++;
    }

    return $items;
  }

  /**
   * Si el nodo es de alguno de los tipos dados.
   *
   * @param array<string, mixed> $nodo
   *   El nodo del grafo.
   * @param array<int, string> $tipos
   *   Los @type que se buscan.
   */
  private static function esDe(array $nodo, array $tipos): bool {
    $tipo = $nodo['@type'] ?? NULL;
    if (is_string($tipo)) {
      return in_array($tipo, $tipos, TRUE);
    }
    if (is_array($tipo)) {
      return (bool) array_intersect($tipo, $tipos);
    }

    return FALSE;
  }

  /**
   * Si el valor es un objeto suelto y no una lista de objetos.
   *
   * @param mixed $valor
   *   El valor de 'offers'.
   */
  private static function esObjeto(mixed $valor): bool {
    return is_array($valor) && !array_is_list($valor);
  }

  /**
   * Si la propiedad no trae nada aprovechable.
   *
   * schema_metatag deja cadenas vacías en las etiquetas sin valor, así que no
   * basta con isset().
   *
   * @param mixed $valor
   *   El valor a comprobar.
   */
  private static function vacio(mixed $valor): bool {
    return $valor === NULL || $valor === '' || $valor === [];
  }

}
