<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pronens_correos_express\Api\Entorno;
use Drupal\pronens_correos_express\Api\RepositorioCredenciales;
use Drupal\pronens_correos_express\Peso\RellenadorPesos;
use Drupal\pronens_correos_express\Peso\TablaPesos;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ajustes de la integración con Correos Express.
 */
final class AjustesForm extends ConfigFormBase {

  private const NOMBRE_CONFIG = 'pronens_correos_express.settings';

  /**
   * Vocabulario del que salen los pesos estimados.
   */
  private const VOCABULARIO_TIPO_PRODUCTO = 'tipo_de_producto';

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    // Protegidas y no privadas: DependencySerializationTrait, que usan los
    // formularios al serializarse, no soporta propiedades privadas.
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RellenadorPesos $rellenadorPesos,
    protected RepositorioCredenciales $repositorioCredenciales,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get(RellenadorPesos::class),
      $container->get(RepositorioCredenciales::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pronens_correos_express_ajustes';
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int, string>
   *   Los nombres de configuración editables.
   */
  protected function getEditableConfigNames(): array {
    return [self::NOMBRE_CONFIG];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Estructura del formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   *
   * @return array<string, mixed>
   *   El formulario.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::NOMBRE_CONFIG);
    $entorno = Entorno::desdeConfiguracion($config->get('entorno'));

    if (!$this->repositorioCredenciales->cargar()->estanCompletas()) {
      $this->messenger()->addWarning($this->t('Faltan las credenciales de Correos Express. Sin ellas no se puede dar de alta ninguna expedición.'));
    }
    if (!$entorno->esProduccion()) {
      $this->messenger()->addWarning($this->t('Estás en preproducción: las expediciones que se den de alta no son reales y sus números de seguimiento no funcionan.'));
    }

    $form['entorno'] = [
      '#type' => 'radios',
      '#title' => $this->t('Entorno de la API'),
      '#options' => [
        Entorno::Pre->value => $entorno::Pre->etiqueta(),
        Entorno::Pro->value => $entorno::Pro->etiqueta(),
      ],
      '#default_value' => $entorno->value,
      '#required' => TRUE,
      '#description' => $this->t('En producción cada alta crea un envío real que Correos Express factura, y la API no permite anular expediciones.'),
    ];

    $form['remitente'] = [
      '#type' => 'details',
      '#title' => $this->t('Datos del remitente'),
      '#description' => $this->t('La dirección y el nombre salen de la tienda. Estos son los que la tienda no guarda en ningún sitio y Correos Express pide en el alta.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $remitente = $config->get('remitente');
    $form['remitente']['nombre'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nombre o razón social'),
      '#description' => $this->t('Déjalo en blanco para usar el nombre de la tienda.'),
      '#default_value' => $remitente['nombre'] ?? '',
    ];
    $form['remitente']['nif'] = [
      '#type' => 'textfield',
      '#title' => $this->t('NIF o CIF'),
      '#default_value' => $remitente['nif'] ?? '',
      '#maxlength' => 15,
    ];
    $form['remitente']['contacto'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Persona de contacto'),
      '#description' => $this->t('A quién llama Correos Express si hay un problema con la recogida.'),
      '#default_value' => $remitente['contacto'] ?? '',
    ];
    $form['remitente']['telefono'] = [
      '#type' => 'tel',
      '#title' => $this->t('Teléfono'),
      '#default_value' => $remitente['telefono'] ?? '',
    ];
    $form['remitente']['correo'] = [
      '#type' => 'email',
      '#title' => $this->t('Correo electrónico'),
      '#description' => $this->t('Déjalo en blanco para usar el de la tienda.'),
      '#default_value' => $remitente['correo'] ?? '',
    ];

    $form['etiqueta'] = [
      '#type' => 'details',
      '#title' => $this->t('Etiqueta'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];
    $etiqueta = $config->get('etiqueta');
    $form['etiqueta']['tipo'] = [
      '#type' => 'radios',
      '#title' => $this->t('Formato'),
      // Los cinco formatos oficiales de apiRestEtiquetaTransporte.
      '#options' => [
        1 => $this->t('PDF de 10x15 cm, una etiqueta por hoja'),
        5 => $this->t('PDF térmico, para imprimir en papel de etiquetas'),
        2 => $this->t('ZPL, el fichero nativo de las impresoras de etiquetas Zebra'),
        3 => $this->t('PDF adhesivo, tres etiquetas por hoja A4'),
        4 => $this->t('PDF de medio folio, dos etiquetas por hoja A4'),
      ],
      '#default_value' => (int) ($etiqueta['tipo'] ?? 1),
      '#description' => $this->t('Con ZPL se descarga un fichero .zpl que se manda a la impresora tal cual; el resto son PDF.'),
      '#required' => TRUE,
    ];
    $form['etiqueta']['posicion'] = [
      '#type' => 'number',
      '#title' => $this->t('Posición de la primera etiqueta'),
      '#min' => 1,
      '#max' => 3,
      '#default_value' => (int) ($etiqueta['posicion'] ?? 1),
      '#description' => $this->t('Para aprovechar una hoja empezada. La hoja adhesiva tiene tres posiciones y la de medio folio dos.'),
      '#states' => [
        'visible' => [
          [':input[name="etiqueta[tipo]"]' => ['value' => '3']],
          [':input[name="etiqueta[tipo]"]' => ['value' => '4']],
        ],
      ],
    ];
    $form['etiqueta']['ocultar_remitente'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('No imprimir el remitente'),
      '#default_value' => (bool) ($etiqueta['ocultar_remitente'] ?? FALSE),
    ];
    $form['etiqueta']['texto_remitente_alternativo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remitente alternativo'),
      '#description' => $this->t('Se imprime en lugar del remitente real, por ejemplo para envíos en nombre de otra marca.'),
      '#default_value' => $etiqueta['texto_remitente_alternativo'] ?? '',
      '#maxlength' => 60,
    ];

    $form['peso'] = [
      '#type' => 'details',
      '#title' => $this->t('Pesos'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $recuento = $this->rellenadorPesos->recuento();
    $form['peso']['estado'] = [
      '#type' => 'item',
      '#markup' => $this->t('@sin de @total variaciones no tienen peso. Correos Express exige kilos en el alta, así que las que falten se envían con el peso estimado de su tipo de producto.', [
        '@sin' => $recuento['sin_peso'],
        '@total' => $recuento['total'],
      ]),
    ];
    $form['peso']['por_defecto_gramos'] = [
      '#type' => 'number',
      '#title' => $this->t('Peso por unidad por defecto'),
      '#field_suffix' => $this->t('gramos'),
      '#min' => 1,
      '#default_value' => (int) $config->get('peso.por_defecto_gramos'),
      '#required' => TRUE,
      '#description' => $this->t('Se usa cuando el artículo no tiene peso y su tipo de producto tampoco.'),
    ];
    $form['peso']['minimo_envio_gramos'] = [
      '#type' => 'number',
      '#title' => $this->t('Peso mínimo del envío'),
      '#field_suffix' => $this->t('gramos'),
      '#min' => 1,
      '#default_value' => (int) $config->get('peso.minimo_envio_gramos'),
      '#required' => TRUE,
      '#description' => $this->t('Correos Express rechaza un envío a cero kilos.'),
    ];

    $configurados = $config->get('peso.por_categoria');
    $conProductos = $this->rellenadorPesos->productosPorTipo();
    $enUso = [];
    $sinUso = [];
    foreach ($this->tiposDeProducto() as $tid => $nombre) {
      $semilla = TablaPesos::semilla($nombre);
      $productos = $conProductos[$tid] ?? 0;
      $campo = [
        '#type' => 'number',
        '#title' => $nombre,
        '#field_suffix' => $this->t('g'),
        '#min' => 0,
        '#default_value' => (int) ($configurados[$tid] ?? $semilla ?? 0),
        '#description' => $semilla === NULL
          ? $this->t('@productos productos. Sin estimación de partida.', ['@productos' => $productos])
          : $this->t('@productos productos. Estimación de partida: @g g.', [
            '@productos' => $productos,
            '@g' => $semilla,
          ]),
      ];

      if ($productos > 0) {
        $enUso[$tid] = $campo;
      }
      else {
        $sinUso[$tid] = $campo;
      }
    }

    // Las categorías sin productos se apartan: el vocabulario tiene treinta
    // términos y solo dieciocho se usan, así que mezclarlos convierte el
    // formulario en una lista imposible de revisar.
    $form['peso']['por_categoria'] = $enUso + [
      '#type' => 'details',
      '#title' => $this->t('Peso por tipo de producto'),
      '#description' => $this->t('Gramos que pesa una unidad. Los valores sugeridos son estimaciones: en cuanto el taller pese una pieza de cada tipo, cámbialos por los reales, porque el transportista factura por peso medido.'),
      '#open' => TRUE,
      '#tree' => TRUE,
      'sin_uso' => $sinUso + [
        '#type' => 'details',
        '#title' => $this->t('Categorías que ahora no tienen productos'),
        '#open' => FALSE,
        '#tree' => TRUE,
        '#weight' => 100,
      ],
    ];

    // La vista previa se pinta solo cuando se pide: son más de mil filas de
    // datos estimados y conviene mirarlas antes de escribirlas.
    if ($form_state->get('mostrar_previa') === TRUE) {
      $form['peso']['previa'] = $this->tablaVistaPrevia();
    }

    $form['peso']['acciones'] = [
      '#type' => 'actions',
      'previa' => [
        '#type' => 'submit',
        '#value' => $this->t('Ver qué pesos se escribirían'),
        '#submit' => ['::verPrevia'],
        '#limit_validation_errors' => [],
      ],
      'rellenar' => [
        '#type' => 'submit',
        '#value' => $this->t('Escribir los pesos que faltan'),
        '#submit' => ['::rellenar'],
        '#limit_validation_errors' => [],
        '#access' => $recuento['sin_peso'] > 0,
      ],
    ];

    $form['seguimiento'] = [
      '#type' => 'details',
      '#title' => $this->t('Seguimiento'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];
    $form['seguimiento']['activo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sincronizar el seguimiento automáticamente'),
      '#default_value' => (bool) $config->get('seguimiento.activo'),
      '#description' => $this->t('Consulta el estado de los envíos ya expedidos y los marca como enviados cuando el paquete se mueve.'),
    ];
    $form['seguimiento']['intervalo_horas'] = [
      '#type' => 'number',
      '#title' => $this->t('Horas entre sincronizaciones'),
      '#min' => 1,
      '#max' => 168,
      '#default_value' => (int) $config->get('seguimiento.intervalo_horas'),
      '#required' => TRUE,
    ];
    $form['seguimiento']['envios_por_ejecucion'] = [
      '#type' => 'number',
      '#title' => $this->t('Envíos consultados cada vez'),
      '#min' => 1,
      '#max' => 200,
      '#default_value' => (int) $config->get('seguimiento.envios_por_ejecucion'),
      '#required' => TRUE,
      '#description' => $this->t('Cada envío es una llamada a la API, así que conviene no pasarse.'),
    ];

    $form['registro_detallado'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Registrar las peticiones completas en el log'),
      '#default_value' => (bool) $config->get('registro_detallado'),
      '#description' => $this->t('Solo para localizar un error de la API. El teléfono, el correo y la dirección del cliente se sustituyen por asteriscos.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Estructura del formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $remitente = $form_state->getValue('remitente');
    $etiqueta = $form_state->getValue('etiqueta');
    $peso = $form_state->getValue('peso');
    $seguimiento = $form_state->getValue('seguimiento');

    // Las categorías sin productos vienen dentro de su propio grupo, así que se
    // aplanan antes de guardar: en la configuración todas son iguales.
    $categorias = $peso['por_categoria'] ?? [];
    $sinUso = $categorias['sin_uso'] ?? [];
    unset($categorias['sin_uso']);

    $porCategoria = [];
    foreach ($categorias + $sinUso as $tid => $gramos) {
      // Los ceros no se guardan: un cero no es una estimación, es "no lo sé", y
      // guardarlo escondería que falta el dato.
      if ((int) $gramos > 0) {
        $porCategoria[(string) $tid] = (int) $gramos;
      }
    }

    $this->config(self::NOMBRE_CONFIG)
      ->set('entorno', (string) $form_state->getValue('entorno'))
      ->set('remitente', [
        'nombre' => trim((string) ($remitente['nombre'] ?? '')),
        'nif' => trim((string) ($remitente['nif'] ?? '')),
        'contacto' => trim((string) ($remitente['contacto'] ?? '')),
        'telefono' => trim((string) ($remitente['telefono'] ?? '')),
        'correo' => trim((string) ($remitente['correo'] ?? '')),
      ])
      ->set('etiqueta', [
        'tipo' => (int) ($etiqueta['tipo'] ?? 1),
        'posicion' => (int) ($etiqueta['posicion'] ?? 1),
        'ocultar_remitente' => (bool) ($etiqueta['ocultar_remitente'] ?? FALSE),
        'texto_remitente_alternativo' => trim((string) ($etiqueta['texto_remitente_alternativo'] ?? '')),
        'logo_base64' => (string) $this->config(self::NOMBRE_CONFIG)->get('etiqueta.logo_base64'),
      ])
      ->set('peso', [
        'por_defecto_gramos' => (int) ($peso['por_defecto_gramos'] ?? 300),
        'minimo_envio_gramos' => (int) ($peso['minimo_envio_gramos'] ?? 100),
        'por_categoria' => $porCategoria,
      ])
      ->set('seguimiento', [
        'activo' => (bool) ($seguimiento['activo'] ?? TRUE),
        'intervalo_horas' => (int) ($seguimiento['intervalo_horas'] ?? 6),
        'envios_por_ejecucion' => (int) ($seguimiento['envios_por_ejecucion'] ?? 25),
      ])
      ->set('registro_detallado', (bool) $form_state->getValue('registro_detallado'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Muestra la vista previa del relleno de pesos.
   *
   * @param array<string, mixed> $form
   *   Estructura del formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  public function verPrevia(array &$form, FormStateInterface $form_state): void {
    $form_state->set('mostrar_previa', TRUE);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Escribe los pesos estimados en las variaciones que no tienen ninguno.
   *
   * @param array<string, mixed> $form
   *   Estructura del formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  public function rellenar(array &$form, FormStateInterface $form_state): void {
    $actualizadas = $this->rellenadorPesos->rellenar();
    $this->messenger()->addStatus($this->t('Escrito el peso estimado en @numero variaciones. Las que ya tenían peso no se han tocado.', [
      '@numero' => $actualizadas,
    ]));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Construye la tabla de la vista previa.
   *
   * @return array<string, mixed>
   *   Un elemento de tabla.
   */
  private function tablaVistaPrevia(): array {
    $filas = [];
    foreach ($this->rellenadorPesos->vistaPrevia() as $fila) {
      $filas[] = [
        $fila['nombre'],
        $fila['variaciones'],
        $this->t('@g g', ['@g' => $fila['gramos']]),
        $fila['estimado'] ? $this->t('configurado') : $this->t('por defecto'),
      ];
    }

    return [
      '#type' => 'table',
      '#caption' => $this->t('Esto es lo que se escribiría. Guarda los pesos por tipo de producto antes si quieres cambiar algún número.'),
      '#header' => [
        $this->t('Tipo de producto'),
        $this->t('Variaciones sin peso'),
        $this->t('Peso por unidad'),
        $this->t('Origen del peso'),
      ],
      '#rows' => $filas,
      '#empty' => $this->t('Todas las variaciones tienen peso.'),
    ];
  }

  /**
   * Tipos de producto que tienen productos publicados.
   *
   * @return array<int, string>
   *   Nombre del término, indexado por su identificador.
   */
  private function tiposDeProducto(): array {
    $terminos = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => self::VOCABULARIO_TIPO_PRODUCTO]);

    $opciones = [];
    foreach ($terminos as $termino) {
      $opciones[(int) $termino->id()] = (string) $termino->label();
    }
    asort($opciones);

    return $opciones;
  }

}
