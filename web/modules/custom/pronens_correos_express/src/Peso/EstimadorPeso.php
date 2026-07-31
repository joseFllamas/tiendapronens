<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Peso;

use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;

/**
 * Rellena los pesos que faltan al preparar un envío.
 *
 * Es la última red antes de llamar a la API. Sin ella, DefaultPacker pone cero
 * gramos en cada línea sin peso y la expedición entera sale a cero, que es lo
 * que pasa hoy en las 1096 variaciones.
 *
 * Lógica pura y sin dependencias: se prueba con PHPUnit sin contenedor.
 */
final class EstimadorPeso {

  public function __construct(
    private readonly int $pesoPorDefectoGramos,
    private readonly int $pesoMinimoEnvioGramos,
  ) {}

  /**
   * Peso de una unidad, con el estimado cuando no hay dato.
   *
   * @param \Drupal\physical\Weight|null $pesoVariacion
   *   Peso guardado en la variación, si lo tiene.
   * @param int|null $estimadoGramos
   *   Estimación por categoría, si se conoce.
   */
  public function pesoUnitario(?Weight $pesoVariacion, ?int $estimadoGramos = NULL): Weight {
    if ($pesoVariacion !== NULL && $this->esPositivo($pesoVariacion)) {
      return $pesoVariacion;
    }

    $gramos = $estimadoGramos !== NULL && $estimadoGramos > 0
      ? $estimadoGramos
      : $this->pesoPorDefectoGramos;

    return new Weight((string) max(1, $gramos), WeightUnit::GRAM);
  }

  /**
   * Peso de una línea de pedido completa.
   *
   * @param \Drupal\physical\Weight|null $pesoVariacion
   *   Peso guardado en la variación, si lo tiene.
   * @param string $cantidad
   *   Unidades de la línea, tal como las guarda Commerce.
   * @param int|null $estimadoGramos
   *   Estimación por categoría, si se conoce.
   */
  public function pesoLinea(?Weight $pesoVariacion, string $cantidad, ?int $estimadoGramos = NULL): Weight {
    $unitario = $this->pesoUnitario($pesoVariacion, $estimadoGramos);
    $unidades = max(1.0, (float) $cantidad);

    return $unitario->multiply((string) $unidades);
  }

  /**
   * Peso de la expedición, aplicando el mínimo.
   *
   * Correos Express rechaza un envío a cero kilos.
   *
   * @param \Drupal\physical\Weight|null $sumaLineas
   *   Suma de los pesos de las líneas, incluida la tara del embalaje.
   */
  public function pesoEnvio(?Weight $sumaLineas): Weight {
    $minimo = new Weight((string) max(1, $this->pesoMinimoEnvioGramos), WeightUnit::GRAM);
    if ($sumaLineas === NULL) {
      return $minimo;
    }

    $enGramos = $sumaLineas->convert(WeightUnit::GRAM);

    return $enGramos->greaterThan($minimo) ? $sumaLineas : $minimo;
  }

  /**
   * Indica si un peso aporta algo.
   *
   * Un peso a cero es lo mismo que no tenerlo: es lo que deja DefaultPacker en
   * las variaciones sin dato.
   */
  private function esPositivo(Weight $peso): bool {
    return (float) $peso->convert(WeightUnit::GRAM)->getNumber() > 0.0;
  }

}
