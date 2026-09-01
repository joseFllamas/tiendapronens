<?php

declare(strict_types=1);

namespace Drupal\pronens_buscador\Controller;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\search_api\Entity\Index;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Las sugerencias en vivo del buscador del header.
 *
 * La referencia es el buscador del D10 de pronens (activity_search_pro):
 * tecleas y ves tarjetas con foto que llevan al producto. Aquí la consulta va
 * contra el índice de search_api del catálogo, exactamente la misma búsqueda
 * que la página /buscar (mismos campos, mismo idioma, mismo matching), así que
 * las sugerencias y los resultados completos nunca discrepan. Lo que el D10
 * hacía mal y aquí no: los despublicados no salen (el procesador entity_status
 * no los indexa), hay límite de resultados y la respuesta es cacheable.
 */
final class SugerenciasController implements ContainerInjectionInterface {

  /**
   * Sugerencias como mucho: el resto se ve en /buscar con el enlace del pie.
   */
  private const MAXIMO = 6;

  /**
   * Longitud mínima del término, la misma min_chars del server search_api.
   */
  private const MINIMO = 3;

  /**
   * Estilo de la miniatura: el mismo que las líneas del carrito (148px).
   */
  private const ESTILO = 'pronens_carrito';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly LanguageManagerInterface $languageManager,
    private readonly CurrencyFormatterInterface $currencyFormatter,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('commerce_price.currency_formatter'),
    );
  }

  /**
   * Devuelve las sugerencias para un término, como JSON cacheable.
   */
  public function json(Request $request): CacheableJsonResponse {
    $texto = trim((string) $request->query->get('texto', ''));
    $idioma = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['url.query_args:texto', 'languages:language_content']);
    $cache->addCacheTags(['commerce_product_list']);

    $payload = [
      'termino' => $texto,
      'total' => 0,
      'resultados' => [],
      'todos' => NULL,
    ];

    if (mb_strlen($texto) >= self::MINIMO) {
      $indice = Index::load('catalogo');
      $consulta = $indice->query()
        ->keys($texto)
        ->setFulltextFields(['titulo', 'sku'])
        ->addCondition('search_api_language', $idioma)
        // Solo productos con categoría: la basura publicada del D7 ("Test
        // sudadera", "Pedido 7682") no tiene y ensuciaría el buscador. La
        // página /buscar aplica el mismo filtro.
        ->addCondition('tipo', NULL, '<>')
        ->range(0, self::MAXIMO)
        ->sort('search_api_relevance', 'DESC');
      $resultados = $consulta->execute();
      $payload['total'] = (int) $resultados->getResultCount();

      foreach ($resultados as $item) {
        $producto = $item->getOriginalObject()?->getValue();
        if (!$producto instanceof ProductInterface) {
          continue;
        }
        $payload['resultados'][] = $this->sugerencia($producto, $texto, $cache);
      }

      // El "ver todos": la página /buscar con el mismo término.
      $todos = Url::fromRoute('view.buscar.page_1', [], [
        'query' => ['texto' => $texto],
      ])->toString(TRUE);
      $cache->addCacheableDependency($todos);
      $payload['todos'] = $todos->getGeneratedUrl();
    }

    $respuesta = new CacheableJsonResponse($payload);
    $respuesta->addCacheableDependency($cache);

    return $respuesta;
  }

  /**
   * Los datos de una sugerencia: nombre, URL, foto, precio y SKU coincidente.
   *
   * @return array<string, mixed>
   *   Datos listos para que el JS pinte la tarjeta.
   */
  private function sugerencia(ProductInterface $producto, string $texto, CacheableMetadata $cache): array {
    // La regla de la casa: una entidad cargada a mano llega en castellano, así
    // que se traduce ANTES de pedirle el nombre o la URL.
    $traducido = $this->entityRepository->getTranslationFromContext($producto);
    assert($traducido instanceof ProductInterface);
    $cache->addCacheableDependency($traducido);

    [$sku, $variacion_id] = $this->skuCoincidente($producto, $texto);
    $opciones = $variacion_id !== NULL ? ['query' => ['v' => $variacion_id]] : [];
    $url = $traducido->toUrl('canonical', $opciones)->toString(TRUE);
    $cache->addCacheableDependency($url);

    return [
      'nombre' => (string) $traducido->label(),
      'url' => $url->getGeneratedUrl(),
      'foto' => $this->foto($producto, $cache),
      'precio' => $this->precioDesde($producto),
      'sku' => $sku,
    ];
  }

  /**
   * El SKU de la variación que responde al término, si responde alguna.
   *
   * Con la misma normalización que el tokenizer del índice (que descarta
   * ". _ -"): así "OSOTRIB" y "bg.osotrib" coinciden con BG.OSOTRIB.PEQ. Si
   * hay coincidencia se devuelve también el id, para que el enlace lleve a la
   * ficha con esa variación preseleccionada (?v=ID).
   *
   * @return array{0: string|null, 1: int|null}
   *   SKU e id de la variación, o [NULL, NULL].
   */
  private function skuCoincidente(ProductInterface $producto, string $texto): array {
    $termino = $this->normaliza($texto);
    if ($termino === '') {
      return [NULL, NULL];
    }
    foreach ($producto->getVariations() as $variacion) {
      $sku = (string) $variacion->getSku();
      if ($sku !== '' && str_contains($this->normaliza($sku), $termino)) {
        return [$sku, (int) $variacion->id()];
      }
    }

    return [NULL, NULL];
  }

  /**
   * Minúsculas y sin separadores de referencia, como indexa el tokenizer.
   */
  private function normaliza(string $texto): string {
    return mb_strtolower(str_replace(['.', '_', '-', ' '], '', $texto));
  }

  /**
   * URL de la miniatura del producto, o NULL si no tiene foto.
   */
  private function foto(ProductInterface $producto, CacheableMetadata $cache): ?string {
    foreach (['field_imagen_principal', 'field_galeria'] as $campo) {
      if (!$producto->hasField($campo) || $producto->get($campo)->isEmpty()) {
        continue;
      }
      $media = $producto->get($campo)->entity;
      if ($media === NULL || !$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
        continue;
      }
      $fichero = $media->get('field_media_image')->entity;
      if ($fichero === NULL) {
        continue;
      }
      $estilo = $this->entityTypeManager->getStorage('image_style')->load(self::ESTILO);
      if ($estilo === NULL) {
        return NULL;
      }
      $cache->addCacheableDependency($media);

      return $estilo->buildUrl($fichero->getFileUri());
    }

    return NULL;
  }

  /**
   * El precio "desde": el mínimo de las variaciones, ya formateado.
   */
  private function precioDesde(ProductInterface $producto): ?string {
    $minimo = NULL;
    foreach ($producto->getVariations() as $variacion) {
      $precio = $variacion->getPrice();
      if ($precio instanceof Price && ($minimo === NULL || $precio->lessThan($minimo))) {
        $minimo = $precio;
      }
    }
    if ($minimo === NULL) {
      return NULL;
    }

    return $this->currencyFormatter->format($minimo->getNumber(), $minimo->getCurrencyCode());
  }

}
