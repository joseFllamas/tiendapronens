<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Peso;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\physical\Weight;

/**
 * Frontera entre las entidades de Commerce y la lógica pura de pesos.
 *
 * Es el único sitio que sabe de dónde salen los datos: el tipo de producto está
 * en el producto y no en la variación, y la talla es un valor de atributo. Ni
 * TablaPesos ni EstimadorPeso tocan el contenedor de servicios, así que la
 * traducción vive aquí.
 */
final class ResolutorPesos {

  /**
   * Campo del producto que guarda el tipo, y por tanto el peso estimado.
   */
  private const CAMPO_TIPO_PRODUCTO = 'field_tipo_de_producto';

  /**
   * Atributos de los que se puede leer una talla.
   *
   * Cada producto usa un solo eje, así que se prueban en orden y se coge el
   * primero que tenga valor.
   *
   * @var list<string>
   */
  private const CAMPOS_TALLA = ['attribute_talla', 'attribute_medida'];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Tabla de pesos con la configuración actual.
   */
  public function tablaPesos(): TablaPesos {
    $peso = $this->configuracionPeso();
    $porCategoria = [];
    if (is_array($peso['por_categoria'] ?? NULL)) {
      foreach ($peso['por_categoria'] as $termino => $gramos) {
        $porCategoria[(int) $termino] = (int) $gramos;
      }
    }

    return new TablaPesos($porCategoria, (int) ($peso['por_defecto_gramos'] ?? 300));
  }

  /**
   * Estimador con la configuración actual.
   */
  public function estimadorPeso(): EstimadorPeso {
    $peso = $this->configuracionPeso();

    return new EstimadorPeso(
      (int) ($peso['por_defecto_gramos'] ?? 300),
      (int) ($peso['minimo_envio_gramos'] ?? 100),
    );
  }

  /**
   * Peso estimado de una unidad de la entidad comprada, en gramos.
   */
  public function gramosEstimados(PurchasableEntityInterface $entidad): int {
    return $this->tablaPesos()->gramos(
      $this->terminoTipoProducto($entidad),
      $this->talla($entidad),
    );
  }

  /**
   * Peso real guardado en la entidad comprada, si tiene alguno.
   */
  public function pesoGuardado(PurchasableEntityInterface $entidad): ?Weight {
    if (!$entidad->hasField('weight') || $entidad->get('weight')->isEmpty()) {
      return NULL;
    }
    $primero = $entidad->get('weight')->first();
    if ($primero === NULL || !method_exists($primero, 'toMeasurement')) {
      return NULL;
    }
    $medida = $primero->toMeasurement();

    return $medida instanceof Weight ? $medida : NULL;
  }

  /**
   * Término del tipo de producto al que pertenece la entidad comprada.
   *
   * El campo está en el producto, no en la variación. Hay 33 productos sin
   * categoría, así que este método devuelve NULL con frecuencia y eso es
   * normal.
   */
  public function terminoTipoProducto(PurchasableEntityInterface $entidad): ?int {
    if (!$entidad instanceof ProductVariationInterface) {
      return NULL;
    }
    $producto = $entidad->getProduct();
    if ($producto === NULL || !$producto->hasField(self::CAMPO_TIPO_PRODUCTO)) {
      return NULL;
    }
    $campo = $producto->get(self::CAMPO_TIPO_PRODUCTO);
    if ($campo->isEmpty()) {
      return NULL;
    }
    $destino = $campo->first()?->get('target_id')?->getValue();

    return is_numeric($destino) ? (int) $destino : NULL;
  }

  /**
   * Etiqueta de la talla de la entidad comprada, si la tiene.
   */
  public function talla(PurchasableEntityInterface $entidad): ?string {
    foreach (self::CAMPOS_TALLA as $campo) {
      if (!$entidad->hasField($campo) || $entidad->get($campo)->isEmpty()) {
        continue;
      }
      $valor = $entidad->get($campo)->entity;
      if ($valor !== NULL) {
        return (string) $valor->label();
      }
    }

    return NULL;
  }

  /**
   * Sección de peso de la configuración.
   *
   * @return array<string, mixed>
   *   Los ajustes de peso.
   */
  private function configuracionPeso(): array {
    $peso = $this->configFactory->get('pronens_correos_express.settings')->get('peso');

    return is_array($peso) ? $peso : [];
  }

}
