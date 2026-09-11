<?php

declare(strict_types=1);

namespace Drupal\pronens_referral\Hook;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pronens_referral\AtribuidorDePedidos;

/**
 * Lo que el módulo añade a las páginas y al pedido de administración.
 */
final class ReferralHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AtribuidorDePedidos $atribuidor,
    private readonly DateFormatterInterface $dateFormatter,
  ) {
  }

  /**
   * Cuelga la captura de la library de Klaro.
   *
   * Así el JS de la captura se carga SIEMPRE que se carga Klaro, y antes que
   * él, que es lo que hace falta para que `Drupal.pronensReferral` exista
   * cuando Klaro dispara el callback del servicio. Colgarlo de una library del
   * tema o adjuntarlo suelto no garantizaría el orden entre extensiones, y
   * cargarlo donde Klaro no está (el backoffice) sería capturar sin gestor de
   * consentimiento delante.
   *
   * @param array<string, mixed> $libraries
   *   Las libraries de la extensión.
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension === 'klaro' && isset($libraries['klaro'])) {
      $libraries['klaro']['dependencies'][] = 'pronens_referral/captura';
    }
  }

  /**
   * Pasa al JS la fuente y la vida de la cookie.
   *
   * Son las mismas dos cosas que configura el cliente, así que el JS no
   * inventa ningún valor. Van en drupalSettings y no en el código porque el
   * nombre del partner y los 90 días son configuración.
   *
   * @param array<string, mixed> $attachments
   *   Los adjuntos de la página.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $config = $this->configFactory->get(AtribuidorDePedidos::NOMBRE_CONFIG);
    $attachments['#attached']['drupalSettings']['pronensReferral'] = [
      'activo' => (bool) $config->get('activo'),
      'cookie' => AtribuidorDePedidos::COOKIE,
      'fuente' => (string) ($config->get('fuente') ?? ''),
      'dias' => (int) ($config->get('dias_cookie') ?? 90),
    ];
    // El valor es el mismo para todo el mundo, así que la página se sigue
    // cacheando entera; solo hay que invalidarla al cambiar los ajustes.
    $attachments['#cache']['tags'][] = 'config:' . AtribuidorDePedidos::NOMBRE_CONFIG;
  }

  /**
   * Enseña la procedencia en la ficha de administración del pedido.
   *
   * Solo en el modo de vista `default`, que es el del backoffice: la ficha del
   * cliente usa `user` y el recibo `email`, y ahí no pinta nada de dónde vino
   * el clic. Los cinco campos siguen ocultos en todos los displays: lo que se
   * ve es este bloque, que los junta bajo un título y traduce el método.
   *
   * @param array<string, mixed> $build
   *   El render array del pedido.
   */
  #[Hook('commerce_order_view')]
  public function ordenView(array &$build, OrderInterface $pedido, EntityViewDisplayInterface $display, string $viewMode): void {
    if ($viewMode !== 'default' || !$pedido->hasField(AtribuidorDePedidos::CAMPO_FUENTE)) {
      return;
    }
    if ($pedido->get(AtribuidorDePedidos::CAMPO_FUENTE)->isEmpty()) {
      return;
    }
    $metodos = [
      'cookie' => $this->t('enlace de @fuente', ['@fuente' => $pedido->get(AtribuidorDePedidos::CAMPO_FUENTE)->value]),
      'cupon' => $this->t('cupón del convenio'),
      'cookie+cupon' => $this->t('enlace y cupón'),
    ];
    $metodo = (string) ($pedido->get(AtribuidorDePedidos::CAMPO_METODO)->value ?? '');
    $filas = [
      [$this->t('Origen'), (string) $pedido->get(AtribuidorDePedidos::CAMPO_FUENTE)->value],
      [$this->t('Reconocido por'), $metodos[$metodo] ?? $metodo],
      [$this->t('Colocación'), (string) ($pedido->get(AtribuidorDePedidos::CAMPO_COLOCACION)->value ?? '—')],
      [$this->t('Creatividad'), (string) ($pedido->get(AtribuidorDePedidos::CAMPO_CREATIVIDAD)->value ?? '—')],
    ];
    $visto = $pedido->get(AtribuidorDePedidos::CAMPO_VISTO)->value;
    if ($visto !== NULL && $visto !== '') {
      $filas[] = [
        $this->t('Llegó a la tienda'),
        $this->dateFormatter->format((int) $visto, 'short'),
      ];
    }
    $build['pronens_procedencia'] = [
      '#type' => 'details',
      '#title' => $this->t('Procedencia'),
      '#open' => TRUE,
      '#weight' => 13,
      'tabla' => [
        '#type' => 'table',
        '#rows' => array_map(static fn (array $fila): array => [
          ['header' => TRUE, 'data' => $fila[0]],
          $fila[1] === '' ? '—' : $fila[1],
        ], $filas),
      ],
    ];
  }

  /**
   * Expone el mes de compra como columna de Views.
   *
   * @param array<string, mixed> $data
   *   Los datos de Views.
   *
   * @see \Drupal\pronens_referral\Plugin\views\field\MesDeCompra
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    $data['commerce_order']['pronens_mes_de_compra'] = [
      'title' => $this->t('Mes de compra'),
      'help' => $this->t('El mes en el que se colocó el pedido, «2026-09», para agrupar el informe por meses.'),
      'field' => [
        'id' => 'pronens_mes_de_compra',
        'real field' => 'placed',
        'click sortable' => TRUE,
      ],
    ];
  }

  /**
   * Red de seguridad: rellena el pedido colocado al que le falte la marca.
   *
   * @see \Drupal\pronens_referral\AtribuidorDePedidos::rellenarSiFalta()
   */
  #[Hook('commerce_order_presave')]
  public function ordenPresave(OrderInterface $pedido): void {
    $this->atribuidor->rellenarSiFalta($pedido);
  }

}
