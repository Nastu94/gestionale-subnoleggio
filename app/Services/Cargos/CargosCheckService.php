<?php

namespace App\Services\Cargos;

use App\Models\User;

class CargosCheckService
{
    /**
     * IMPORTANTE:
     * - In DB la colonna record_preview è string(160)
     * - Quindi qui cappiamo SEMPRE a 160, anche se in config metti un valore più alto.
     */
    private const PREVIEW_LEN_MAX = 160;

    public function __construct(
        protected CargosRentalContextResolver $resolver,
        protected CargosContractPayloadBuilder $builder,
        protected CargosContractFixedWidthFormatter $formatter,
        protected CargosTokenProvider $tokenProvider,
        protected CargosApiClient $api,
        protected CargosTransmissionLogger $logger,
    ) {}

    /**
     * @return array{ok:bool, stage:string, errors:array<int,string>, response?:mixed}
     */
    public function checkRental(int $rentalId, ?User $operator = null): array
    {
        $action = 'check';

        $agencyId    = null;
        $operatorId  = $operator?->id;

        // 1) Resolver
        try {
            $ctx = $this->resolver->resolveOrFail($rentalId, $operator);

            $agencyId   = $ctx['agency']?->id ?? null;
            $operatorId = $ctx['operator']?->id ?? $operatorId;
        } catch (\Throwable $e) {
            $this->safeLog([
                'rental_id'              => $rentalId,
                'agency_organization_id' => $agencyId,
                'operator_user_id'       => $operatorId,
                'action'                 => $action,
                'ok'                     => false,
                'stage'                  => 'resolver',
                'error_message'          => $e->getMessage(),
            ]);

            return ['ok' => false, 'stage' => 'resolver', 'errors' => [$e->getMessage()]];
        }

        // 2) Builder
        $built = $this->builder->build($ctx);
        if (!($built['ok'] ?? false)) {
            $this->safeLog([
                'rental_id'              => $rentalId,
                'agency_organization_id' => $agencyId,
                'operator_user_id'       => $operatorId,
                'action'                 => $action,
                'ok'                     => false,
                'stage'                  => 'builder',
                'validation_errors'      => $built['errors'] ?? ['Errore builder'],
            ]);

            return ['ok' => false, 'stage' => 'builder', 'errors' => $built['errors'] ?? ['Errore builder']];
        }

        // 3) Formatter
        $fw = $this->formatter->format($built['payload']);
        if (!($fw['ok'] ?? false)) {
            $rec = isset($fw['record']) ? (string) $fw['record'] : '';

            $this->safeLog([
                'rental_id'              => $rentalId,
                'agency_organization_id' => $agencyId,
                'operator_user_id'       => $operatorId,
                'action'                 => $action,
                'ok'                     => false,
                'stage'                  => 'formatter',
                'record_length'          => $fw['length'] ?? null,
                'record_preview'         => $this->preview($rec),
                'validation_errors'      => $fw['errors'] ?? ['Errore formatter'],
            ]);

            return ['ok' => false, 'stage' => 'formatter', 'errors' => $fw['errors'] ?? ['Errore formatter']];
        }

        // Record pronto
        $record = (string) ($fw['record'] ?? '');
        $hash   = hash('sha256', $record);

        // 4) Token (stage dedicato)
        try {
            $auth     = $this->tokenProvider->getEncryptedBearer();
            $username = $this->tokenProvider->username();
        } catch (\Throwable $e) {
            $this->safeLog([
                'rental_id'              => $rentalId,
                'agency_organization_id' => $agencyId,
                'operator_user_id'       => $operatorId,
                'action'                 => $action,
                'ok'                     => false,
                'stage'                  => 'api.token',
                'request_hash'           => $hash,
                'record_length'          => strlen($record),
                'record_preview'         => $this->preview($record),
                'error_message'          => $e->getMessage(),
            ]);

            return ['ok' => false, 'stage' => 'api.token', 'errors' => [$e->getMessage()]];
        }

        // 5) API Check (stage dedicato)
        try {
            $resp = $this->api->check($auth['bearer'], $username, [$record]);

            $errors = [];
            if (array_is_list($resp)) {
                foreach ($resp as $i => $row) {
                    $esito = (bool) ($row['esito'] ?? false);
                    if (!$esito) {
                        $desc = $row['errore']['error_description']
                            ?? $row['errore']['error']
                            ?? 'Errore non specificato';
                        $errors[] = 'Riga ' . ($i + 1) . ': ' . $desc;
                    }
                }
            }

            $ok = count($errors) === 0;

            $this->safeLog([
                'rental_id'              => $rentalId,
                'agency_organization_id' => $agencyId,
                'operator_user_id'       => $operatorId,
                'action'                 => $action,
                'ok'                     => $ok,
                'stage'                  => 'api.check',
                'request_hash'           => $hash,
                'record_length'          => strlen($record),
                'record_preview'         => $this->preview($record),

                // ✅ salva il record completo SOLO se abilitato (default: NO)
                'record'                 => $this->recordToStore($record),

                'validation_errors'      => $errors ?: null,
                'api_response'           => is_array($resp) ? $resp : null,
            ]);

            return [
                'ok'       => $ok,
                'stage'    => 'api.check',
                'errors'   => $errors,
                'response' => $resp,
            ];
        } catch (\Throwable $e) {
            $this->safeLog([
                'rental_id'              => $rentalId,
                'agency_organization_id' => $agencyId,
                'operator_user_id'       => $operatorId,
                'action'                 => $action,
                'ok'                     => false,
                'stage'                  => 'api.check',
                'request_hash'           => $hash,
                'record_length'          => strlen($record),
                'record_preview'         => $this->preview($record),
                'error_message'          => $e->getMessage(),
            ]);

            return ['ok' => false, 'stage' => 'api.check', 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Preview coerente con DB: max 160.
     */
    private function preview(?string $record): ?string
    {
        $record = is_string($record) ? $record : '';
        $record = (string) $record;

        if (trim($record) === '') {
            return null;
        }

        $len = (int) config('cargos.preview_len', self::PREVIEW_LEN_MAX);
        if ($len <= 0) {
            return null;
        }

        // Cappiamo SEMPRE a 160 per evitare errori DB
        $len = min($len, self::PREVIEW_LEN_MAX);

        return substr($record, 0, $len);
    }

    /**
     * Salva il record completo solo se abilitato.
     */
    private function recordToStore(string $record): ?string
    {
        $store = (bool) config('cargos.store_full_record', false);
        return $store ? $record : null;
    }

    protected function safeLog(array $data): void
    {
        try {
            $this->logger->log($data);
        } catch (\Throwable) {
            // best-effort
        }
    }
}
