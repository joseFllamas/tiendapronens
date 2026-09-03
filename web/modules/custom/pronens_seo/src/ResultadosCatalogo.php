<?php

declare(strict_types=1);

namespace Drupal\pronens_seo;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\image\Entity\ImageStyle;

/**
 * Guarda qué productos ha pintado la view del catálogo en esta petición.
 *
 * El ItemList de la categoría necesita saber qué productos hay en la página y
 * en qué orden, y eso solo lo sabe la view. Repetir la consulta desde el hook
 * de attachments sería hacer dos veces el mismo trabajo y arriesgarse a que
 * las facetas o el paginador den un resultado distinto, así que se anota lo
 * que la view ya ha resuelto: el contenido principal se renderiza antes de que
 * se recojan los attachments de página, de modo que cuando JsonLdHooks
 * pregunta, esto ya está lleno.
 *
 * El nombre sale de la ENTIDAD, no de la tarjeta ya pintada: hay productos del
 * D7 con el alias cruzado (la sudadera blanca sirve en la URL de la rosa) y
 * leer el DOM llevaría ese cruce a los datos estructurados.
 */
final class ResultadosCatalogo {

  /**
   * Los productos de la página, en orden.
   *
   * @var array<int, array{url: string, nombre: string}>
   */
  private array $productos = [];

  /**
   * Cuántos hay en la categoría entera, no solo en esta página.
   */
  private ?int $total = NULL;

  /**
   * Desde qué posición del listado global empieza esta página.
   */
  private int $desde = 1;

  /**
   * Foto del primer producto, para el og:image de la categoría.
   */
  private ?string $foto = NULL;

  public function __construct(
    private readonly EntityRepositoryInterface $entityRepository,
  ) {
  }

  /**
   * Anota lo que ha devuelto la view.
   *
   * @param array<int, mixed> $resultados
   *   Las filas de la view, con la entidad en _entity.
   * @param int|null $total
   *   Total de la categoría, del paginador.
   * @param int $desde
   *   Posición del primer resultado de la página.
   */
  public function anota(array $resultados, ?int $total, int $desde): void {
    $this->total = $total;
    $this->desde = $desde;
    foreach ($resultados as $fila) {
      $entidad = $fila->_entity ?? NULL;
      if (!$entidad instanceof ProductInterface) {
        continue;
      }
      $producto = $this->entityRepository->getTranslationFromContext($entidad);
      \assert($producto instanceof ProductInterface);
      $this->productos[] = [
        'url' => $producto->toUrl('canonical', ['absolute' => TRUE])->toString(),
        'nombre' => (string) $producto->label(),
      ];
      if ($this->foto === NULL) {
        $this->foto = $this->fotoDe($producto);
      }
    }
  }

  /**
   * La foto del primer producto de la página, en el estilo de redes.
   *
   * 8 de los 30 términos llegaron de la migración sin imagen propia, así que
   * compartir esas categorías no daba ninguna vista previa. La foto de su
   * primer producto representa la categoría mejor que un logo genérico.
   */
  public function foto(): ?string {
    return $this->foto;
  }

  /**
   * Resuelve la foto principal de un producto en el estilo pronens_og.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $producto
   *   El producto.
   */
  private function fotoDe(ProductInterface $producto): ?string {
    if (!$producto->hasField('field_imagen_principal')) {
      return NULL;
    }
    $media = $producto->get('field_imagen_principal')->entity;
    if ($media === NULL || !$media->hasField('field_media_image')) {
      return NULL;
    }
    $fichero = $media->get('field_media_image')->entity;
    $estilo = ImageStyle::load('pronens_og');
    if ($fichero === NULL || $estilo === NULL) {
      return NULL;
    }

    return $estilo->buildUrl($fichero->getFileUri());
  }

  /**
   * Los productos anotados.
   *
   * @return array<int, array{url: string, nombre: string}>
   *   Los productos, en orden.
   */
  public function productos(): array {
    return $this->productos;
  }

  /**
   * El total de la categoría, o el número de productos de la página.
   */
  public function total(): int {
    return $this->total ?? count($this->productos);
  }

  /**
   * La posición del primer producto de la página.
   */
  public function desde(): int {
    return $this->desde;
  }

}
