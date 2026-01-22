<?php

namespace App\Services\Cargos;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CargosApiClient
{
    protected function baseUrl(): string
    {
        $base = trim((string) config('cargos.base_url'));
        if ($base === '') {
            throw new RuntimeException('CARGOS base_url mancante in config/.env.');
        }
        return rtrim($base, '/') . '/';
    }

    protected function timeout(): int
    {
        return (int) config('cargos.timeout', 15);
    }

    protected function verifySsl(): bool
    {
        return (bool) config('cargos.verify_ssl', true);
    }

    /**
     * Token: Basic Auth con USERNAME + PASSWORD (come da manuale).
     *
     * @return array<string,mixed>
     */
    public function token(string $username, string $password): array
    {
        $res = Http::baseUrl($this->baseUrl())
            ->timeout($this->timeout())
            ->connectTimeout($this->timeout()) // 👈 importante per DNS/connect
            ->retry(2, 250)                    // 👈 2 retry veloci
            ->withOptions(['verify' => $this->verifySsl()])
            ->withBasicAuth($username, $password)
            ->acceptJson()
            ->get('api/Token');

        return $this->decodeOrThrow($res, 'Token');
    }

    /**
     * Check: OAuth2 Bearer col token CRIPTATO + header Organization=USERNAME.
     *
     * Body: array di stringhe (righe fixed-width).
     *
     * @param  array<int,string> $records
     * @return array<int, array<string,mixed>>|array<string,mixed>
     */
    public function check(string $encryptedBearer, string $username, array $records): array
    {
        $res = Http::baseUrl($this->baseUrl())
            ->timeout($this->timeout())
            ->connectTimeout($this->timeout())
            ->retry(2, 250)
            ->withOptions(['verify' => $this->verifySsl()])
            ->withToken($encryptedBearer)
            ->withHeaders(['Organization' => $username])
            ->acceptJson()
            ->asJson()
            ->post('api/Check', $records);

        return $this->decodeOrThrow($res, 'Check');
    }

    /**
     * Send: OAuth2 Bearer col token CRIPTATO + header Organization=USERNAME.
     *
     * Body: array di stringhe (righe fixed-width).
     *
     * @param  array<int,string> $records
     * @return array<int, array<string,mixed>>|array<string,mixed>
     */
    public function send(string $encryptedBearer, string $username, array $records): array
    {
        $res = Http::baseUrl($this->baseUrl())
            ->timeout($this->timeout())
            ->withOptions(['verify' => $this->verifySsl()])
            ->withToken($encryptedBearer)
            ->withHeaders(['Organization' => $username])
            ->acceptJson()
            ->asJson()
            ->post('api/Send', $records);

        return $this->decodeOrThrow($res, 'Send');
    }

    /**
     * @return array<string,mixed>|array<int, array<string,mixed>>
     */
    protected function decodeOrThrow(Response $res, string $op): array
    {
        $json = $res->json();

        if ($res->successful() && is_array($json)) {
            return $json;
        }

        // Errore “strutturato” (errore/error_description) oppure testo grezzo
        $errDesc = is_array($json)
            ? (($json['error_description'] ?? null) ?: ($json['errore']['error_description'] ?? null) ?: ($json['errore']['error'] ?? null))
            : null;

        $msg = $errDesc ?: ($res->body() ?: 'Errore sconosciuto');
        throw new RuntimeException("CARGOS {$op} KO: {$msg}");
    }
}
