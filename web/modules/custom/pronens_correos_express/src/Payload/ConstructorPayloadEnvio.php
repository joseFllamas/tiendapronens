<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Payload;

use Drupal\physical\WeightUnit;

/**
 * Construye el cuerpo del alta de expedición.
 *
 * Devuelve un array plano, con las claves en el mismo orden que la integración
 * oficial de Correos, a propósito: el test compara el array completo contra un
 * fichero de referencia, y así cualquier cambio accidental de clave, de orden o
 * de tipo salta en cuanto se ejecutan las pruebas.
 *
 * Es lógica pura y sin dependencias: recibe un DatosEnvio ya resuelto y no toca
 * el contenedor de servicios.
 */
final class ConstructorPayloadEnvio {

  /**
   * Tipo de etiqueta que la API espera dentro del alta.
   *
   * No es el formato de impresión: ese se elige al pedir la etiqueta, en su
   * propia llamada. Aquí siempre va este valor, igual que en la integración
   * oficial.
   */
  public const TIPO_ETIQUETA_ALTA = '5';

  /**
   * Firma de la integración.
   *
   * La API la usa para estadística de plataformas. No es una referencia del
   * pedido: esa va en el campo "ref".
   */
  public const PREFIJO_REF_CLIENTE = 'MODULO_DRUPAL_11';

  public function __construct(
    private readonly Normalizador $normalizador,
  ) {}

  /**
   * Construye el payload del alta.
   *
   * @return array<string, mixed>
   *   Cuerpo listo para enviar.
   */
  public function construir(DatosEnvio $envio): array {
    $remitente = $envio->remitente;
    $destinatario = $envio->destinatario;

    $cpRemitente = $this->normalizador->codigosPostales($remitente->paisIso, $remitente->codigoPostal);
    $cpDestinatario = $this->normalizador->codigosPostales($destinatario->paisIso, $destinatario->codigoPostal);

    $observaciones = $this->normalizador->observaciones($envio->observaciones);
    $bultos = $this->listaBultos($envio, $observaciones);

    $payload = [
      // El alta es la única operación que pide el código de cliente con una P
      // delante. En el resto de campos va tal cual.
      'solicitante' => 'P' . $envio->codigoCliente,
      'canalEntrada' => '',
      // Lo asigna Correos Express, nunca la integración.
      'numEnvio' => '',
      'ref' => $this->normalizador->texto($envio->referencia, Normalizador::MAX_REFERENCIA),
      'refCliente' => self::PREFIJO_REF_CLIENTE . '/' . $envio->version,
      'fecha' => $this->normalizador->fecha($envio->fecha),

      'codRte' => $envio->codigoCliente,
      'nomRte' => $this->normalizador->nombreCompleto($remitente->nombre),
      'nifRte' => $this->normalizador->texto($remitente->documento, Normalizador::MAX_DOCUMENTO),
      'dirRte' => $this->normalizador->texto($remitente->direccion, Normalizador::MAX_DIRECCION),
      'pobRte' => $this->normalizador->texto($remitente->poblacion, Normalizador::MAX_POBLACION),
      'codPosNacRte' => $cpRemitente['nacional'],
      'paisISORte' => strtoupper(trim($remitente->paisIso)),
      'codPosIntRte' => $cpRemitente['internacional'],
      'contacRte' => $this->normalizador->texto($remitente->contacto, Normalizador::MAX_CONTACTO),
      'telefRte' => $this->normalizador->telefono($remitente->telefono),
      'emailRte' => $this->normalizador->texto($remitente->correo, Normalizador::MAX_CORREO),

      // El código de destinatario es para clientes con maestro de direcciones
      // en Correos Express. Una tienda envía a particulares, así que va vacío.
      'codDest' => '',
      'nomDest' => $this->normalizador->nombreCompleto(
        $destinatario->nombre,
        $destinatario->apellidos,
        $destinatario->empresa,
      ),
      'nifDest' => $this->normalizador->texto($destinatario->documento, Normalizador::MAX_DOCUMENTO),
      'dirDest' => $this->normalizador->texto($destinatario->direccion, Normalizador::MAX_DIRECCION),
      'pobDest' => $this->normalizador->texto($destinatario->poblacion, Normalizador::MAX_POBLACION),
      'codPosNacDest' => $cpDestinatario['nacional'],
      'paisISODest' => strtoupper(trim($destinatario->paisIso)),
      'codPosIntDest' => $cpDestinatario['internacional'],
      'contacDest' => $this->normalizador->nombreCompleto($destinatario->nombre, $destinatario->apellidos),
      'telefDest' => $this->normalizador->telefono($destinatario->telefono),
      'emailDest' => $this->normalizador->texto($destinatario->correo, Normalizador::MAX_CORREO),

      // Tercer interlocutor del envío. No se usa en una tienda.
      'contacOtrs' => '',
      'telefOtrs' => '',
      'emailOtrs' => '',

      'observac' => $observaciones,
      'numBultos' => $envio->numeroBultos(),
      'kilos' => $this->kilosTotales($envio),
      'volumen' => '',
      // Las medidas de la raíz van siempre vacías: las que cuentan son las de
      // cada bulto, y así lo hace también la integración oficial.
      'alto' => '',
      'largo' => '',
      'ancho' => '',
      'producto' => $envio->servicio->codigoProducto(),
      // Portes pagados en origen. La tienda cobra el envío al cliente, así que
      // nunca son debidos.
      'portes' => 'P',
      // La tienda cobra por pasarela: no hay contrareembolso ni seguro
      // declarado, y exponerlos exigiría un circuito contable que no existe.
      'reembolso' => '',
      'entrSabado' => $envio->entregaSabado ? 'S' : 'N',
      'seguro' => '',
      'numEnvioVuelta' => '',
      'listaBultos' => $bultos,
      // Oficina elegida. Vacío en las entregas a domicilio.
      'codDirecDestino' => $envio->codigoOficina ?? '',
      'password' => '',
      'listaInformacionAdicional' => [$this->informacionAdicional($envio)],
    ];

    // Solo se manda con PaqPunto: en el resto de productos la API no espera
    // esta clave.
    if ($envio->idPuntoConveniencia !== NULL && $envio->idPuntoConveniencia !== '') {
      $payload['idPtoExterno'] = $envio->idPuntoConveniencia;
    }

    return $payload;
  }

