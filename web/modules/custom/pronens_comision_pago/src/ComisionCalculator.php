<?php

declare(strict_types=1);

namespace Drupal\pronens_comision_pago;

use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\Price;

/**
 * Calcula la comisión que se suma al total según el medio de pago elegido.
 *
 * Es lógica pura y sin dependencias, como SurchargeCalculator: quien la usa le
 * entrega el total ya cerrado y el porcentaje configurado. Así las reglas de
 * dinero se prueban con PHPUnit sin contenedor ni base de datos.
 *
 * A diferencia del recargo del bordado, esta comisión NO mira las líneas: es un
 * porcentaje del total del pedido, que es exactamente sobre lo que PayPal cobra
 * su tarifa (prenda, bordado, extras, envío e IVA incluidos).
 */
final class ComisionCalculator {

  /**
   * Decide si la pasarela elegida repercute comisión.
   *
   * @param string|null $pasarela
   *   Identificador de la pasarela elegida, o NULL si aún no hay ninguna.
   * @param array<int, string> $pasarelas_con_comision
   *   Identificadores de las pasarelas configuradas para repercutirla.
   */
  public function aplicaA(?string $pasarela, array $pasarelas_con_comision): bool {
    if ($pasarela === NULL || $pasarela === '') {
      return FALSE;
    }

    return in_array($pasarela, $pasarelas_con_comision, TRUE);
  }

  /**
   * Calcula el importe de la comisión.
   *
   * @param \Drupal\commerce_price\Price|null $total
   *   Total del pedido con todo dentro. Es la base real de la tarifa de la
   *   pasarela, que cobra sobre lo que cobra, no sobre el subtotal.
   * @param string $porcentaje
   *   Porcentaje configurado, como cadena para no perder precisión.
   *
   * @return \Drupal\commerce_price\Price|null
   *   El importe a sumar, sin redondear, o NULL si no hay nada que cobrar. El
   *   redondeo lo hace quien llama con el servicio de Commerce, que es el único
   *   que sabe cuántos decimales admite la moneda.
   */
  public function calculate(?Price $total, string $porcentaje): ?Price {
    if ($total === NULL || !$total->isPositive()) {
      return NULL;
    }
    // is_numeric() no basta: acepta notación científica ("1e2") y bcmath, que
    // es quien hace la división, no la entiende y devolvería cero en silencio.
    if (!self::esPorcentajeValido($porcentaje) || Calculator::compare($porcentaje, '0') <= 0) {
      return NULL;
    }

    // Seis decimales de escala: con un total de 60 € y un 1,5% el resultado es
    // 0,9 exacto, pero con porcentajes como el 2,9% + IVA conviene no truncar
    // antes de que redondee Commerce.
    return $total->multiply(Calculator::divide($porcentaje, '100', 6));
  }

  /**
   * Formatea el porcentaje para enseñarlo al cliente.
   *
   * Coma decimal y sin ceros de relleno: 1.5 se lee "1,5" y 3 se lee "3", no
   * "1,50" ni "3,00", que es como lo escribe cualquier tienda española.
   */
  public function formatearPorcentaje(string $porcentaje): string {
    if (!self::esPorcentajeValido($porcentaje)) {
      return $porcentaje;
    }

    $formateado = number_format((float) $porcentaje, 2, ',', '');
    $formateado = rtrim($formateado, '0');

    return rtrim($formateado, ',');
  }

  /**
   * Comprueba que el porcentaje es un decimal que bcmath sabe leer.
   */
  public static function esPorcentajeValido(string $porcentaje): bool {
    return (bool) preg_match('/^\d+(\.\d+)?$/', $porcentaje);
  }

}
