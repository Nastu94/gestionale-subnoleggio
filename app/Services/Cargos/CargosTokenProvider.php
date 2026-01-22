<?php

namespace App\Services\Cargos;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Recupera un token valido e lo restituisce già criptato (Bearer) secondo specifica CARGOS.
 */
class CargosTokenProvider
{
    public function __construct(
        protected CargosApiClient $api,
        protected CargosTokenCryptor $cryptor,
    ) {}

    public function username(): string
    {
        $u = trim((string) config('cargos.admin.username'));
        if ($u === '') {
            throw new RuntimeException('CARGOS admin.username mancante in config/.env.');
        }
        return $u;
    }

    public function password(): string
    {
        $p = (string) config('cargos.admin.password');
        if (trim($p) === '') {
            throw new RuntimeException('CARGOS admin.password mancante in config/.env.');
        }
        return $p;
    }

    /**
     * @return array{bearer:string, expires_at:string, source:string}
     */
    public function getEncryptedBearer(): array
    {
        $cacheKey = 'cargos.token.v1';

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['access_token'], $cached['expires_at'])) {
            $expiresAt = Carbon::parse($cached['expires_at']);

            // buffer: se scade entro 60s, rigeneriamo
            if ($expiresAt->diffInSeconds(now(), false) < -60) {
                return [
                    'bearer'     => $this->cryptor->encrypt((string) $cached['access_token']),
                    'expires_at' => $expiresAt->toIso8601String(),
                    'source'     => 'cache',
                ];
            }
        }

        $tok = $this->api->token($this->username(), $this->password());

        $accessToken = (string) ($tok['access_token'] ?? '');
        $expiresDate = (string) ($tok['expires_date'] ?? '');

        if ($accessToken === '' || $expiresDate === '') {
            throw new RuntimeException('CARGOS Token response incompleta: manca access_token o expires_date.');
        }

        $expiresAt = Carbon::parse($expiresDate);

        // Cache fino a scadenza (Laravel accetta Carbon come TTL)
        Cache::put($cacheKey, [
            'access_token' => $accessToken,
            'expires_at'   => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return [
            'bearer'     => $this->cryptor->encrypt($accessToken),
            'expires_at' => $expiresAt->toIso8601String(),
            'source'     => 'api',
        ];
    }
}