  /**
   * Construye la lista de bultos.
   *
   * @return list<array<string, mixed>>
   *   Un elemento por paquete.
   */
  private function listaBultos(DatosEnvio $envio, string $observacionesEnvio): array {
    $bultos = $envio->bultos !== [] ? $envio->bultos : [new Bulto()];
    $pesosIndividuales = $this->tienePesosIndividuales($envio);

    $lista = [];
    $orden = 0;
    foreach ($bultos as $bulto) {
      $orden++;
      $lista[] = [
        'alto' => $this->normalizador->metros($bulto->alto),
        'ancho' => $this->normalizador->metros($bulto->ancho),
        'codBultoCli' => $orden,
        // Lo devuelve la API en la respuesta del alta, uno por bulto.
        'codUnico' => '',
        'descripcion' => '',
        // Con un peso único para toda la expedición, cada bulto va a cero y el
        // total viaja en la raíz. Es lo que espera la API.
        'kilos' => $pesosIndividuales ? $this->normalizador->kilos($bulto->peso) : 0,
        'largo' => $this->normalizador->metros($bulto->largo),
        'observaciones' => $bulto->observaciones !== ''
          ? $this->normalizador->observaciones($bulto->observaciones)
          : $observacionesEnvio,
        'orden' => $orden,
        'referencia' => '',
        'volumen' => '',
      ];
    }

    return $lista;
  }

  /**
   * Calcula el peso que va en la raíz del payload.
   */
  private function kilosTotales(DatosEnvio $envio): string {
    if (!$this->tienePesosIndividuales($envio)) {
      return $this->normalizador->kilos($envio->pesoTotal);
    }

    $suma = NULL;
    foreach ($envio->bultos as $bulto) {
      if ($bulto->peso === NULL) {
        continue;
      }
      $enGramos = $bulto->peso->convert(WeightUnit::GRAM);
      $suma = $suma === NULL ? $enGramos : $suma->add($enGramos);
    }

    return $this->normalizador->kilos($suma ?? $envio->pesoTotal);
  }

  /**
   * Indica si el operario declaró un peso por bulto.
   *
   * Con un solo bulto nunca: su peso es el de la expedición y va en la raíz.
   */
  private function tienePesosIndividuales(DatosEnvio $envio): bool {
    if (count($envio->bultos) < 2) {
      return FALSE;
    }

    foreach ($envio->bultos as $bulto) {
      if ($bulto->peso === NULL) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Construye el bloque de información adicional.
   *
   * Es un único objeto dentro de un array. Ahí van el formato de etiqueta, el
   * logotipo y la recogida.
   *
   * @return array<string, string>
   *   El bloque.
   */
  private function informacionAdicional(DatosEnvio $envio): array {
    $recogida = $envio->recogida;

    $bloque = [
      'tipoEtiqueta' => self::TIPO_ETIQUETA_ALTA,
      // La etiqueta no se pide aquí: se descarga después con su propia llamada,
      // que permite elegir formato y posición sin repetir el alta.
      'etiquetaPDF' => 'N',
      'posicionEtiqueta' => '',
      'hideSender' => $envio->ocultarRemitente ? '1' : '0',
      'logoCliente' => $envio->logoBase64,
      'codificacionUnicaB64' => '1',
      'textoRemiAlternativo' => $this->normalizador->texto(
        $envio->textoRemitenteAlternativo,
        Normalizador::MAX_NOMBRE,
      ),
      'idioma' => strtoupper($envio->idioma),
      'creaRecogida' => $recogida !== NULL ? 'S' : 'N',
      'fechaRecogida' => $recogida !== NULL ? $this->normalizador->fecha($recogida->fecha) : '',
      'horaDesdeRecogida' => $recogida !== NULL ? $this->normalizador->hora($recogida->desde) : '',
      'horaHastaRecogida' => $recogida !== NULL ? $this->normalizador->hora($recogida->hasta) : '',
      'referenciaRecogida' => $recogida !== NULL
        ? $this->normalizador->texto($recogida->referencia, Normalizador::MAX_REFERENCIA)
        : '',
    ];

    // La API solo acepta el código AT en envíos internos de Portugal, y ahí lo
    // exige.
    if ($envio->esPortugalInterno()) {
      $bloque['codigoAT'] = $this->normalizador->texto($envio->codigoAt, 30);
    }

    return $bloque;
  }

}
