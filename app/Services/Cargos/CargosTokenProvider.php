<?php

namespace App\Services\Cargos;

use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Recupera un token valido e lo restituisce già criptato (Bearer) secondo specifica CARGOS.
 * Cache key basata su username.
 *
 * NOTA:
 * - 'source' = da dove arrivano le credenziali ('config'|'organization')  ✅
 * - 'token_source' = da dove arriva il token ('cache'|'api')              ✅
 */
class CargosTokenProvider
{
    public function __construct(
        protected CargosApiClient $api,
        protected CargosTokenCryptor $cryptor,
        protected CargosCredentialsResolver $credentialsResolver,
    ) {}

    /**
     * @return array{
     *   bearer:string,
     *   expires_at:string,
     *   source:string,        // ✅ cred source: config|organization
     *   token_source:string,  // ✅ token source: cache|api
     *   username:string,
     *   agency_id:string
     * }
     */
    public function getEncryptedBearerForAgency(?Organization $agency): array
    {
        $creds = $this->credentialsResolver->resolveForAgency($agency);

        $credSource = (string) ($creds['source'] ?? 'config'); // config|organization

        $username = (string) $creds['username'];
        $password = (string) $creds['password'];
        $apiKey     = (string) ($creds['apikey'] ?? '');

        $cacheKey = 'cargos.token.v2.' . sha1($username);

        $cached = Cache::get($cacheKey);

        if (is_array($cached) && isset($cached['access_token'], $cached['expires_at'])) {
            $expiresAt = Carbon::parse($cached['expires_at']);

            if ($expiresAt->diffInSeconds(now(), false) < -60) {
                return [
                    'bearer'      => $this->cryptor->encryptWithApiKey((string) $cached['access_token'], $apiKey), // ✅
                    'expires_at'  => $expiresAt->toIso8601String(),
                    'source'      => $credSource,
                    'token_source'=> 'cache',
                    'username'    => $username,
                    'agency_id'   => (string) $creds['agency_id'],
                ];
            }
        }

        $tok = $this->api->token($username, $password);

        $accessToken = (string) ($tok['access_token'] ?? '');
        $expiresDate = (string) ($tok['expires_date'] ?? '');

        if ($accessToken === '' || $expiresDate === '') {
            throw new RuntimeException('CARGOS Token response incompleta: manca access_token o expires_date.');
        }

        $expiresAt = Carbon::parse($expiresDate);

        Cache::put($cacheKey, [
            'access_token' => $accessToken,
            'expires_at'   => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return [
            'bearer'      => $this->cryptor->encryptWithApiKey($accessToken, $apiKey), // ✅
            'expires_at'  => $expiresAt->toIso8601String(),
            'source'      => $credSource,
            'token_source'=> 'api',
            'username'    => $username,
            'agency_id'   => (string) $creds['agency_id'],
        ];
    }
}
