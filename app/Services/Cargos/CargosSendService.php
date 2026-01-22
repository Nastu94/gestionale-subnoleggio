<?php

namespace App\Services\Cargos;

use App\Models\User;

class CargosSendService
{
    /**
     * record_preview in DB è string(160)
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
     * @return array{ok:bool, stage:string, errors:array<int,string>, response?:mixed, dry_run?:bool}
     */
    public function sendRental(int $rentalId, ?User $operator = null): array
    {
        $action = 'send';
        $agencyId = null;
        $operatorId = $operator?->id;

        // 1) Resolver
        try {
            $ctx = $this->resolver->resolveOrFail($rentalId, $operator);
            $agencyId   = $ctx['agency']?->id ?? null;
            $operatorId = $ctx['operator']?->id ?? $operatorId;
        } catch (\Throwable $e) {
            $this->safeLog([
                'rental_id' => $rentalId,
                'agency_organization_id' => $agencyId,
                'operator_user_id' => $operatorId,
                'action' => $action,
                'ok' => false,
                'stage' => 'resolver',
                'error_message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'stage' => 'resolver', 'errors' => [$e->getMessage()]];
        }

        // 2) Builder
        $built = $this->builder->build($ctx);
        if (!($built['ok'] ?? false)) {
            $this->safeLog([
                'rental_id' => $rentalId,
                'agency_organization_id' => $agencyId,
                'operator_user_id' => $operatorId,
                'action' => $action,
                'ok' => false,
                'stage' => 'builder',
                'validation_errors' => $built['errors'] ?? ['Errore builder'],
            ]);

            return ['ok' => false, 'stage' => 'builder', 'errors' => $built['errors'] ?? ['Errore builder']];
        }

        // 3) Formatter
        $fw = $this->formatter->format($built['payload']);
        if (!($fw['ok'] ?? false)) {
            $rec = isset($fw['record']) ? (string) $fw['record'] : '';

            $this->safeLog([
                'rental_id' => $rentalId,
                'agency_organization_id' => $agencyId,
                'operator_user_id' => $operatorId,
                'action' => $action,
                'ok' => false,
                'stage' => 'formatter',
                'record_length' => $fw['length'] ?? null,
                'record_preview' => $this->preview($rec),
                'validation_errors' => $fw['errors'] ?? ['Errore formatter'],
            ]);

            return ['ok' => false, 'stage' => 'formatter', 'errors' => $fw['errors'] ?? ['Errore formatter']];
        }

        $record = (string) ($fw['record'] ?? '');
        $hash   = hash('sha256', $record);

        // 4) PRE-FLIGHT GUARD: deve esistere un CHECK OK per lo stesso hash
        $okCheck = $this->logger->lastOkCheckForRentalHash($rentalId, $hash);

        if (!$okCheck) {
            $msg = 'Pre-flight SEND KO: non esiste un CHECK OK recente con lo stesso hash. Esegui CHECK e non modificare i dati prima del SEND.';

            $this->safeLog([
                'rental_id' => $rentalId,
                'agency_organization_id' => $agencyId,
                'operator_user_id' => $operatorId,
                'action' => $action,
                'ok' => false,
                'stage' => 'preflight.send',
                'request_hash' => $hash,
                'record_length' => strlen($record),
                'record_preview' => $this->preview($record),
                'error_message' => $msg,
            ]);

            return ['ok' => false, 'stage' => 'preflight.send', 'errors' => [$msg]];
        }

        // 5) DRY-RUN in non-production
        if (!app()->environment('production')) {
            $this->safeLog([
                'rental_id' => $rentalId,
                'agency_organization_id' => $agencyId,
                'operator_user_id' => $operatorId,
                'action' => $action,
                'ok' => true,
                'stage' => 'preflight.send',
                'request_hash' => $hash,
                'record_length' => strlen($record),
                'record_preview' => $this->preview($record),

                // ✅ salva il record completo SOLO se abilitato (default: NO)
                'record' => $this->recordToStore($record),

                // qui va bene: è JSON, non dipende dalle colonne future
                'api_response' => [
                    [
                        'dry_run' => true,
                        'note' => 'SEND non eseguito (ambiente non-production). Pre-flight OK.',
                        'linked_check_id' => $okCheck->id,
                        'hash' => $hash,
                    ]
                ],
            ]);

            return [
                'ok' => true,
                'stage' => 'preflight.send',
                'errors' => [],
                'dry_run' => true,
                'response' => [
                    'dry_run' => true,
                    'linked_check_id' => $okCheck->id,
                    'hash' => $hash,
                ],
            ];
        }

        // 6) PRODUCTION: chiamata reale a api/Send
        try {
            $auth     = $this->tokenProvider->getEncryptedBearer();
            $username = $this->tokenProvider->username();

            $resp = $this->api->send($auth['bearer'], $username, [$record]);

            $errors = [];
            if (array_is_list($resp)) {
                foreach ($resp as $i => $row) {
                    $esito = (bool) ($row['esito'] ?? false);
                    if (!$esito) {
                        $desc = $row['errore']['error_description'] ?? $row['errore']['error'] ?? 'Errore non specificato';
                        $errors[] = 'Riga ' . ($i + 1) . ': ' . $desc;
                    }
                }
            }

            $ok = count($errors) === 0;

            $this->safeLog([
                'rental_id' => $rentalId,
                'agency_organization_id' => $agencyId,
                'operator_user_id' => $operatorId,
                'action' => $action,
                'ok' => $ok,
                'stage' => 'api.send',
                'request_hash' => $hash,
                'record_length' => strlen($record),
                'record_preview' => $this->preview($record),

                // ✅ salva il record completo SOLO se abilitato (default: NO)
                'record' => $this->recordToStore($record),

                'validation_errors' => $errors ?: null,
                'api_response' => is_array($resp) ? $resp : null,
            ]);

            return [
                'ok' => $ok,
                'stage' => 'api.send',
                'errors' => $errors,
                'response' => $resp,
            ];
        } catch (\Throwable $e) {
            $this->safeLog([
                'rental_id' => $rentalId,
                'agency_organization_id' => $agencyId,
                'operator_user_id' => $operatorId,
                'action' => $action,
                'ok' => false,
                'stage' => 'api.send',
                'request_hash' => $hash,
                'record_length' => strlen($record),
                'record_preview' => $this->preview($record),
                'error_message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'stage' => 'api.send', 'errors' => [$e->getMessage()]];
        }
    }

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

        $len = min($len, self::PREVIEW_LEN_MAX);

        return substr($record, 0, $len);
    }

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
