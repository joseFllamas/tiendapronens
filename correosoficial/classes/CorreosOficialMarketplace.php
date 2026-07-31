<?php

namespace CorreosOficial\Classes;

use CorreosOficial\Models\CorreosOficialConfig;

if (!defined('WPINC')) {
    exit;
}

/**
 * Gestión del canal Marketplace de Correos para WooCommerce.
 *
 * Centraliza la activación/desactivación del modo Marketplace, crea/elimina
 * la clave de WooCommerce REST API asociada y registra el estado de pedido
 * personalizado "Sent to Marketplace".
 */
class CorreosOficialMarketplace
{
    // ── Config keys ───────────────────────────────────────────────────────────

    /** Clave de configuración: estado del checkbox Marketplace. */
    const CONFIG_KEY_ACTIVATE        = 'ActivateMarketplace';

    /** Clave de configuración: ID de la fila en woocommerce_api_keys. */
    const CONFIG_KEY_API_KEY_ID      = 'MarketplaceApiKeyId';

    /** Clave de configuración: Consumer Key plain-text (mostrado al usuario). */
    const CONFIG_KEY_CONSUMER_KEY    = 'MarketplaceConsumerKey';

    /** Clave de configuración: Consumer Secret (mostrado al usuario). */
    const CONFIG_KEY_CONSUMER_SECRET = 'MarketplaceConsumerSecret';

    /** Clave de configuración: slug del estado de pedido de Marketplace. */
    const CONFIG_KEY_ORDER_STATUS    = 'MarketplaceOrderStatusSlug';

    /** Código de módulo enviado a CTRLVERS (WooCommerce). */
    const MODULE_CODE                = 'WOOT';

    /** Código de módulo enviado a CTRLVERS cuando Marketplace está activo. */
    const MODULE_CODE_MARKETPLACE    = 'WOOTM';

    // ── Order status ──────────────────────────────────────────────────────────

    /** Slug del estado de pedido personalizado de WooCommerce para Marketplace. */
    const ORDER_STATUS_SLUG  = 'wc-sent-marketplace';

    /** Etiqueta del estado de pedido personalizado. */
    const ORDER_STATUS_LABEL = 'Sent to Marketplace';

    // ── Order meta keys ───────────────────────────────────────────────────────

    /**
     * Meta key en el pedido WooCommerce donde el canal Marketplace escribe el
     * número de seguimiento (tracking number) una vez que el envío es registrado.
     * Prefijado con _ para que sea privado en el panel de WooCommerce.
     */
    const META_KEY_TRACKING_NUMBER = '_correosoficial_marketplace_tracking_number';

    // ── REST API resources ────────────────────────────────────────────────────

    /**
     * Recursos de la WooCommerce REST API expuestos cuando Marketplace está activo.
     * Estos endpoints son los equivalentes WooCommerce de los recursos PS expuestos
     * para la integración con el canal Marketplace de Correos.
     *
     * @var array<string, string[]>
     */
    const MARKETPLACE_RESOURCES = [
        'orders'           => ['GET', 'PUT'],
        'orders/statuses'  => ['GET'],
        'shipping_methods' => ['GET'],
    ];

    /**
     * IDs de los botones de acordeón de Ajustes que deben bloquearse
     * cuando Marketplace está activo.
     */
    const LOCKED_ACCORDION_SECTIONS = [
        'sender_block',      // Sección "SENDERS"
        'fulfillment_block', // Sección "FULFILLMENT SERVICE" (SGA)
    ];

    // ── WooCommerce check ─────────────────────────────────────────────────────

