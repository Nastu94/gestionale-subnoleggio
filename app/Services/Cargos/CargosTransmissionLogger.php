<?php

namespace App\Services\Cargos;

use App\Models\CargosTransmission;

class CargosTransmissionLogger
{
    /**
     * @param array{
     *  rental_id?:int|null,
     *  agency_organization_id?:int|null,
     *  operator_user_id?:int|null,
     *  linked_check_id?:int|null,
     *  action:string,
     *  ok:bool,
     *  dry_run?:bool,
     *  stage?:string|null,
     *  request_hash?:string|null,
     *  record_length?:int|null,
     *  record_preview?:string|null,
     *  record?:string|null,
     *  validation_errors?:array|null,
     *  api_response?:array|null,
     *  error_message?:string|null,
     *
     *  // ✅ nuovi (NON salvati in chiaro: vengono mascherati + hash)
     *  auth_source?:string|null,      // 'organization' | 'config'
     *  auth_username?:string|null,
     *  auth_agency_id?:string|null
     * } $data
     */
    public function log(array $data): CargosTransmission
    {
        $username = isset($data['auth_username']) ? (string) $data['auth_username'] : null;
        $agencyId = isset($data['auth_agency_id']) ? (string) $data['auth_agency_id'] : null;

        return CargosTransmission::create([
            'rental_id'              => $data['rental_id'] ?? null,
            'agency_organization_id' => $data['agency_organization_id'] ?? null,
            'operator_user_id'       => $data['operator_user_id'] ?? null,

            'linked_check_id'        => $data['linked_check_id'] ?? null,
            'action'                 => $data['action'],
            'ok'                     => (bool) $data['ok'],
            'dry_run'                => (bool) ($data['dry_run'] ?? false),
            'stage'                  => $data['stage'] ?? null,

            // ✅ auth meta
            'auth_source'            => $data['auth_source'] ?? null,
            'auth_username_masked'   => $this->mask($username),
            'auth_username_hash'     => $this->hash($username),
            'auth_agency_id_masked'  => $this->mask($agencyId),
            'auth_agency_id_hash'    => $this->hash($agencyId),

            'request_hash'           => $data['request_hash'] ?? null,
            'record_length'          => $data['record_length'] ?? null,
            'record_preview'         => $data['record_preview'] ?? null,
            'record'                 => $data['record'] ?? null,

            'validation_errors'      => $data['validation_errors'] ?? null,
            'api_response'           => $data['api_response'] ?? null,
            'error_message'          => $data['error_message'] ?? null,
        ]);
    }

    /**
     * Maschera un identificativo (debug leggibile senza esporre credenziali).
     * Esempio: ABCDEFGH -> AB****GH
     */
    protected function mask(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        $keepStart = 2;
        $keepEnd   = 2;

        return substr($value, 0, $keepStart)
            . str_repeat('*', max(1, $len - ($keepStart + $keepEnd)))
            . substr($value, -$keepEnd);
    }

    /**
     * Hash sha256 (utile per confronti/ricerche senza salvare il valore in chiaro).
     */
    protected function hash(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        return hash('sha256', $value);
    }

    /**
     * Ultimo CHECK per un rental (utile per pre-flight guard e debug).
     */
    public function lastCheckForRental(int $rentalId): ?CargosTransmission
    {
        return CargosTransmission::query()
            ->where('action', 'check')
            ->where('rental_id', $rentalId)
            ->latest('id')
            ->first();
    }

    /**
     * Ultimo CHECK OK per un rental (utile per pre-flight guard e debug).
     */
    public function lastOkCheckForRental(int $rentalId): ?CargosTransmission
    {
        return CargosTransmission::query()
            ->where('action', 'check')
            ->where('rental_id', $rentalId)
            ->where('ok', true)
            ->latest('id')
            ->first();
    }

    /**
     * Ultimo CHECK OK per un rental con specifico hash (utile per pre-flight guard e debug).
     */
    public function lastOkCheckForRentalHash(int $rentalId, string $hash): ?CargosTransmission
    {
        return CargosTransmission::query()
            ->where('action', 'check')
            ->where('rental_id', $rentalId)
            ->where('ok', true)
            ->where('request_hash', $hash)
            ->latest('id')
            ->first();
    }
}
