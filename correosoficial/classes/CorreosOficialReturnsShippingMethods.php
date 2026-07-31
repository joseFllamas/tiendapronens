<?php
namespace CorreosOficial\Classes;

/**
 * Clase para gestionar los métodos de envío de devoluciones según países
 */
class CorreosOficialReturnsShippingMethods 
{
    // Países con Correos S0148 + Recogida
    const CORREOS_RETURNS_COUNTRIES = ['ES', 'AD'];
    
    // Países CEX disponible
    const CEX_RETURNS_COUNTRIES = ['ES', 'PT'];
    
    // Países con Correos Internacional S0159 SIN Recogida  
    const CORREOS_INTERNATIONAL_S0159_COUNTRIES = ['PT', 'DE', 'AT', 'BE', 'CZ', 'HR', 'DK', 'SI', 'SK', 'EE', 'FR', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'RO', 'GB'];

    /**
     * Verifica si un país está soportado para devoluciones
     */
    public static function isReturnsSupportedCountry($country_code) {
        $country_code = strtoupper($country_code);
        return in_array($country_code, self::CORREOS_RETURNS_COUNTRIES) || 
               in_array($country_code, self::CORREOS_INTERNATIONAL_S0159_COUNTRIES);
    }

    /**
     * Obtiene los métodos de devolución disponibles para un país
     */
    public static function getAvailableReturnMethods($country_code) {
        $country_code = strtoupper($country_code);
        $methods = [];

        // España y Andorra: Correos Nacional S0148 + Recogida
        if (in_array($country_code, self::CORREOS_RETURNS_COUNTRIES)) {
            $methods[] = [
                'operator' => 'correos_national',
                'service_code' => 'S0148',
                'service_name' => __('Correos Nacional S0148 + Recogida', 'correosoficial'),
                'allow_pickup' => true
            ];
        }

        // Portugal y países UE + UK: Correos Internacional S0159 SIN Recogida
        if (in_array($country_code, self::CORREOS_INTERNATIONAL_S0159_COUNTRIES)) {
            $methods[] = [
                'operator' => 'correos_international',
                'service_code' => 'S0159', 
                'service_name' => __('Correos Internacional S0159 SIN Recogida', 'correosoficial'),
                'allow_pickup' => false
            ];
        }

        // CEX disponible para España y Portugal
        if (in_array($country_code, self::CEX_RETURNS_COUNTRIES)) {
            $methods[] = [
                'operator' => 'cex',
                'service_code' => '63',
                'service_name' => __('CEX 63 + Recogida', 'correosoficial'),
                'allow_pickup' => true
            ];
        }

        return $methods;
    }
}