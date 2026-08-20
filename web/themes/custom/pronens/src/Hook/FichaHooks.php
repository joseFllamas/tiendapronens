<?php

namespace Drupal\pronens\Hook;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\TermInterface;
use Drupal\pronens\CamposTrait;
use Drupal\pronens\PrecioTrait;
use Drupal\pronens\TraduccionTrait;

/**
 * Hooks de la ficha de producto.
 *
 * Aparte de PronensHooks porque la ficha es la pantalla más grande y mezcla
 * dos cosas distintas: los datos del producto (galería, acordeones,
 * relacionados) y la reordenación del formulario de añadir al carrito, que es
 * del módulo pronens_personalizacion y aquí solo se agrupa y se viste.
 */
class FichaHooks {

  use CamposTrait;
  use PrecioTrait;
  use StringTranslationTrait;
  use TraduccionTrait;

  /**
   * Cuántas fotos entran en la cuadrícula de la galería.
   *
   * El prototipo dibuja seis huecos; con menos fotos la cuadrícula se ajusta.
   */
  protected const MAX_FOTOS = 6;

  /**
   * Letras que se pueden bordar.
   *
   * El alfabeto entero: la competencia deja la X fuera, pero Ximena y Xavier
   * existen. Si el taller no tiene parche de alguna letra, se quita de aquí.
   */
  protected const LETRAS = [
    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
    'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
  ];

  /**
   * Library que trae cada fuente de bordado.
   *
   * Cada una es un solo @font-face y se carga solo en la ficha que la usa: son
   * tres WOFF2 de entre 6 y 10 KB y no tiene sentido servir los tres.
   */
  protected const FUENTES = [
    'unicase' => 'pronens/delius',
    'script' => 'pronens/caveat',
    'letra' => 'pronens/graduate',
  ];

  /**
   * Fuente con la que se borda un nombre cuando el producto no dice otra.
   *
   * Unicase, que es como sale del taller: la misma altura para la caja alta y la
   * baja. Los 279 productos de nombre migrados no traen valor, así que este es
   * el que se les aplica; poniéndoles "Cursiva" vuelven a la Caveat de antes.
   */
  protected const FUENTE_DEFECTO = 'unicase';

  /**
   * Tamaño de partida del bordado en cada modo, en % del ancho de la foto.
   *
   * En inicial es el lado del parche y en nombre la altura de la letra. Son los
   * mismos que la barra del widget del backoffice (MontajeHooks).
   */
  protected const TAMANO_DEFECTO = [
    'inicial' => 12.0,
    'texto' => 5.0,
  ];

  /**
   * Ancho de partida del fondo del bordado, en % del ancho de la foto.
   *
   * El mismo que la barra del widget del backoffice (MontajeHooks) y que el
   * valor por defecto de field_fondo_tamano.
   */
  protected const FONDO_ANCHO_DEFECTO = 34.0;

