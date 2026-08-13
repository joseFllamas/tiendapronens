<?php

declare(strict_types=1);

namespace Drupal\pronens_mail;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * En qué idioma se le escribe al cliente de un pedido.
 *
 * Hace falta porque el pedido no guarda idioma: `commerce_order` no tiene
 * columna `langcode` y el checkout es de invitado, así que ni la entidad ni la
 * cuenta dicen en qué idioma se compró. Lo apunta IdiomaPedidoSubscriber al
 * confirmar el pedido y aquí se lee, con tres redes de seguridad detrás para
 * los pedidos que ya existían antes de que esto se montara.
 */
class IdiomaPedido {

  /**
   * Clave del idioma de compra en la columna `data` del pedido.
   */
  public const CLAVE = 'pronens_langcode';

  public function __construct(
    protected LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Idioma en el que hay que escribir al cliente de un pedido.
   */
  public function resolver(OrderInterface $pedido): string {
    $cliente = $pedido->getCustomer();

    return $this->elegir(
      $pedido->getData(self::CLAVE),
      $cliente->isAuthenticated() ? $cliente->getPreferredLangcode(FALSE) : NULL,
      $this->languageManager->getCurrentLanguage()->getId(),
      $this->languageManager->getDefaultLanguage()->getId(),
      array_keys($this->languageManager->getLanguages()),
    );
  }

  /**
   * La elección, sin dependencias, para poder probarla.
   *
   * El idioma de la compra manda sobre el de la cuenta a propósito: los 1578
   * clientes migrados del D7 tienen `preferred_langcode` en castellano porque
   * el D7 no lo guardaba, así que su preferencia es un valor por defecto y no
   * una elección. El idioma de interfaz es el último recurso antes del del
   * sitio, y no vale por sí solo: los avisos de expedición salen del cron.
   *
   * @param string|null $guardado
   *   Idioma apuntado en el pedido al confirmarlo.
   * @param string|null $preferido
   *   Idioma preferido del cliente, si tiene cuenta. FALSE en
   *   getPreferredLangcode() para que devuelva null en vez del del sitio.
   * @param string $actual
   *   Idioma de la interfaz en curso.
   * @param string $porDefecto
   *   Idioma por defecto del sitio.
   * @param array<int, string> $disponibles
   *   Idiomas configurados en el sitio.
   */
  public function elegir(?string $guardado, ?string $preferido, string $actual, string $porDefecto, array $disponibles): string {
    foreach ([$guardado, $preferido, $actual] as $candidato) {
      if ($candidato !== NULL && $candidato !== '' && in_array($candidato, $disponibles, TRUE)) {
        return $candidato;
      }
    }

    return $porDefecto;
  }

}