    /**
     * Comprueba si WooCommerce está instalado y activo.
     */
    public static function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }

    // ── Activación ────────────────────────────────────────────────────────────

    /**
     * Comprueba si el modo Marketplace está activo para la tienda actual.
     */
    public static function isMarketplaceEnabled(): bool
    {
        $status = CorreosOficialConfig::get_config_status(self::CONFIG_KEY_ACTIVATE);
        return !empty($status->value) && $status->value === 'on';
    }

    /**
     * Devuelve el código de módulo para CTRLVERS según el estado de Marketplace.
     */
    public static function getModuleCode(): string
    {
        return self::isMarketplaceEnabled()
            ? self::MODULE_CODE_MARKETPLACE
            : self::MODULE_CODE;
    }

    /**
     * Activa el modo Marketplace persistiendo la configuración.
     */
    public static function enableMarketplace(): bool
    {
        return (bool) CorreosOficialConfig::save(self::CONFIG_KEY_ACTIVATE, 'on', 'checkbox');
    }

    /**
     * Desactiva el modo Marketplace persistiendo la configuración.
     */
    public static function disableMarketplace(): bool
    {
        return (bool) CorreosOficialConfig::save(self::CONFIG_KEY_ACTIVATE, '', 'checkbox');
    }

    // ── WooCommerce REST API key management ───────────────────────────────────

    /**
     * Crea una nueva clave de WooCommerce REST API para Marketplace.
     * Si ya existe una key_id almacenada y la fila sigue en la DB, no hace nada.
     * Almacena el Consumer Key (plain-text) y Consumer Secret en configuración.
     *
     * @return bool
     */
    public static function createOrActivateApiKey(): bool
    {
        global $wpdb;

        // Reusar key existente si sigue presente en la DB
        $keyId = (int) CorreosOficialConfig::getConfigValue(self::CONFIG_KEY_API_KEY_ID);
        if ($keyId > 0) {
            $exists = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_api_keys WHERE key_id = %d",
                    $keyId
                )
            );
            if ($exists) {
                return true;
            }
            // La key fue eliminada externamente – crear una nueva
        }

        if (!function_exists('wc_rand_hash') || !function_exists('wc_api_hash')) {
            return false;
        }

        $consumer_key    = 'ck_' . wc_rand_hash();
        $consumer_secret = 'cs_' . wc_rand_hash();

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'woocommerce_api_keys',
            [
                'user_id'         => get_current_user_id(),
                'description'     => 'Correos Marketplace',
                'permissions'     => 'read_write',
                'consumer_key'    => wc_api_hash($consumer_key),
                'consumer_secret' => $consumer_secret,
                'truncated_key'   => substr($consumer_key, -7),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return false;
        }

        $newKeyId = (int) $wpdb->insert_id;

        CorreosOficialConfig::save(self::CONFIG_KEY_API_KEY_ID, (string) $newKeyId, 'text');
        CorreosOficialConfig::save(self::CONFIG_KEY_CONSUMER_KEY, $consumer_key, 'text');
        CorreosOficialConfig::save(self::CONFIG_KEY_CONSUMER_SECRET, $consumer_secret, 'text');

        return true;
    }

    /**
     * Elimina la clave de WooCommerce REST API de Marketplace y borra los valores
     * almacenados. Al desactivar Marketplace el tercero ya no puede autenticarse.
     *
     * @return bool
     */
    public static function deleteApiKey(): bool
    {
        global $wpdb;

        $keyId = (int) CorreosOficialConfig::getConfigValue(self::CONFIG_KEY_API_KEY_ID);

        if ($keyId > 0) {
            $wpdb->delete(
                $wpdb->prefix . 'woocommerce_api_keys',
                ['key_id' => $keyId],
                ['%d']
            );
        }

        CorreosOficialConfig::save(self::CONFIG_KEY_API_KEY_ID, '', 'text');
        CorreosOficialConfig::save(self::CONFIG_KEY_CONSUMER_KEY, '', 'text');
        CorreosOficialConfig::save(self::CONFIG_KEY_CONSUMER_SECRET, '', 'text');

        return true;
    }

    /**
     * Devuelve el Consumer Key plain-text almacenado (vacío si no existe).
     */
    public static function getConsumerKey(): string
    {
        return (string) CorreosOficialConfig::getConfigValue(self::CONFIG_KEY_CONSUMER_KEY);
    }

    /**
     * Devuelve el Consumer Secret almacenado (vacío si no existe).
     */
    public static function getConsumerSecret(): string
    {
        return (string) CorreosOficialConfig::getConfigValue(self::CONFIG_KEY_CONSUMER_SECRET);
    }

    /**
     * Devuelve la URL base de la WooCommerce REST API v3
     * (p.ej. https://mi-tienda.com/wp-json/wc/v3/).
     */
    public static function getApiBaseUrl(): string
    {
        return function_exists('rest_url') ? rest_url('wc/v3/') : '';
    }

    // ── Order Status management ───────────────────────────────────────────────

    /**
     * Registra el estado de pedido personalizado "Sent to Marketplace" en WordPress.
     * Debe llamarse desde el hook 'init'.
     */
    public static function registerOrderStatus(): void
    {
        register_post_status(self::ORDER_STATUS_SLUG, [
            'label'                     => __('Sent to Marketplace', 'correosoficial'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: order count */
            'label_count'               => _n_noop(
                'Sent to Marketplace <span class="count">(%s)</span>',
                'Sent to Marketplace <span class="count">(%s)</span>'
            ),
        ]);
    }

    /**
     * Añade el estado Marketplace a la lista de estados de WooCommerce.
     * Debe usarse como filtro 'wc_order_statuses'.
     *
     * @param array $statuses
     * @return array
     */
    public static function addOrderStatus(array $statuses): array
    {
        $statuses[self::ORDER_STATUS_SLUG] = __('Sent to Marketplace', 'correosoficial');
        return $statuses;
    }

    /**
     * Persiste el slug del estado de pedido en configuración y lo devuelve.
     */
    public static function createOrFindOrderStatus(): string
    {
        CorreosOficialConfig::save(self::CONFIG_KEY_ORDER_STATUS, self::ORDER_STATUS_SLUG, 'text');
        return self::ORDER_STATUS_SLUG;
    }
}
