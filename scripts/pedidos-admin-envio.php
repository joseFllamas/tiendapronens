<?php

/**
 * @file
 * Añade el envío a la lista de pedidos del backoffice.
 *
 * Uso: `ddev drush php:script scripts/pedidos-admin-envio.php`.
 *
 * El problema que resuelve, con palabras del cliente: «una vez el pedido se
 * completa pone "completado", y eso no cambia aunque procese el envío». Es
 * cierto y no es un fallo: el workflow `order_default` de Commerce pasa a
 * `completed` en el momento de pagar y ahí se queda. Esa columna habla del
 * pedido (¿pagado? ¿anulado?), no de la caja. Así que /admin/commerce/orders no
 * decía en ninguna parte qué queda por expedir, qué ya tiene número de
 * expedición ni quién eligió recoger en tienda.
 *
 * Lo que deja montado:
 *
 * - Columna «Envío»: un chip con la situación real (Por expedir, Expedido,
 *   En tránsito, Entregado, Recoge en tienda, Devuelto, Cancelado) y debajo el
 *   nombre del método, que es el «tipo de envío».
 * - Columna «Expedición»: el número de Correos Express enlazado al seguimiento
 *   público, más un enlace a la etiqueta para quien tenga permiso de expedir.
 * - Filtro expuesto «Envío», con «Por expedir» como la lista de trabajo del
 *   día. La acción masiva «Generar expediciones de Correos Express» ya estaba
 *   en esta misma pantalla, así que filtrar y expedir se hace de una pasada.
 * - Las etiquetas de lo que ya había pasan a «Estado del pedido», en la columna
 *   y en el filtro, para que no se confunda con el estado del envío.
 *
 * La situación NO se guarda en ningún campo: la calculan los plugins de la
 * entidad de cada fila (ver EnvioDelPedido y CalculadoraSituacion). Y el filtro
 * usa subconsultas EXISTS en vez de una relación de Views porque `shipments` es
 * multivalor y una relación duplicaría la fila de los pedidos con dos cajas,
 * que es el mismo pisotón que ya dio el idioma en el catálogo.
 *
 * Es idempotente: se puede relanzar. La vista es CONFIGURACIÓN, así que lo que
 * escribe este script viaja en config/sync y en producción entra con `drush
 * cim`; se ejecuta aquí y se exporta.
 */

declare(strict_types=1);

use Drupal\views\Entity\View;

$vista = View::load('commerce_orders');
if ($vista === NULL) {
  echo "No existe la vista commerce_orders.\n";
  return;
}

$display = $vista->getDisplay('default');
$opciones = $display['display_options'];

/**
 * Inserta una entrada en un array asociativo justo detrás de otra.
 *
 * Views respeta el orden del array: es lo que decide en qué orden salen las
 * columnas, así que no vale con añadir al final.
 *
 * @param array<string, mixed> $original
 *   Array de partida.
 * @param string $despuesDe
 *   Clave detrás de la cual se inserta (al final si no existe).
 * @param array<string, mixed> $nuevas
 *   Entradas a insertar.
 *
 * @return array<string, mixed>
 *   El array con las entradas nuevas en su sitio.
 */
$insertarDetrasDe = static function (array $original, string $despuesDe, array $nuevas): array {
  // Se quitan primero para que relanzar el script las recoloque en vez de
  // dejarlas duplicadas o en el sitio de la vez anterior.
  foreach (array_keys($nuevas) as $clave) {
    unset($original[$clave]);
  }
  if (!array_key_exists($despuesDe, $original)) {
    return $original + $nuevas;
  }

  $resultado = [];
  foreach ($original as $clave => $valor) {
    $resultado[$clave] = $valor;
    if ($clave === $despuesDe) {
      foreach ($nuevas as $claveNueva => $valorNuevo) {
        $resultado[$claveNueva] = $valorNuevo;
      }
    }
  }

  return $resultado;
};

/**
 * Los ajustes de presentación que Views espera en cualquier campo.
 *
 * @return array<string, mixed>
 *   Valores por omisión, los mismos que guarda la interfaz de Views.
 */
$presentacionPorOmision = static fn (): array => [
  'exclude' => FALSE,
  'alter' => [
    'alter_text' => FALSE,
    'text' => '',
    'make_link' => FALSE,
    'path' => '',
    'absolute' => FALSE,
    'external' => FALSE,
    'replace_spaces' => FALSE,
    'path_case' => 'none',
    'trim_whitespace' => FALSE,
    'alt' => '',
    'rel' => '',
    'link_class' => '',
    'prefix' => '',
    'suffix' => '',
    'target' => '',
    'nl2br' => FALSE,
    'max_length' => 0,
    'word_boundary' => TRUE,
    'ellipsis' => TRUE,
    'more_link' => FALSE,
    'more_link_text' => '',
    'more_link_path' => '',
    'strip_tags' => FALSE,
    'trim' => FALSE,
    'preserve_tags' => '',
    'html' => FALSE,
  ],
  'element_type' => '',
  'element_class' => '',
  'element_label_type' => '',
  'element_label_class' => '',
  'element_label_colon' => FALSE,
  'element_wrapper_type' => '',
  'element_wrapper_class' => '',
  'element_default_classes' => TRUE,
  'empty' => '',
  'hide_empty' => FALSE,
  'empty_zero' => FALSE,
  'hide_alter_empty' => TRUE,
];

