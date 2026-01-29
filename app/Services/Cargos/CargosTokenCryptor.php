<?php

namespace App\Services\Cargos;

use RuntimeException;

/**
 * Cripta l'access_token con AES-256-CBC usando la APIKEY personale CARGOS.
 *
 * Da manuale:
 * - Key = primi 32 caratteri della APIKEY
 * - IV  = successivi 16 caratteri della APIKEY
 * - AES CBC + PKCS7, output Base64
 */
class CargosTokenCryptor
{
    public function encrypt(string $accessToken): string
    {
        $apiKey = trim((string) config('cargos.apikey'));

        // Da manuale: lunghezza minima > 47 (32 Key + 16 IV = 48)
        if ($apiKey === '' || strlen($apiKey) < 48) {
            throw new RuntimeException('CARGOS APIKEY non valida: deve avere almeno 48 caratteri (32 Key + 16 IV).');
        }

        $key = substr($apiKey, 0, 32);
        $iv  = substr($apiKey, 32, 16);

        $raw = openssl_encrypt(
            $accessToken,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($raw === false) {
            $err = openssl_error_string() ?: 'openssl_encrypt returned false';
            throw new RuntimeException("CARGOS: criptazione AES fallita ({$err}).");
        }

        return base64_encode($raw);
    }

    /**
     * Nuovo: cifra usando una APIKEY passata (DB o env).
     */
    public function encryptWithApiKey(string $accessToken, string $apiKey): string
    {
        $apiKey = trim($apiKey);

        if ($apiKey === '' || strlen($apiKey) < 48) {
            throw new RuntimeException('CARGOS APIKEY non valida: deve avere almeno 48 caratteri (32 Key + 16 IV).');
        }

        $key = substr($apiKey, 0, 32);
        $iv  = substr($apiKey, 32, 16);

        $raw = openssl_encrypt(
            $accessToken,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($raw === false) {
            $err = openssl_error_string() ?: 'openssl_encrypt returned false';
            throw new RuntimeException("CARGOS: criptazione AES fallita ({$err}).");
        }

        return base64_encode($raw);
    }
}
