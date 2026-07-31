<?php

namespace CorreosOficial\Classes;

/**
 * Encriptación / desencriptación de credenciales
 *
 * es wp_options (tabla de BD)
 * Las claves se generan una única vez con random_bytes() y se persisten con
 * add_option() para que persistan durante actualizaciones del plugin.
 */
class CorreosOficialCrypto
{
    const METHOD         = 'aes-128-cbc';
    const OPTION_KEY     = 'CORREOS_OFICIAL_KEY_SECRET';
    const SECRET_ERROR   = 'Could not retrieve the secret, please try entering your credentials again. If the error persists, contact support.';

    /** @var array|null Cache en memoria para evitar múltiples get_option por request */
    private static $cachedKeys = null;

    /** @var string|null Ruta base para ficheros legacy (override para tests) */
    private static $legacyBaseDir = null;

    /**
     * Cifra una cadena.
     *
     * @param string $data Texto plano a cifrar.
     * @return string Texto cifrado (base64 implícito de openssl_encrypt).
     */
    public static function encrypt($data)
    {
        $keys        = self::getKeys();
        $secret_hash = $keys['hash'];
        $iv          = $keys['iv'];

        return openssl_encrypt($data, self::METHOD, $secret_hash, 0, $iv);
    }

    /**
     * Descifra una cadena.
     *
     * @param string $data Texto cifrado.
     * @return string|false Texto plano o false si la desencriptación falla.
     */
    public static function decrypt($data)
    {
        $keys        = self::getKeys();
        $secret_hash = $keys['hash'];
        $iv          = $keys['iv'];

        $result = openssl_decrypt($data, self::METHOD, $secret_hash, 0, $iv);

        if ($result === false || $result === '') {
            return false;
        }

        return $result;
    }

    /**
     * Devuelve el mensaje de error traducido para fallos de descifrado.
     *
     * @return string
     */
    public static function getDecryptErrorMessage()
    {
        return function_exists('__')
            ? __( 'Could not retrieve the secret, please try entering your credentials again. If the error persists, contact support.', 'correosoficial' )
            : self::SECRET_ERROR;
    }

    /**
     * Obtiene las claves de cifrado desde wp_options.
     * Si no existen:
     *   1. Intenta migrar las claves legacy de los ficheros openssl_shiv.
     *   2. Intenta migrar desde el option backup anterior (correosoficial_openssl_keys_backup).
     *   3. Si nada existe, genera claves nuevas.
     *
     * @return array{hash: string, iv: string} Claves binarias.
     */
    private static function getKeys()
    {
        if (self::$cachedKeys !== null) {
            return self::$cachedKeys;
        }

        $stored = get_option(self::OPTION_KEY);

        if (!empty($stored) && isset($stored['hash'], $stored['iv'])) {
            self::$cachedKeys = [
                'hash' => base64_decode($stored['hash']),
                'iv'   => base64_decode($stored['iv']),
            ];
            return self::$cachedKeys;
        }

        // Migración 1: ficheros legacy de vendor/ecommerce_common_lib
        $keys = self::migrateFromLegacyFiles();

        // Migración 2: option backup anterior
        if ($keys === null) {
            $keys = self::migrateFromLegacyOption();
        }

        // Generación nueva si no hay nada que migrar
        if ($keys === null) {
            $keys = [
                'hash' => random_bytes(32),
                'iv'   => random_bytes(16),
            ];
        }

        self::persistKeys($keys);
        self::$cachedKeys = $keys;

        return $keys;
    }

    /**
     * Guarda las claves en wp_options de forma atómica.
     */
    private static function persistKeys(array $keys)
    {
        $serialized = [
            'hash' => base64_encode($keys['hash']),
            'iv'   => base64_encode($keys['iv']),
        ];

        // add_option no sobreescribe si ya existe (seguridad contra race conditions)
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $serialized, '', false);
        } else {
            update_option(self::OPTION_KEY, $serialized, false);
        }
    }

    /**
     * Intenta leer las claves de los ficheros legacy openssl_shiv.
     *
     * @return array{hash: string, iv: string}|null
     */
    private static function migrateFromLegacyFiles()
    {
        $baseDir  = self::$legacyBaseDir ?? dirname(__DIR__) . '/vendor/ecommerce_common_lib/Commons/openssl/openssl_shiv';
        $hashFile = $baseDir . '/secret.hash.php';
        $ivFile   = $baseDir . '/secret.iv.php';

        if (!file_exists($hashFile) || !file_exists($ivFile)) {
            return null;
        }

        $phpTags    = ['<?php "', '" ?>'];
        $hashRaw    = file_get_contents($hashFile);
        $ivRaw      = file_get_contents($ivFile);

        if (empty($hashRaw) || empty($ivRaw)) {
            return null;
        }

        $hash = base64_decode(str_replace($phpTags, '', $hashRaw));
        $iv   = base64_decode(str_replace($phpTags, '', $ivRaw));

        if (empty($hash) || empty($iv)) {
            return null;
        }

        return ['hash' => $hash, 'iv' => $iv];
    }

    /**
     * Intenta leer las claves del option backup anterior (v2.6.0 interim).
     *
     * @return array{hash: string, iv: string}|null
     */
    private static function migrateFromLegacyOption()
    {
        $backup = get_option('correosoficial_openssl_keys_backup');

        if (empty($backup) || !isset($backup['hash'], $backup['iv'])) {
            return null;
        }

        $hash = base64_decode($backup['hash']);
        $iv   = base64_decode($backup['iv']);

        if (empty($hash) || empty($iv)) {
            return null;
        }

        return ['hash' => $hash, 'iv' => $iv];
    }

    /**
     * Resetea la caché en memoria (útil para tests).
     */
    public static function resetCache()
    {
        self::$cachedKeys = null;
    }

    /**
     * Override de la ruta base de ficheros legacy (solo para tests).
     */
    public static function setLegacyBaseDir($dir)
    {
        self::$legacyBaseDir = $dir;
    }
}
