<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Hook;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Form\FormStateInterface;
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

  /**
   * El tipo de paquete de relleno que trae Commerce Shipping.
   *
   * Mide 1x1x1 MILÍMETROS y es un marcador de posición: contrib no sabe qué
   * cajas usa la tienda, así que declara una de tamaño nulo para que un método
   * de envío recién creado tenga algo con lo que arrancar. Está muy por debajo
   * del mínimo de Correos Express (15x10x1 cm).
   *
   * No se puede borrar: PackageTypeManager no llama a alterInfo(), o sea que
   * los tipos de paquete no tienen hook de alteración, y ShippingMethodBase
   * lo devuelve codificado a fuego en defaultConfiguration(), así que sin el
   * plugin reventaría la creación de cualquier método de envío. Lo que sí se
   * puede es quitarlo de los desplegables donde alguien lo elegiría sin querer.
   */
  private const PAQUETE_RELLENO = 'custom_box';

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

  /**
   * Esconde el tipo de paquete de relleno de contrib.
   *
   * Se quita de los dos sitios donde una persona lo puede elegir: el campo
   * «Package Type» del envío y el «Default package type» del método de envío,
   * que es de donde salió el problema. La primera expedición real de la tienda
   * se rechazó por llevarlo, y una expedición no se puede anular.
   *
   * Se deja visible cuando es el valor guardado: si se quitara la opción, el
   * formulario perdería el valor al abrirlo y lo cambiaría sin avisar.
   *
   * @param array<string, mixed> $form
   *   El formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   * @param string $form_id
   *   Identificador del formulario.
   */
  #[Hook('form_alter')]
  public function escondeElPaqueteDeRelleno(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!str_starts_with($form_id, 'commerce_shipment_') && !str_starts_with($form_id, 'commerce_shipping_method_')) {
      return;
    }

    $this->quitaOpcionDeRelleno($form);
  }

  /**
   * Recorre el formulario quitando la opción del paquete de relleno.
   *
   * Se busca por el contenido de #options y no por la ruta del elemento porque
   * los dos formularios lo colocan en sitios muy distintos: en el envío cuelga
   * de la raíz, y en el método de envío está seis niveles dentro, en la
   * configuración del plugin.
   *
   * @param array<string, mixed> $elemento
   *   Rama del formulario.
   */
  private function quitaOpcionDeRelleno(array &$elemento): void {
    foreach ($elemento as $clave => &$hijo) {
      if (!is_array($hijo) || str_starts_with((string) $clave, '#')) {
        continue;
      }
      $opciones = $hijo['#options'] ?? NULL;
      $esElValorGuardado = ($hijo['#default_value'] ?? NULL) === self::PAQUETE_RELLENO;
      if (is_array($opciones) && isset($opciones[self::PAQUETE_RELLENO]) && !$esElValorGuardado) {
        unset($hijo['#options'][self::PAQUETE_RELLENO]);
      }
      $this->quitaOpcionDeRelleno($hijo);
    }
  }

}