// ---------------------------------------------------------------------------
// Campos.
// ---------------------------------------------------------------------------
$camposNuevos = [
  'pronens_envio_pedido' => [
    'id' => 'pronens_envio_pedido',
    'table' => 'commerce_order',
    'field' => 'pronens_envio_pedido',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'pronens_envio_pedido',
    'label' => 'Envío',
  ] + $presentacionPorOmision(),
  'pronens_expedicion_pedido' => [
    'id' => 'pronens_expedicion_pedido',
    'table' => 'commerce_order',
    'field' => 'pronens_expedicion_pedido',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'pronens_expedicion_pedido',
    'label' => 'Expedición',
    // Sin expedición la celda se queda vacía y no hay nada que anunciar.
    'hide_empty' => TRUE,
  ] + $presentacionPorOmision(),
];

// Detrás del estado del pedido: primero en qué estado está el pedido, luego
// dónde está la caja. El total y las operaciones se quedan al final.
$opciones['fields'] = $insertarDetrasDe($opciones['fields'], 'state', $camposNuevos);

// «Estado» a secas era ambiguo desde que hay dos estados en la misma fila.
if (isset($opciones['fields']['state'])) {
  $opciones['fields']['state']['label'] = 'Estado del pedido';
}

// ---------------------------------------------------------------------------
// Filtro expuesto.
// ---------------------------------------------------------------------------
$filtroNuevo = [
  'pronens_situacion_envio' => [
    'id' => 'pronens_situacion_envio',
    'table' => 'commerce_order',
    'field' => 'pronens_situacion_envio',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'pronens_situacion_envio',
    'operator' => 'in',
    'value' => [],
    'group' => 1,
    'exposed' => TRUE,
    'expose' => [
      'operator_id' => 'pronens_situacion_envio_op',
      'label' => 'Envío',
      'description' => '',
      'use_operator' => FALSE,
      'operator' => 'pronens_situacion_envio_op',
      'operator_limit_selection' => FALSE,
      'identifier' => 'envio',
      'required' => FALSE,
      'remember' => FALSE,
      // Un desplegable de una sola opción: quien prepara pedidos elige «Por
      // expedir» y se pone a ello. Con selección múltiple habría que explicar
      // cómo se marcan dos valores en un select.
      'multiple' => FALSE,
      'remember_roles' => ['authenticated' => 'authenticated'],
      'reduce' => FALSE,
    ],
    'is_grouped' => FALSE,
    'group_info' => [
      'label' => '',
      'description' => '',
      'identifier' => '',
      'optional' => TRUE,
      'widget' => 'select',
      'multiple' => FALSE,
      'remember' => FALSE,
      'default_group' => 'All',
      'default_group_multiple' => [],
      'group_items' => [],
    ],
  ],
];

$opciones['filters'] = $insertarDetrasDe($opciones['filters'], 'state', $filtroNuevo);

if (isset($opciones['filters']['state']['expose']['label'])) {
  $opciones['filters']['state']['expose']['label'] = 'Estado del pedido';
}

// ---------------------------------------------------------------------------
// Columnas de la tabla.
// ---------------------------------------------------------------------------
$columnasNuevas = [
  'pronens_envio_pedido' => 'pronens_envio_pedido',
  'pronens_expedicion_pedido' => 'pronens_expedicion_pedido',
];
$opciones['style']['options']['columns'] = $insertarDetrasDe(
  $opciones['style']['options']['columns'],
  'state',
  $columnasNuevas,
);

$infoNueva = [];
foreach (array_keys($columnasNuevas) as $columna) {
  $infoNueva[$columna] = [
    // No se puede ordenar por ellas: el valor no está en ninguna columna de la
    // base de datos, se calcula al pintar la fila. Para agrupar el trabajo
    // está el filtro.
    'sortable' => FALSE,
    'default_sort_order' => 'asc',
    'align' => '',
    'separator' => '',
    // La de expedición desaparece mientras no haya ni una: en una tienda que
    // acaba de empezar, una columna entera vacía solo estorba.
    'empty_column' => $columna === 'pronens_expedicion_pedido',
    'responsive' => '',
  ];
}
$opciones['style']['options']['info'] = $insertarDetrasDe(
  $opciones['style']['options']['info'],
  'state',
  $infoNueva,
);

$display['display_options'] = $opciones;
$displays = $vista->get('display');
$displays['default'] = $display;
$vista->set('display', $displays);
$vista->save();

echo "Vista commerce_orders actualizada.\n";
echo "  Campos:  " . implode(', ', array_keys($opciones['fields'])) . "\n";
echo "  Filtros: " . implode(', ', array_keys($opciones['filters'])) . "\n";
