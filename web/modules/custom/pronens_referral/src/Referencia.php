<?php

declare(strict_types=1);

namespace Drupal\pronens_referral;

/**
 * Lo que se sabe de la llegada desde el partner: nada personal.
 *
 * Los cuatro datos son los del contrato de educoland (`utm_source`,
 * `utm_campaign`, `utm_content` y el momento del clic). No hay identificador de
 * visitante, ni correo, ni nada que permita reconocer a nadie: ese es justo el
 * motivo de que la atribución se haga con UTM y no con la cookie de afiliación
 * que educoland retiró.
 */
final readonly class Referencia {

  /**
   * Construye la referencia con los datos del contrato de educoland.
   *
   * @param string $fuente
   *   El partner (`educoland`).
   * @param string $colocacion
   *   Dónde estaba el enlace (`utm_campaign`): `ficha_publica`, `email_alta`….
   * @param string $creatividad
   *   Qué banner e imagen (`utm_content`): `banner12-s31183`. Puede ir vacía.
   * @param int $visto
   *   Cuándo llegó el visitante, en segundos desde 1970.
   */
  public function __construct(
    public string $fuente,
    public string $colocacion,
    public string $creatividad,
    public int $visto,
  ) {
  }

}
