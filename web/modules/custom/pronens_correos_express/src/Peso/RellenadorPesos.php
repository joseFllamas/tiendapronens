<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Peso;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Psr\Log\LoggerInterface;

/**
 * Siembra el peso de las variaciones que no lo tienen.
 *
 * No es un hook_update_N a propósito: son más de mil filas de datos estimados y
 * un despliegue las aplicaría en silencio. Se expone como botón con vista
 * previa en el formulario de ajustes, para que alguien mire los números antes.
 *
 * Nunca sobrescribe un peso existente: en cuanto el taller pese un artículo de
 * verdad, ese valor manda y esta clase deja de tocarlo.
 */
final class RellenadorPesos {

  /**
   * Variaciones que se cargan de golpe.
   */
  private const TAMANO_LOTE = 100;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ResolutorPesos $resolutorPesos,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Cuenta cuántas variaciones tienen peso y cuántas no.
   *
   * @return array{con_peso: int, sin_peso: int, total: int}
   *   El recuento.
   */
  public function recuento(): array {
    $almacen = $this->entityTypeManager->getStorage('commerce_product_variation');

    $total = (int) $almacen->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $sinPeso = (int) $almacen->getQuery()
      ->accessCheck(FALSE)
      // Con la propiedad explícita: physical_measurement no declara una
      // propiedad principal, así que sin ella la consulta genera una columna
      // inexistente.
      ->notExists('weight.number')
      ->count()
      ->execute();

    return [
      'con_peso' => $total - $sinPeso,
      'sin_peso' => $sinPeso,
      'total' => $total,
    ];
  }

  /**
   * Cuántos productos hay en cada tipo de producto.
   *
   * El vocabulario tiene treinta términos pero solo dieciocho se usan: el resto
   * son restos del Drupal 7 ("test", "Outlet", "Packs"). Sirve para no llenar
   * el formulario de ajustes con categorías que no pesan nada.
   *
   * @return array<int, int>
   *   Número de productos, indexado por identificador de término.
   */
  public function productosPorTipo(): array {
    // La API de consultas agregadas devuelve una fila por grupo, pero su
    // docblock no lo dice, así que hay que anotarlo para el análisis estático.
    /** @var list<array<string, mixed>> $filas */
    $filas = $this->entityTypeManager
      ->getStorage('commerce_product')
      ->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('field_tipo_de_producto')
      ->aggregate('product_id', 'COUNT')
      ->execute();

    $recuento = [];
    foreach ($filas as $fila) {
      $termino = $fila['field_tipo_de_producto_target_id'] ?? NULL;
      if (is_numeric($termino)) {
        $recuento[(int) $termino] = (int) ($fila['product_id_count'] ?? 0);
      }
    }

    return $recuento;
  }

  /**
   * Vista previa de lo que haría el relleno, agrupada por tipo de producto.
   *
   * @return array<int|string, array{termino: int|null, nombre: string, variaciones: int, gramos: int, estimado: bool}>
   *   Una fila por tipo de producto, indexada por su identificador. Los
   *   productos sin categoría van bajo la clave "sin_categoria".
   */
  public function vistaPrevia(): array {
    $tabla = $this->resolutorPesos->tablaPesos();
    $filas = [];

    foreach ($this->variacionesSinPeso() as $variacion) {
      $termino = $this->resolutorPesos->terminoTipoProducto($variacion);
      $clave = $termino === NULL ? 'sin_categoria' : (string) $termino;

      if (!isset($filas[$clave])) {
        $filas[$clave] = [
          'termino' => $termino,
          'nombre' => $this->nombreTermino($termino),
          'variaciones' => 0,
          'gramos' => $tabla->gramos($termino),
          'estimado' => $tabla->tieneEstimacion($termino),
        ];
      }
      $filas[$clave]['variaciones']++;
    }

    uasort($filas, static fn (array $a, array $b): int => $b['variaciones'] <=> $a['variaciones']);

    return $filas;
  }

  /**
   * Escribe el peso estimado en las variaciones que no tienen ninguno.
   *
   * @return int
   *   Cuántas variaciones se han actualizado.
   */
  public function rellenar(): int {
    $almacen = $this->entityTypeManager->getStorage('commerce_product_variation');
    $actualizadas = 0;

    foreach ($this->variacionesSinPeso() as $variacion) {
      $gramos = $this->resolutorPesos->gramosEstimados($variacion);
      $variacion->set('weight', new Weight((string) $gramos, WeightUnit::GRAM));
      $almacen->save($variacion);
      $actualizadas++;
    }

    $this->logger->notice('Peso estimado escrito en @numero variaciones que no tenían ninguno.', [
      '@numero' => $actualizadas,
    ]);

    return $actualizadas;
  }

  /**
   * Recorre las variaciones sin peso por lotes.
   *
   * @return \Generator<int, \Drupal\commerce_product\Entity\ProductVariationInterface>
   *   Las variaciones, de cien en cien para no cargarlas todas a la vez.
   */
  private function variacionesSinPeso(): \Generator {
    $almacen = $this->entityTypeManager->getStorage('commerce_product_variation');
    $ids = $almacen->getQuery()
      ->accessCheck(FALSE)
      // Con la propiedad explícita: physical_measurement no declara una
      // propiedad principal, así que sin ella la consulta genera una columna
      // inexistente.
      ->notExists('weight.number')
      ->sort('variation_id')
      ->execute();

    foreach (array_chunk(array_values($ids), self::TAMANO_LOTE) as $lote) {
      foreach ($almacen->loadMultiple($lote) as $variacion) {
        if ($variacion instanceof ProductVariationInterface) {
          yield $variacion;
        }
      }
    }
  }

  /**
   * Nombre legible de un término de tipo de producto.
   */
  private function nombreTermino(?int $termino): string {
    if ($termino === NULL) {
      return 'Sin categoría';
    }
    $entidad = $this->entityTypeManager->getStorage('taxonomy_term')->load($termino);

    return $entidad === NULL ? sprintf('Término %d', $termino) : (string) $entidad->label();
  }

}
