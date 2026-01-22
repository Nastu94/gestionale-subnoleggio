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
     *  error_message?:string|null
     * } $data
     */
    public function log(array $data): CargosTransmission
    {
        return CargosTransmission::create([
            'rental_id'              => $data['rental_id'] ?? null,
            'agency_organization_id' => $data['agency_organization_id'] ?? null,
            'operator_user_id'       => $data['operator_user_id'] ?? null,

            'linked_check_id'        => $data['linked_check_id'] ?? null, // ✅
            'action'                 => $data['action'],
            'ok'                     => (bool) $data['ok'],
            'dry_run'                => (bool) ($data['dry_run'] ?? false), // ✅
            'stage'                  => $data['stage'] ?? null,

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