  /**
   * Caja de texto que cabe dentro de un fondo, en % del propio fondo.
   *
   * Los mismos números que los valores por defecto de field_caja_ancho y
   * field_caja_alto: un fondo sin medir cae aquí en vez de dejar el nombre
   * desbordado. Una nube no es un rectángulo, así que el texto no puede llegar
   * a los bordes de la foto.
   */
  protected const CAJA_DEFECTO = ['ancho' => 50.0, 'alto' => 34.0];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected RendererInterface $renderer,
    protected EntityRepositoryInterface $entityRepository,
    protected ImageFactory $imageFactory,
  ) {
  }

  /**
   * Monta la ficha completa.
   *
   * Galería, cabecera, desglose de precio, acordeones y relacionados. Lo llama
   * PronensHooks::preprocessCommerceProduct() porque un tema solo puede
   * implementar cada preprocess una vez. El add-to-cart viene del display.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de producto.
   */
  public function buildFicha(array &$variables, ProductInterface $producto): void {
    $variacion = $producto->getDefaultVariation();
    $precio = $variacion?->getPrice();
    $recargo = $this->recargo($producto);

    $variables['ficha'] = [
      'eyebrow' => $this->eyebrow($producto),
      'titulo' => $producto->label(),
      'fotos' => $this->fotos($variables, $producto),
      'precio' => $precio !== NULL ? $this->precio($precio) : NULL,
      // El desglose "14,52 € + 5,00 € bordado" del prototipo: el precio
      // unitario no cambia, el bordado es un ajuste aparte.
      'precio_base' => $precio !== NULL ? (float) $precio->getNumber() : NULL,
      'moneda' => $precio?->getCurrencyCode() ?? 'EUR',
      'recargo' => $recargo,
      'recargo_texto' => $recargo > 0
        ? $this->precioSuelto((string) $recargo, $precio?->getCurrencyCode() ?? 'EUR')
        : NULL,
      'personalizable' => $this->esPersonalizable($producto),
      // Dónde y de qué tamaño va el bordado sobre la foto, en porcentaje: la
      // misma foto se sirve en varios estilos y anchos, así que en píxeles solo
      // valdría para un tamaño.
      'inicial' => $this->posicionInicial($producto),
      // Y con qué letra, en qué color y en qué caja se borda, que en los
      // productos de nombre lo decide el backoffice producto a producto.
      'bordado' => $this->estiloBordado($producto),
      'guia_tallas' => $this->guiaTallas($producto),
      'guia_bordado' => $this->esModoInicial($producto) ? $this->guiaBordado($variables) : NULL,
    ];
    // La nube de las mochilas y las bolsas. El primero es el que sale elegido
    // de entrada, igual que en el formulario (PersonalizacionHooks), así que la
    // vista previa ya se pinta bien antes de que el JS toque nada.
    $fondos = $this->fondosDelProducto($producto, $variables);
    $variables['ficha']['fondos'] = $fondos;
    $variables['ficha']['fondo'] = $fondos === [] ? NULL : reset($fondos);
    // Los complementarios no se pintan en la ficha: viven en el flyout del
    // carrito ("Completa el conjunto", CarritoHooks::completaElConjunto()).
    $variables['ficha']['relacionados'] = $this->relacionados($variables, $producto);
    $variables['#attached']['library'][] = 'pronens/ficha';
    // Una tipografía por producto y solo la que toca: la letra de parche
    // (Graduate) en el modo inicial, y en el de nombre la que diga la ficha.
    $variables['#attached']['library'][] = self::FUENTES[$variables['ficha']['bordado']['fuente']];
    $variables['#attached']['drupalSettings']['pronens']['ficha'] = [
      'precioBase' => $variables['ficha']['precio_base'],
      'recargo' => $recargo,
      'moneda' => $variables['ficha']['moneda'],
      // Precio de cada extra, para que el CTA sume en vivo lo que se marca.
      'extras' => $this->preciosDeExtras($producto),
      // Perfil e interior de cada formato: con eso el JS dibuja las letras y la
      // vista previa tal como quedarán bordadas.
      'formatos' => $this->coloresDeFormatos(),
      // Foto, caja de texto y color de hilo de cada fondo: con eso el JS cambia
      // la nube y encoge el nombre para que quepa dentro al elegir otra.
      'fondos' => $fondos,
      // Para rehacer el desglose al cambiar de variación.
      'etiquetaBordado' => (string) $this->t('embroidery'),
    ];
  }

  /**
   * Implements hook_form_alter().
   *
   * Reordena y agrupa el add-to-cart para el orden del diseño: atributos,
   * card de personalización, y por último cantidad junto al CTA. El módulo
   * pone los campos de bordado con peso 2; aquí solo se agrupan y se visten,
   * sin tocar validación ni lógica de negocio.
   *
   * @param array<string, mixed> $form
   *   El formulario.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!str_starts_with($form_id, 'commerce_order_item_add_to_cart_form_commerce_product')) {
      return;
    }
    $producto = $form_state->get('product');

    $form['#attributes']['class'][] = 'pro-buy-form';

    // El precio de la variación elegida viaja en el propio formulario, que es lo
    // único que Commerce vuelve a renderizar al cambiar de talla o color. Sin
    // esto, el precio grande y el total del CTA se quedaban con el de la
    // variación por defecto: se elegía la talla adulto y seguía diciendo 18,95 €
    // cuando se iba a pagar 23,95 €.
    $variacion = $this->variacionElegida($form_state, $producto);
    if ($variacion !== NULL && ($precio = $variacion->getPrice()) !== NULL) {
      $form['#attributes']['data-pro-precio'] = $precio->getNumber();
      $form['#attributes']['data-pro-precio-texto'] = $this->precio($precio);
    }

    // Atributos (talla, medida…): pastillas.
    if (isset($form['purchased_entity'])) {
      $form['purchased_entity']['#attributes']['class'][] = 'pro-attrs';
      $form['purchased_entity']['#weight'] = 0;
      $this->traduceEtiquetasDeAtributo($form['purchased_entity']);
    }

    // Card del personalizador: el checkbox y los dos campos van juntos.
    if (isset($form['personalizacion_activa'])) {
      $form['pro_perso'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['pro-perso'],
          'data-pro-perso' => TRUE,
        ],
        '#weight' => 5,
      ];
      foreach (['personalizacion_activa', 'field_texto_bordado', 'field_fondo_bordado', 'field_color_bordado'] as $clave) {
        if (isset($form[$clave])) {
          $form['pro_perso'][$clave] = $form[$clave];
          unset($form[$clave]);
        }
      }
      $form['pro_perso']['personalizacion_activa']['#attributes']['data-pro-perso-toggle'] = TRUE;
      // El prototipo canta el recargo junto al texto del checkbox.
      $recargo = $producto instanceof ProductInterface ? $this->recargo($producto) : 0.0;
      if ($recargo > 0) {
        $titulo = (string) $form['pro_perso']['personalizacion_activa']['#title'];
        $form['pro_perso']['personalizacion_activa']['#title'] = Markup::create(
          $titulo . ' <span class="pro-perso__fee">+'
          . $this->precioSuelto((string) $recargo) . '</span>'
        );
      }
      if (isset($form['pro_perso']['field_texto_bordado']['widget'][0]['value'])) {
        $valor = &$form['pro_perso']['field_texto_bordado']['widget'][0]['value'];
        $valor['#attributes']['data-pro-perso-texto'] = TRUE;
        // En modo inicial se elige la letra de una rejilla en vez de teclearla:
        // es una sola letra mayúscula, así no hay forma de equivocarse y cada
        // letra se dibuja con los colores del formato elegido, que es la única
        // manera de ver cómo va a quedar sin foto de cada combinación.
        if ($producto instanceof ProductInterface && $this->esModoInicial($producto)) {
          $valor['#type'] = 'radios';
          $valor['#options'] = array_combine(self::LETRAS, self::LETRAS);
          $valor['#attributes']['class'][] = 'pro-letras';
          unset($valor['#size'], $valor['#maxlength'], $valor['#placeholder']);
        }
        // La descripción del campo es una nota interna de la migración ("máximo
        // real observado en el Drupal 7…"): en la tienda va un placeholder con
        // el límite real, que es lo que dice el diseño.
        $limite = (int) ($valor['#maxlength'] ?? 0);
        $valor['#description'] = NULL;
        if (($valor['#placeholder'] ?? '') === '' && $limite > 1) {
          $valor['#placeholder'] = $this->t('Type the name (max. @n)', ['@n' => $limite]);
        }
        // Los productos que se bordan en caja alta lo enseñan ya al teclear, no
        // solo en la vista previa. El servidor lo confirma de todas formas
        // (PersonalizacionHooks::validarPersonalizacion), que es lo que guarda
        // MÓNICA en la línea de pedido.
        if ($producto instanceof ProductInterface && $this->estiloBordado($producto)['mayusculas']) {
          $valor['#attributes']['class'][] = 'pro-perso__texto--caps';
          $valor['#attributes']['autocapitalize'] = 'characters';
        }
        unset($valor);
      }
      if (isset($form['pro_perso']['field_fondo_bordado'])
        && ($form['pro_perso']['field_fondo_bordado']['#access'] ?? TRUE) !== FALSE) {
        $this->vistealosFondos($form['pro_perso']['field_fondo_bordado']);
      }
      if (isset($form['pro_perso']['field_color_bordado'])) {
        // El formato solo se elige en los productos de inicial: en el D7 el
        // campo existía únicamente en el tipo de línea `custom_color_product`
        // de un solo producto, y el cliente confirma que la elección de
        // combinación es cosa de la inicial, no del nombre completo.
        if ($producto instanceof ProductInterface && $this->esModoInicial($producto)) {
          $this->vistealosFormatos($form['pro_perso']['field_color_bordado']);
        }
        else {
          $form['pro_perso']['field_color_bordado']['#access'] = FALSE;
        }
      }
    }

    // Extras (llavero y compañía) en su propia card: no dependen del bordado.
    if (isset($form['field_extras']) && ($form['field_extras']['#access'] ?? TRUE) !== FALSE) {
      $form['pro_extras'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['pro-extras'], 'data-pro-extras' => TRUE],
        '#weight' => 7,
      ];
      foreach (['field_extras', 'field_extras_texto'] as $clave) {
        if (isset($form[$clave])) {
          $form['pro_extras'][$clave] = $form[$clave];
          unset($form[$clave]);
        }
      }
      $this->vistealosExtras($form['pro_extras']['field_extras']);
    }

    // Cantidad y CTA en la misma fila, después de la card.
    $form['pro_compra'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pro-ficha__row']],
      '#weight' => 10,
    ];
    if (isset($form['quantity'])) {
      $form['quantity']['#attributes']['class'][] = 'pro-qty';
      if (isset($form['quantity']['widget'][0]['value'])) {
        $form['quantity']['widget'][0]['value']['#attributes']['data-pro-qty-input'] = TRUE;
      }
      $form['pro_compra']['quantity'] = $form['quantity'];
      unset($form['quantity']);
    }
    if (isset($form['actions'])) {
      $form['actions']['#attributes']['class'][] = 'pro-buy';
      if (isset($form['actions']['submit'])) {
        $form['actions']['submit']['#attributes']['class'][] = 'pro-btn';
        $form['actions']['submit']['#attributes']['class'][] = 'pro-btn--cta';
        $form['actions']['submit']['#attributes']['data-pro-cta'] = TRUE;
      }
      $form['pro_compra']['actions'] = $form['actions'];
      unset($form['actions']);
    }
  }

  /**
   * Posición y tamaño del bordado sobre la foto, en porcentaje.
   *
   * @return array<string, float>
   *   Claves x, y y tamaño.
   */
  protected function posicionInicial(ProductInterface $producto): array {
    $lee = static function (string $campo, float $defecto) use ($producto): float {
      if (!$producto->hasField($campo) || $producto->get($campo)->isEmpty()) {
        return $defecto;
      }

      return (float) $producto->get($campo)->value;
    };

    return [
      'x' => $lee('field_inicial_x', 50.0),
      'y' => $lee('field_inicial_y', 50.0),
      'tamano' => $lee(
        'field_inicial_tamano',
        self::TAMANO_DEFECTO[$this->esModoInicial($producto) ? 'inicial' : 'texto']
      ),
      // La inclinación va en grados y vale para los dos modos: es colocación,
      // igual que la posición, no una decisión sobre la letra.
      'rotacion' => $lee('field_bordado_rotacion', 0.0),
      // Ancho de la nube, también en % del ancho de la foto. Solo se usa en los
      // productos que ofrecen fondo; en el resto la variable sobra y no estorba.
      'fondo_ancho' => $lee('field_fondo_tamano', self::FONDO_ANCHO_DEFECTO),
    ];
  }

  /**
   * Fondos del bordado que ofrece el producto, con lo que necesita el JS.
   *
   * La nube de las mochilas y las bolsas: el nombre no se borda sobre la tela
   * sino dentro de una forma. De cada una hace falta la foto (que se cambia al
   * elegir otro color), la caja de texto que cabe dentro de la silueta y el
   * color del hilo con el que se borda encima, que puede no ser el del producto:
   * sobre la nube marrón el nombre va en blanco y sobre la tela iría en rosa.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $producto
   *   El producto de la ficha.
   * @param array<string, mixed> $variables
   *   Variables del template (se anotan cache tags).
   *
   * @return array<int, array<string, mixed>>
   *   Datos indexados por id de término, en el orden en que los ofrece el
   *   producto: el primero es el que sale elegido de entrada.
   */
  protected function fondosDelProducto(ProductInterface $producto, array &$variables): array {
    // El fondo es el de un NOMBRE bordado: una inicial es un parche que va
    // sobre la tela, no dentro de una nube. La misma regla que el módulo, que
    // es quien decide si el selector se enseña (PersonalizacionHooks).
    if ($this->esModoInicial($producto) || !$producto->hasField('field_fondos_disponibles')) {
      return [];
    }
    $lista = $producto->get('field_fondos_disponibles');
    if (!$lista instanceof EntityReferenceFieldItemListInterface) {
      return [];
    }

    $fondos = [];
    foreach ($lista->referencedEntities() as $termino) {
      if (!$termino instanceof TermInterface || !$termino->isPublished()) {
        continue;
      }
      $media = $this->mediaFromField($termino, 'field_imagen');
      $variables['#cache']['tags'] = Cache::mergeTags(
        $variables['#cache']['tags'] ?? [],
        $termino->getCacheTags()
      );
      $fondos[(int) $termino->id()] = [
        'nombre' => (string) $termino->label(),
        'foto' => $media !== NULL ? $this->urlDeEstilo($media, 'pronens_fondo') : NULL,
        // Porcentajes de la propia foto del fondo: una nube no es un
        // rectángulo y el nombre tiene que quedarse dentro de la panza.
        'ancho' => $this->numero($termino, 'field_caja_ancho', self::CAJA_DEFECTO['ancho']),
        'alto' => $this->numero($termino, 'field_caja_alto', self::CAJA_DEFECTO['alto']),
        'color' => $this->colorDeCampo($termino, 'field_color'),
      ];
    }

    // Un fondo sin foto no se puede pintar: se descarta en vez de dejar un
    // hueco que el cliente elegiría a ciegas.
    return array_filter($fondos, static fn (array $fondo) => $fondo['foto'] !== NULL);
  }

  /**
   * URL de la imagen de un media en un estilo dado.
   *
   * Hace falta la URL suelta y no el render array porque el JS cambia el src al
   * elegir otro fondo, y por eso mismo el estilo se genera aquí: si la primera
   * petición del fichero derivado la hiciera el JS, el fondo tardaría en verse.
   */
  protected function urlDeEstilo(MediaInterface $media, string $estilo): ?string {
    if (!$media->hasField('field_media_image')) {
      return NULL;
    }
    $lista = $media->get('field_media_image');
    $ficheros = $lista instanceof EntityReferenceFieldItemListInterface ? $lista->referencedEntities() : [];
    $fichero = reset($ficheros);
    if (!$fichero instanceof FileInterface) {
      return NULL;
    }
    $imagen = $this->entityTypeManager->getStorage('image_style')->load($estilo);

    return $imagen instanceof ImageStyleInterface
      ? $imagen->buildUrl((string) $fichero->getFileUri())
      : NULL;
  }

  /**
   * Valor numérico de un campo, o el que se pase por defecto.
   */
  protected function numero(object $entidad, string $campo, float $defecto): float {
    if (!$entidad instanceof FieldableEntityInterface
      || !$entidad->hasField($campo)
      || $entidad->get($campo)->isEmpty()) {
      return $defecto;
    }

    return (float) $entidad->get($campo)->value;
  }

  /**
   * Con qué letra, en qué color y en qué caja se borda este producto.
   *
   * En modo inicial no hay nada que decidir aquí: la letra va en Graduate y los
   * colores salen del formato que elige el cliente en la ficha. En modo nombre
   * los tres los fija el backoffice producto a producto, porque son una
   * característica de la prenda y no una elección de quien compra: la bolsa gris
   * de referencia lleva el nombre en mayúsculas y en rosa siempre.
   *
   * @return array{modo: string, fuente: string, color: string|null, mayusculas: bool}
   *   El estilo del bordado.
   */
  protected function estiloBordado(ProductInterface $producto): array {
    if ($this->esModoInicial($producto)) {
      return ['modo' => 'inicial', 'fuente' => 'letra', 'color' => NULL, 'mayusculas' => FALSE];
    }

    $fuente = self::FUENTE_DEFECTO;
    if ($producto->hasField('field_bordado_fuente') && !$producto->get('field_bordado_fuente')->isEmpty()) {
      $valor = (string) $producto->get('field_bordado_fuente')->value;
      $fuente = isset(self::FUENTES[$valor]) ? $valor : self::FUENTE_DEFECTO;
    }

    $color = NULL;
    if ($producto->hasField('field_bordado_color') && !$producto->get('field_bordado_color')->isEmpty()) {
      $valor = (string) $producto->get('field_bordado_color')->color;
      // Solo hexadecimal: el valor acaba dentro de un atributo style.
      $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $valor) === 1 ? $valor : NULL;
    }

    return [
      'modo' => 'texto',
      'fuente' => $fuente,
      'color' => $color,
      'mayusculas' => $producto->hasField('field_bordado_mayusculas')
        && (bool) $producto->get('field_bordado_mayusculas')->value,
    ];
  }

  /**
   * Variación que el formulario tiene elegida ahora mismo.
   *
   * Commerce guarda la elegida en el form state tras cada refresco por AJAX;
   * en la primera carga todavía no hay ninguna y manda la de por defecto.
   */
  protected function variacionElegida(FormStateInterface $form_state, mixed $producto): ?ProductVariationInterface {
    $id = $form_state->get('selected_variation');
    if ($id) {
      $variacion = $this->entityTypeManager->getStorage('commerce_product_variation')->load($id);
      if ($variacion instanceof ProductVariationInterface) {
        return $variacion;
      }
    }

    return $producto instanceof ProductInterface ? $producto->getDefaultVariation() : NULL;
  }

  /**
   * Fotos de la galería: principal primero, sin repetidas.
   *
   * @param array<string, mixed> $variables
   *   Variables del template (se anotan cache tags).
   *
   * @return array<int, array<string, mixed>>
   *   Lista de imágenes renderizables.
   */
  protected function fotos(array &$variables, ProductInterface $producto): array {
    // Sobre qué foto va la vista previa del bordado: la que diga
    // field_bordado_foto (el bordado va en una cara que la principal no
    // enseña) y, sin ella, la primera. La misma elección que hace el widget
    // del backoffice (MontajeHooks::fotoDeMontaje), que es lo que garantiza
    // que lo colocado y lo que se ve coinciden.
    $media_bordado = $this->mediaFromField($producto, 'field_bordado_foto');
    $id_bordado = $media_bordado?->id();

    $fotos = [];
    foreach ($this->mediasFromFields($producto, ['field_imagen_principal', 'field_galeria']) as $media) {
      $foto = $this->foto($variables, $media, $fotos === [], $id_bordado);
      if ($foto !== NULL) {
        $fotos[] = $foto;
      }
      if (\count($fotos) === self::MAX_FOTOS) {
        break;
      }
    }
    // Si la foto del bordado no está entre las que se enseñan (no es de la
    // galería, o quedó fuera del corte de seis), se añade al final: la vista
    // previa necesita una foto donde vivir.
    if ($media_bordado !== NULL && !array_filter($fotos, static fn (array $foto) => $foto['bordado'])) {
      $foto = $this->foto($variables, $media_bordado, $fotos === [], $id_bordado);
      if ($foto !== NULL) {
        $fotos[] = $foto;
      }
    }
    // Sin foto elegida, la vista previa va en la primera, como siempre.
    if ($fotos !== [] && !array_filter($fotos, static fn (array $foto) => $foto['bordado'])) {
      $fotos[0]['bordado'] = TRUE;
    }

    return $fotos;
  }

  /**
   * Una foto de la galería, con todo lo que pide la plantilla.
   *
   * @param array<string, mixed> $variables
   *   Variables del template (se anotan cache tags).
   * @param \Drupal\media\MediaInterface $media
   *   El media con la foto.
   * @param bool $principal
   *   Si es la primera de la galería (estilo grande y carga eager).
   * @param int|string|null $id_bordado
   *   Id del media sobre el que va la vista previa del bordado, o NULL.
   *
   * @return array<string, mixed>|null
   *   Datos de la foto, o NULL si el media no trae imagen.
   */
  protected function foto(array &$variables, MediaInterface $media, bool $principal, int|string|null $id_bordado): ?array {
    $imagen = $this->buildStyledImage(
      $media,
      $principal ? 'pronens_ficha_principal' : 'pronens_ficha_miniatura',
      $principal
    );
    if ($imagen === NULL) {
      return NULL;
    }
    $alt = (string) ($imagen['#alt'] ?? '');
    // La cuadrícula recorta a 3:4; en el lightbox se ve entera. El enlace
    // sirve además sin JS: lleva a la foto grande directamente.
    $derivados = $this->datosDeEstilo($media, ['pronens_lightbox', 'pronens_zoom']);
    $grande = $derivados['pronens_lightbox'] ?? NULL;
    $lupa = $derivados['pronens_zoom'] ?? NULL;
    // La versión de más resolución solo se ofrece cuando el original da para
    // más de los 1400 del lightbox (215 medias pasan de 2600 y 74 andan entre
    // medias); en el resto sería el mismo fichero pesando dos veces. La pide
    // el JS únicamente cuando se acerca la foto, no al abrir el lightbox.
    $hay_lupa = $grande !== NULL && $lupa !== NULL
      && (int) ($lupa['ancho'] ?? 0) > (int) ($grande['ancho'] ?? 0);
    $variables['#cache']['tags'] = Cache::mergeTags($variables['#cache']['tags'] ?? [], $media->getCacheTags());

    return [
      'imagen' => $imagen,
      'grande' => $grande['url'] ?? NULL,
      'lupa' => $hay_lupa ? $lupa['url'] : NULL,
      // Los píxeles reales de la mejor versión que se va a servir: con ellos
      // el JS sabe cuánto puede acercar sin emborronar. Ninguno de los dos
      // estilos amplía, así que la mitad del catálogo no da para nada.
      'ancho' => $hay_lupa ? $lupa['ancho'] : ($grande['ancho'] ?? NULL),
      'alt' => $alt,
      // La migración dejó el nombre del fichero como texto alternativo en las
      // fotos del D7, y "Foto Cupcake 1 - copia.jpg" no es un pie de foto.
      'pie' => $this->pareceNombreDeFichero($alt) ? '' : $alt,
      // Aquí vive la vista previa del bordado (una sola foto la lleva).
      'bordado' => $id_bordado !== NULL && (string) $media->id() === (string) $id_bordado,
    ];
  }

  /**
   * Si un texto parece el nombre de un fichero y no una descripción.
   */
  protected function pareceNombreDeFichero(string $texto): bool {
    return preg_match('/\.(jpe?g|png|gif|webp|avif)$/i', trim($texto)) === 1;
  }

  /**
   * URL y medidas de la imagen de un media con un estilo dado.
   *
   * Las medidas son las del derivado, no las del original: los dos estilos que
   * usa el lightbox escalan sin ampliar, así que un original de 945px sale de
   * 945px pida lo que pida el estilo. Es justo el dato que necesita la lupa
   * para saber si hay píxeles que enseñar.
   *
   * @param \Drupal\media\MediaInterface $media
   *   El media con la foto.
   * @param string[] $estilos
   *   Nombres de los estilos de imagen. Se piden juntos porque leer el fichero
   *   cuesta, y con uno basta para calcular todos los derivados.
   *
   * @return array<string, array{url: string, ancho: int, alto: int}>
   *   Datos de cada derivado, indexados por estilo. El estilo que no exista no
   *   trae entrada, y la lista sale vacía si el media no tiene imagen.
   */
  protected function datosDeEstilo(MediaInterface $media, array $estilos): array {
    if (!$media->hasField('field_media_image')) {
      return [];
    }
    $campo = $media->get('field_media_image');
    $ficheros = $campo instanceof EntityReferenceFieldItemListInterface ? $campo->referencedEntities() : [];
    $fichero = reset($ficheros);
    if (!$fichero instanceof FileInterface) {
      return [];
    }
    $uri = (string) $fichero->getFileUri();
    // Las medidas se leen del fichero y no del campo: 893 de las 1165 medias
    // guardan las del original que había antes del dedupe de la migración
    // (tote-1v1.jpg dice 860x842 y mide 1200x1600), así que fiarse del campo
    // dejaría sin lupa fotos que sí tienen píxeles de sobra.
    $imagen = $this->imageFactory->get($uri);
    if (!$imagen->isValid()) {
      return [];
    }
    $almacen = $this->entityTypeManager->getStorage('image_style');
    $datos = [];
    foreach ($estilos as $estilo) {
      $estilo_imagen = $almacen->load($estilo);
      if (!$estilo_imagen instanceof ImageStyleInterface) {
        continue;
      }
      $medidas = ['width' => $imagen->getWidth(), 'height' => $imagen->getHeight()];
      $estilo_imagen->transformDimensions($medidas, $uri);
      $datos[$estilo] = [
        'url' => $estilo_imagen->buildUrl($uri),
        'ancho' => (int) $medidas['width'],
        'alto' => (int) $medidas['height'],
      ];
    }

    return $datos;
  }

  /**
   * Rehace el título de cada selector de atributo en el idioma de la página.
   *
   * Commerce saca ese título del atributo
   * (commerce_product.commerce_product_attribute.talla) y lo traduce al idioma
   * de la VARIACIÓN, no al de la página: ProductVariationAttributeMapper::
   * prepareAttributes() llama a getTranslationFromContext() pasándole
   * $selected_variation->language(). Aquí las 1123 variaciones son solo `es`,
   * así que el selector decía "Talla" también en la ficha francesa por mucho
   * que el atributo estuviera traducido.
   *
   * Se relee del config factory, que ya aplica el override del idioma activo.
   * Ojo: la etiqueta que Commerce usa es la del atributo, NO la del campo
   * `field.field.commerce_product_variation.default.attribute_talla`, que
   * también está traducida pero solo sale en el backoffice.
   *
   * @param array<string, mixed> $elemento
   *   El sub-árbol purchased_entity del formulario.
   */
  protected function traduceEtiquetasDeAtributo(array &$elemento): void {
    foreach ($elemento as $clave => &$hijo) {
      if (!is_array($hijo)) {
        continue;
      }
      if (is_string($clave) && str_starts_with($clave, 'attribute_') && isset($hijo['#title'])) {
        $etiqueta = $this->configFactory
          ->get('commerce_product.commerce_product_attribute.' . substr($clave, strlen('attribute_')))
          ->get('label');
        if (is_string($etiqueta) && $etiqueta !== '') {
          $hijo['#title'] = $etiqueta;
        }
        continue;
      }
      $this->traduceEtiquetasDeAtributo($hijo);
    }
  }

  /**
   * Eyebrow del diseño: categoría y composición separadas por punto medio.
   */
  protected function eyebrow(ProductInterface $producto): ?string {
    $partes = [];
    $termino = $this->termFromField($producto, 'field_tipo_de_producto');
    if ($termino !== NULL) {
      $partes[] = $this->etiqueta($termino);
    }
    if ($producto->hasField('field_composicion') && !$producto->get('field_composicion')->isEmpty()) {
      $partes[] = (string) $producto->get('field_composicion')->value;
    }

    return $partes === [] ? NULL : implode(' · ', $partes);
  }

  /**
   * Recargo por bordado de este producto, en euros.
   *
   * Por producto manda field_recargo; si está vacío, el ajuste global de
   * /admin/commerce/config/personalizacion.
   */
  protected function recargo(ProductInterface $producto): float {
    // La inicial nunca se cobra: es el reclamo del producto, no un extra
    // (misma regla que PersonalizacionOrderProcessor).
    if ($this->esModoInicial($producto)) {
      return 0.0;
    }
    if ($producto->hasField('field_recargo') && !$producto->get('field_recargo')->isEmpty()) {
      return (float) $producto->get('field_recargo')->number;
    }

    return (float) $this->configFactory->get('pronens_personalizacion.settings')->get('recargo.number');
  }

  /**
   * TRUE si el producto admite bordado.
   */
  protected function esPersonalizable(ProductInterface $producto): bool {
    return $producto->hasField('field_personalizable')
      && (bool) $producto->get('field_personalizable')->value;
  }

  /**
   * Guía de tallas: solo 57 productos la tienen.
   *
   * @return array<string, mixed>|null
   *   Render array del término de la guía.
   */
  protected function guiaTallas(ProductInterface $producto): ?array {
    $termino = $this->termFromField($producto, 'field_guia_tallas');
    if ($termino === NULL) {
      return NULL;
    }

    $traducido = $this->traducido($termino);

    return [
      'titulo' => $traducido->label(),
      'contenido' => $traducido->get('description')->view(['label' => 'hidden', 'type' => 'text_default']),
    ];
  }

  /**
   * Productos similares: "También te puede gustar".
   *
   * field_relacionados manda si está relleno; si no, los más recientes de la
   * misma categoría.
   *
   * @param array<string, mixed> $variables
   *   Variables del template (se anotan cache tags).
   * @return array<int, array<string, mixed>>
   *   Render arrays de tarjetas.
   */
  protected function relacionados(array &$variables, ProductInterface $producto): array {
    $ids = [];
    if ($producto->hasField('field_relacionados')) {
      $lista = $producto->get('field_relacionados');
      if ($lista instanceof EntityReferenceFieldItemListInterface) {
        foreach ($lista->referencedEntities() as $relacionado) {
          $ids[] = (int) $relacionado->id();
        }
      }
    }

    if ($ids === []) {
      $termino = $this->termFromField($producto, 'field_tipo_de_producto');
      if ($termino === NULL) {
        return [];
      }
      $consulta = $this->entityTypeManager->getStorage('commerce_product')->getQuery()
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->condition('field_tipo_de_producto', $termino->id())
        ->condition('product_id', $producto->id(), '<>')
        ->sort('created', 'DESC')
        ->range(0, 4);
      $ids = array_map('intval', array_values($consulta->execute()));
      $variables['#cache']['tags'] = Cache::mergeTags(
        $variables['#cache']['tags'] ?? [],
        ['commerce_product_list']
      );
    }

    return $this->tarjetas($ids);
  }

  /**
   * Render arrays de tarjeta para una lista de ids, hasta 4.
   *
   * @param array<int, int> $ids
   *   Ids de producto.
   *
   * @return array<int, array<string, mixed>>
   *   Render arrays de tarjetas.
   */
  protected function tarjetas(array $ids): array {
    if ($ids === []) {
      return [];
    }
    $constructor = $this->entityTypeManager->getViewBuilder('commerce_product');
    $tarjetas = [];
    foreach ($this->entityTypeManager->getStorage('commerce_product')->loadMultiple(array_slice($ids, 0, 4)) as $producto) {
      $tarjetas[] = $constructor->view($producto, 'tarjeta');
    }

    return $tarjetas;
  }


  /**
   * Convierte las opciones del formato del bordado en fichas visuales.
   *
   * Cada término de color_letra es una combinación cerrada de la fuente única
   * y los colores del hilo, y el diseño la representa con la foto de una letra
   * bordada (el "choose your initial colour" de la referencia). El widget de
   * opciones pinta etiquetas de texto, así que aquí se sustituyen por la foto
   * del término; si no tiene, por una muestra de color de field_color; y si
   * tampoco, se deja el nombre.
   *
   * Ojo con los datos: las 9 fotos que trajo el D7 son miniaturas de 82x93 de
   * camisetas dobladas, no letras bordadas, así que hoy la ficha cae a la
   * muestra de color. En cuanto el taller suba las fotos reales a field_imagen
   * la ficha las usa sin tocar código.
   *
   * @param array<string, mixed> $elemento
   *   El elemento de formulario del campo, por referencia.
   */
  protected function vistealosFormatos(array &$elemento): void {
    if (!isset($elemento['widget']['#options'])) {
      return;
    }
    // En #attributes del widget la clase acabaría también en cada radio; en el
    // elemento va solo al fieldset, que es lo que estiliza el tema.
    $elemento['#attributes']['class'][] = 'pro-formatos';
    // Fuera la opción vacía: el diseño no ofrece "N/D" y el módulo ya vacía el
    // bordado entero cuando la casilla está desmarcada.
    unset($elemento['widget']['#options']['_none']);
    // El formato viene elegido de entrada, y es el nº 1 de la foto guía
    // ("perfil negro interior blanco" mientras sea el primero por peso del
    // vocabulario): el campo no es obligatorio, así que sin esto se podía
    // pedir una inicial sin decir de qué color. Ojo: en un campo de un solo
    // valor, options_buttons espera el tid **suelto**; con un array dentro no
    // marcaba ningún radio y la ficha se cargaba sin formato.
    $actual = $elemento['widget']['#default_value'] ?? NULL;
    if (in_array($actual, [NULL, '', '_none', [], ['_none']], TRUE)) {
      $primero = array_key_first($elemento['widget']['#options']);
      $multiple = ($elemento['widget']['#type'] ?? '') === 'checkboxes';
      $elemento['widget']['#default_value'] = $multiple ? [$primero] : $primero;
    }
    // Nota de desarrollo, no texto de tienda.
    $elemento['widget']['#description'] = NULL;
    $elemento['#description'] = NULL;
    $terminos = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadMultiple(array_keys($elemento['widget']['#options']));

    // Los términos vienen ordenados por peso, que es el orden de la foto guía:
    // el número de la pastilla y el de la foto tienen que coincidir.
    $numero = 0;
    foreach ($elemento['widget']['#options'] as $tid => $etiqueta) {
      $termino = $terminos[$tid] ?? NULL;
      if ($termino === NULL) {
        continue;
      }
      $numero++;
      $media = $this->mediaFromField($termino, 'field_imagen');
      $foto = $media !== NULL ? $this->buildStyledImage($media, 'pronens_formato') : NULL;
      $interior = $this->colorDeCampo($termino, 'field_color');
      $perfil = $this->colorDeCampo($termino, 'field_color_perfil');

      if ($foto !== NULL && $this->fotoUtil($media)) {
        $muestra = '<span class="pro-formato__foto">' . $this->renderer->render($foto) . '</span>';
      }
      elseif ($interior !== NULL) {
        // Dos tonos: el aro es el perfil y el centro el interior. Con un solo
        // color, "perfil negro interior blanco" y "todo blanco" se confundían.
        $muestra = '<span class="pro-formato__color" style="--pro-formato-interior:' . $interior
          . ';--pro-formato-perfil:' . ($perfil ?? $interior) . '"></span>';
      }
      else {
        $muestra = '<span class="pro-formato__num">' . $numero . '</span>';
      }
      $elemento['widget']['#options'][$tid] = Markup::create(
        $muestra . '<span class="pro-formato__nombre">' . $etiqueta . '</span>'
      );
    }
  }

  /**
   * Pone la foto de cada fondo junto a su radio.
   *
   * La nube se elige por su color, así que la etiqueta útil es la propia foto;
   * el nombre se queda para lectores de pantalla, igual que en el selector de
   * color de variación. El módulo ya ha recortado las opciones a los fondos que
   * ofrece este producto y ha marcado el primero.
   *
   * @param array<string, mixed> $elemento
   *   El elemento de formulario del campo, por referencia.
   */
  protected function vistealosFondos(array &$elemento): void {
    if (!isset($elemento['widget']['#options'])) {
      return;
    }
    $elemento['#attributes']['class'][] = 'pro-fondos';
    $elemento['widget']['#title'] = $this->t('Background', [], ['context' => 'Embroidery']);
    $elemento['widget']['#description'] = NULL;
    $elemento['#description'] = NULL;

    /** @var array<int, TermInterface> $terminos */
    $terminos = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadMultiple(array_keys($elemento['widget']['#options']));

    foreach ($elemento['widget']['#options'] as $tid => $etiqueta) {
      $termino = $terminos[$tid] ?? NULL;
      if ($termino === NULL) {
        continue;
      }
      $media = $this->mediaFromField($termino, 'field_imagen');
      $foto = $media !== NULL ? $this->buildStyledImage($media, 'pronens_fondo_muestra') : NULL;
      if ($foto === NULL) {
        continue;
      }
      $elemento['widget']['#options'][$tid] = Markup::create(
        '<span class="pro-fondo__foto">' . $this->renderer->render($foto) . '</span>'
        . '<span class="pro-fondo__nombre">' . $etiqueta . '</span>'
      );
    }
  }

  /**
   * Color del hilo de un formato, en hexadecimal, o NULL.
   */
  protected function colorDeFormato(object $termino): ?string {
    if (!$termino instanceof FieldableEntityInterface) {
      return NULL;
    }
    if (!$termino->hasField('field_color') || $termino->get('field_color')->isEmpty()) {
      return NULL;
    }
    // color_field_type guarda el hexadecimal en la propiedad "color", no en
    // "value": leerlo por ->value devuelve NULL.
    return $this->colorDeCampo($termino, 'field_color');
  }

  /**
   * TRUE si la foto del formato sirve como ficha de letra bordada.
   *
   * Las del D7 miden 82x93: por debajo de 200px de ancho no dan ni para el
   * tamaño de la ficha en pantallas normales, y de hecho no son letras.
   */
  protected function fotoUtil(?object $media): bool {
    if (!$media instanceof MediaInterface || !$media->hasField('field_media_image')) {
      return FALSE;
    }
    $item = $media->get('field_media_image')->first();

    return $item !== NULL && (int) ($item->getValue()['width'] ?? 0) >= 200;
  }


  /**
   * Colores de perfil e interior de cada formato de bordado.
   *
   * @return array<int, array<string, string>>
   *   Colores indexados por id de término.
   */
  protected function coloresDeFormatos(): array {
    $colores = [];
    /** @var array<int, TermInterface> $terminos */
    $terminos = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'color_letra', 'status' => 1]);
    foreach ($terminos as $termino) {
      $colores[(int) $termino->id()] = [
        'perfil' => $this->colorDeCampo($termino, 'field_color_perfil') ?? '#1B1F27',
        'interior' => $this->colorDeCampo($termino, 'field_color') ?? '#F5F1E6',
      ];
    }

    return $colores;
  }

  /**
   * Hexadecimal de un campo de color, o NULL.
   */
  protected function colorDeCampo(object $entidad, string $campo): ?string {
    if (!$entidad instanceof FieldableEntityInterface
      || !$entidad->hasField($campo)
      || $entidad->get($campo)->isEmpty()) {
      return NULL;
    }
    $valor = (string) ($entidad->get($campo)->first()?->get('color')->getValue() ?? '');

    return preg_match('/^#[0-9a-fA-F]{6}$/', $valor) === 1 ? $valor : NULL;
  }

  /**
   * Precio unitario de cada extra que ofrece el producto.
   *
   * @return array<int, float>
   *   Precios indexados por id de término.
   */
  protected function preciosDeExtras(ProductInterface $producto): array {
    if (!$producto->hasField('field_extras_disponibles')) {
      return [];
    }
    $lista = $producto->get('field_extras_disponibles');
    if (!$lista instanceof EntityReferenceFieldItemListInterface) {
      return [];
    }
    $precios = [];
    foreach ($lista->referencedEntities() as $extra) {
      if (!$extra instanceof FieldableEntityInterface
        || !$extra->hasField('field_precio')
        || $extra->get('field_precio')->isEmpty()) {
        continue;
      }
      $precios[(int) $extra->id()] = (float) ($extra->get('field_precio')->first()?->get('number')->getValue() ?? 0);
    }

    return $precios;
  }

  /**
   * TRUE si el producto se personaliza con una inicial y no con un nombre.
   */
  protected function esModoInicial(ProductInterface $producto): bool {
    return $producto->hasField('field_modo_personalizacion')
      && (string) $producto->get('field_modo_personalizacion')->value === 'inicial';
  }

  /**
   * Guía visual del formato del bordado, para el diálogo de ayuda.
   *
   * Es un bloque de contenido del tipo guia_bordado, así que el cliente cambia
   * la foto y el texto desde /admin/content/block sin tocar código.
   *
   * @param array<string, mixed> $variables
   *   Variables del template (se anotan cache tags).
   *
   * @return array<string, mixed>|null
   *   Render array del bloque.
   */
  protected function guiaBordado(array &$variables): ?array {
    $almacen = $this->entityTypeManager->getStorage('block_content');
    $bloques = $almacen->loadByProperties(['type' => 'guia_bordado']);
    $bloque = reset($bloques);
    if ($bloque === FALSE) {
      return NULL;
    }
    // Lista del bundle además del bloque: si el cliente crea otro, se recalcula.
    $variables['#cache']['tags'] = Cache::mergeTags(
      $variables['#cache']['tags'] ?? [],
      Cache::mergeTags($bloque->getCacheTags(), ['block_content_list'])
    );

    return $this->entityTypeManager->getViewBuilder('block_content')->view($bloque, 'default');
  }


  /**
   * Pone la foto del extra junto a su casilla.
   *
   * El widget de opciones pinta etiquetas de texto; el diseño de la referencia
   * enseña el llavero, así que la foto del término se cuela en la etiqueta.
   *
   * @param array<string, mixed> $elemento
   *   El elemento de formulario del campo, por referencia.
   */
  protected function vistealosExtras(array &$elemento): void {
    if (!isset($elemento['widget']['#options'])) {
      return;
    }
    $elemento['#attributes']['class'][] = 'pro-extras__lista';
    /** @var array<int, TermInterface> $terminos */
    $terminos = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadMultiple(array_keys($elemento['widget']['#options']));

    foreach ($elemento['widget']['#options'] as $tid => $etiqueta) {
      $termino = $terminos[$tid] ?? NULL;
      if ($termino === NULL) {
        continue;
      }
      $media = $this->mediaFromField($termino, 'field_imagen');
      $foto = $media !== NULL ? $this->buildStyledImage($media, 'pronens_formato') : NULL;
      $muestra = $foto !== NULL
        ? '<span class="pro-extra__foto">' . $this->renderer->render($foto) . '</span>'
        : '';
      $elemento['widget']['#options'][$tid] = Markup::create(
        $muestra . '<span class="pro-extra__nombre">' . $etiqueta . '</span>'
      );
    }
  }

}
