<?php

declare(strict_types=1);

namespace Drupal\pronens_seo\Hook;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pronens_seo\Ofertas;

/**
 * Tokens del producto para el Product JSON-LD de schema_metatag.
 *
 * Los tokens de entidad de token.module solo llegan a la PRIMERA variación
 * ([commerce_product:variations:entity:sku] devuelve una), así que no hay
 * forma de sacar una Offer por variación desde la configuración. Estos tokens
 * devuelven las listas separadas por comas que schema_metatag "pivota" en N
 * objetos Offer, y así la configuración sigue viviendo en /admin/config/
 * search/metatag, editable, en vez de en código.
 */
final class TokenHooks {

  use StringTranslationTrait;

  private const TIPO = 'commerce_product';

  /**
   * Tokens que ofrece el módulo, con su descripción para la ayuda.
   *
   * @var array<string, string>
   */
  private const TOKENS = [
    'pronens-ofertas-precio' => 'Precios de todas las variaciones publicadas, separados por comas (para pivotar Offer en schema_metatag).',
    'pronens-ofertas-url' => 'URL con ?v=ID de cada variación, separadas por comas, alineadas con los precios.',
    'pronens-ofertas-disponibilidad' => 'InStock/OutOfStock de cada variación según el stock real, alineadas con los precios.',
    'pronens-precio-minimo' => 'El precio más bajo de las variaciones publicadas (el "desde" de la tarjeta).',
    'pronens-precio-maximo' => 'El precio más alto de las variaciones publicadas.',
    'pronens-ofertas-total' => 'Número de variaciones publicadas.',
    'pronens-sku' => 'SKU de la variación por defecto de la ficha.',
  ];

  public function __construct(
    private readonly Ofertas $ofertas,
    private readonly LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Implements hook_token_info().
   *
   * @return array<string, mixed>
   *   Definición de los tokens.
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $info = ['tokens' => [self::TIPO => []]];
    foreach (self::TOKENS as $nombre => $descripcion) {
      $info['tokens'][self::TIPO][$nombre] = [
        'name' => $this->t('Pronens SEO: @token', ['@token' => $nombre]),
        'description' => $descripcion,
      ];
    }

    return $info;
  }

  /**
   * Implements hook_tokens().
   *
   * @param string $type
   *   Tipo de token.
   * @param array<string, string> $tokens
   *   Tokens pedidos, nombre => token original.
   * @param array<string, mixed> $data
   *   Datos del contexto; aquí interesa 'commerce_product'.
   * @param array<string, mixed> $options
   *   Opciones de sustitución (langcode incluido).
   * @param \Drupal\Core\Render\BubbleableMetadata $metadata
   *   Metadatos de caché que se anotan.
   *
   * @return array<string, string>
   *   Sustituciones.
   */
  #[Hook('tokens')]
  public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $metadata): array {
    if ($type !== self::TIPO || !isset($data[self::TIPO]) || !$data[self::TIPO] instanceof ProductInterface) {
      return [];
    }
    $pedidos = array_intersect_key($tokens, self::TOKENS);
    if ($pedidos === []) {
      return [];
    }
    $producto = $data[self::TIPO];
    $idioma = !empty($options['langcode']) ? $this->languageManager->getLanguage($options['langcode']) : NULL;
    $listas = $this->ofertas->listas($producto, $idioma);
    $metadata->addCacheableDependency($producto);
    foreach ($producto->getVariations() as $variacion) {
      $metadata->addCacheableDependency($variacion);
    }
    // El stock cambia con cada venta y no tiene etiqueta de caché propia.
    $metadata->addCacheTags(['commerce_stock_transaction']);

    $valores = [
      'pronens-ofertas-precio' => $listas['precio'],
      'pronens-ofertas-url' => $listas['url'],
      'pronens-ofertas-disponibilidad' => $listas['disponibilidad'],
      'pronens-precio-minimo' => $listas['minimo'],
      'pronens-precio-maximo' => $listas['maximo'],
      'pronens-ofertas-total' => $listas['total'],
      'pronens-sku' => $this->ofertas->sku($producto),
    ];
    $sustituciones = [];
    foreach ($pedidos as $nombre => $original) {
      $sustituciones[$original] = $valores[$nombre];
    }

    return $sustituciones;
  }

}
