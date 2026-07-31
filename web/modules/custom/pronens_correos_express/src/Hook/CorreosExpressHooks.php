<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Hook;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\pronens_correos_express\Api\Entorno;
use Drupal\pronens_correos_express\Api\RepositorioCredenciales;
use Drupal\pronens_correos_express\GestorExpediciones;
use Drupal\pronens_correos_express\Peso\RellenadorPesos;
use Drupal\pronens_correos_express\SincronizadorSeguimiento;

/**
 * Ganchos del módulo.
 */
final class CorreosExpressHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly GestorExpediciones $gestorExpediciones,
    private readonly SincronizadorSeguimiento $sincronizador,
    private readonly RepositorioCredenciales $repositorioCredenciales,
    private readonly RellenadorPesos $rellenadorPesos,
    private readonly AccountInterface $usuarioActual,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Añade las operaciones de Correos Express a la lista de envíos.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entidad de la fila.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Metadatos de caché de la lista de operaciones.
   *
   * @return array<string, array<string, mixed>>
   *   Operaciones adicionales.
   */
  #[Hook('entity_operation')]
  public function operacionesDeEnvio(EntityInterface $entity, CacheableMetadata $cacheability): array {
    if (!$entity instanceof ShipmentInterface) {
      return [];
    }
    if (!$this->usuarioActual->hasPermission('generar expediciones correos express')) {
      return [];
    }
    $pedido = $entity->getOrder();
    if ($pedido === NULL) {
      return [];
    }

    // Lo que se ofrece depende del permiso de quien mira y de si el envío ya
    // tiene expedición, así que la lista no se puede cachear sin más.
    $cacheability->addCacheContexts(['user.permissions']);
    $cacheability->addCacheableDependency($entity);

    $parametros = [
      'commerce_order' => $pedido->id(),
      'commerce_shipment' => $entity->id(),
    ];

    if (!$this->gestorExpediciones->estaExpedido($entity)) {
      return [
        'pronens_cex_generar' => [
          'title' => $this->t('Generar expedición CEX'),
          'weight' => 40,
          'url' => Url::fromRoute('pronens_correos_express.generar', $parametros),
        ],
      ];
    }

    $operaciones = [
      'pronens_cex_etiqueta' => [
        'title' => $this->t('Etiqueta CEX'),
        'weight' => 40,
        'url' => Url::fromRoute('pronens_correos_express.etiqueta', $parametros),
      ],
    ];

    $codigo = $entity->getTrackingCode();
    if ($codigo !== NULL && $codigo !== '') {
      $operaciones['pronens_cex_seguimiento'] = [
        'title' => $this->t('Ver seguimiento'),
        'weight' => 41,
        'url' => Url::fromUri('https://s.correosexpress.com/c?n=' . $codigo),
      ];
    }

    return $operaciones;
  }

  /**
   * Encola la sincronización del seguimiento.
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->sincronizador->encolar();
  }

  /**
   * Avisa en el informe de estado de lo que falta o puede sorprender.
   *
   * @return array<string, array<string, mixed>>
   *   Requisitos del sistema.
   */
  #[Hook('runtime_requirements')]
  public function requisitos(): array {
    $requisitos = [];
    $entorno = Entorno::desdeConfiguracion(
      $this->configFactory->get('pronens_correos_express.settings')->get('entorno'),
    );

    $requisitos['pronens_cex_entorno'] = [
      'title' => $this->t('Correos Express: entorno'),
      'value' => $entorno->etiqueta(),
      // Aviso permanente en preproducción: es la forma de que sea imposible
      // creer que se está expidiendo de verdad cuando no es así, y al
      // contrario.
      'severity' => $entorno->esProduccion()
        ? RequirementSeverity::OK
        : RequirementSeverity::Warning,
      'description' => $entorno->esProduccion()
        ? $this->t('Cada alta crea un envío real y facturable.')
        : $this->t('Las expediciones que se creen no son reales y sus números de seguimiento no funcionan.'),
    ];

    $credenciales = $this->repositorioCredenciales->cargar();
    $requisitos['pronens_cex_credenciales'] = [
      'title' => $this->t('Correos Express: credenciales'),
      'value' => $credenciales->estanCompletas()
        ? $this->t('Configuradas')
        : $this->t('Faltan'),
      'severity' => $credenciales->estanCompletas()
        ? RequirementSeverity::OK
        : RequirementSeverity::Error,
      'description' => $credenciales->estanCompletas()
        ? NULL
        : $this->t('Sin las tres credenciales no se puede dar de alta ninguna expedición.'),
    ];

    $recuento = $this->rellenadorPesos->recuento();
    $requisitos['pronens_cex_pesos'] = [
      'title' => $this->t('Correos Express: pesos de los productos'),
      'value' => $this->t('@sin de @total variaciones sin peso', [
        '@sin' => $recuento['sin_peso'],
        '@total' => $recuento['total'],
      ]),
      'severity' => $recuento['sin_peso'] === 0
        ? RequirementSeverity::OK
        : RequirementSeverity::Warning,
      'description' => $recuento['sin_peso'] === 0
        ? NULL
        : $this->t('Los envíos de esos artículos viajan con el peso estimado de su tipo de producto. Correos Express factura por peso real, así que conviene pesarlos.'),
    ];

    // La extensión mbstring hace falta para convertir las respuestas en
    // ISO-8859-1, y sin ZipArchive no se pueden descargar las etiquetas de un
    // envío de varios bultos.
    foreach (['mbstring' => 'mb_convert_encoding', 'zip' => 'ZipArchive'] as $extension => $comprobacion) {
      $presente = $extension === 'zip'
        ? class_exists($comprobacion)
        : function_exists($comprobacion);
      if (!$presente) {
        $requisitos['pronens_cex_' . $extension] = [
          'title' => $this->t('Correos Express: extensión @extension', ['@extension' => $extension]),
          'value' => $this->t('No disponible'),
          'severity' => RequirementSeverity::Error,
        ];
      }
    }

    return $requisitos;
  }

}
