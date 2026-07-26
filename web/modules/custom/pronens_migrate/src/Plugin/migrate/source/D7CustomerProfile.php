<?php

declare(strict_types=1);

namespace Drupal\pronens_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Direcciones de cliente del Drupal 7, una por cliente y tipo.
 *
 * En el D7 hay 2550 perfiles de facturación y 2655 de envío para 1225 clientes:
 * Commerce 1 creaba un perfil nuevo en cada compra en lugar de reutilizarlo.
 * Migrarlos todos llenaría la libreta de direcciones de cada cliente de copias
 * casi idénticas, así que este origen se queda con la más reciente de cada tipo.
 *
 * No se migran los pedidos, así que estas direcciones existen solo para que los
 * 1225 clientes que ya compraron no tengan que volver a teclear la suya.
 */
#[MigrateSource(id: 'pronens_d7_customer_profile')]
final class D7CustomerProfile extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query(): SelectInterface {
    // La más reciente de cada par cliente/tipo, identificada por el profile_id
    // más alto, que en el D7 es autoincremental.
    $recientes = $this->select('commerce_customer_profile', 'sub');
    $recientes->addExpression('MAX(sub.profile_id)', 'max_id');
    $recientes
      ->condition('sub.uid', 0, '>')
      ->condition('sub.status', 1)
      ->groupBy('sub.uid')
      ->groupBy('sub.type');

    $query = $this->select('commerce_customer_profile', 'p')
      ->fields('p', ['profile_id', 'uid', 'type', 'created', 'changed']);
    $query->condition('p.profile_id', $recientes, 'IN');
    $query->orderBy('p.profile_id');
    return $query;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, string>
   */
  public function fields(): array {
    return [
      'profile_id' => 'ID del perfil en el D7',
      'uid' => 'Cliente propietario',
      'type' => 'billing o shipping',
      'created' => 'Creado',
      'changed' => 'Modificado',
      'direccion' => 'Dirección en el formato que espera el plugin addressfield',
      'es_predeterminada' => 'Si es la dirección por defecto del cliente',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, array<string, string>>
   */
  public function getIds(): array {
    return [
      'profile_id' => [
        'type' => 'integer',
        'alias' => 'p',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row): bool {
    $profile_id = (int) $row->getSourceProperty('profile_id');

    $direccion = $this->select('field_data_commerce_customer_address', 'a')
      ->fields('a')
      ->condition('a.entity_type', 'commerce_customer_profile')
      ->condition('a.entity_id', $profile_id)
      ->condition('a.deleted', 0)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if ($direccion === FALSE) {
      return FALSE;
    }

    $valores = [];
    foreach ($direccion as $columna => $valor) {
      if (str_starts_with($columna, 'commerce_customer_address_')) {
        $valores[substr($columna, strlen('commerce_customer_address_'))] = $valor;
      }
    }

    // Sin país no hay dirección utilizable.
    if (empty($valores['country'])) {
      return FALSE;
    }
    // Y sin calle tampoco: hay filas del D7 que solo traen el país.
    if (empty($valores['thoroughfare'])) {
      return FALSE;
    }

    // El D7 guardó el código de país en el campo de provincia en muchas filas,
    // por ejemplo administrative_area = "ES" en lugar de "B" para Barcelona.
    // Dejarlo pasar produciría direcciones inválidas en Drupal 11. La
    // comparación es sin distinguir mayúsculas porque hay filas con "es".
    $provincia = trim((string) ($valores['administrative_area'] ?? ''));
    if (strcasecmp($provincia, (string) $valores['country']) === 0) {
      $provincia = '';
    }
    $valores['administrative_area'] = $provincia;

    // El nombre: el D7 tiene tres formas de guardarlo y no siempre coinciden.
    $nombre = trim((string) ($valores['first_name'] ?? ''));
    $apellidos = trim((string) ($valores['last_name'] ?? ''));
    if ($nombre === '' && $apellidos === '') {
      $partes = preg_split('/\s+/u', trim((string) ($valores['name_line'] ?? ''))) ?: [];
      $nombre = (string) array_shift($partes);
      $apellidos = implode(' ', $partes);
    }

    // Se entrega ya en la forma que espera el campo address de Drupal 11, para
    // no depender del plugin addressfield de contrib, que asume una estructura
    // distinta de la que produce este origen.
    $row->setSourceProperty('direccion', [
      'country_code' => (string) $valores['country'],
      'administrative_area' => $provincia,
      'locality' => (string) ($valores['locality'] ?? ''),
      'dependent_locality' => (string) ($valores['dependent_locality'] ?? ''),
      'postal_code' => (string) ($valores['postal_code'] ?? ''),
      'sorting_code' => '',
      'address_line1' => (string) $valores['thoroughfare'],
      'address_line2' => (string) ($valores['premise'] ?? ''),
      'organization' => (string) ($valores['organisation_name'] ?? ''),
      'given_name' => $nombre,
      'family_name' => $apellidos,
    ]);

    // La de facturación es la que se marca como predeterminada del cliente.
    $row->setSourceProperty('es_predeterminada', $row->getSourceProperty('type') === 'billing');

    return parent::prepareRow($row);
  }

}
