<?php

namespace App\Services\Cargos;

use App\Models\Organization;
use Carbon\Carbon;
use RuntimeException;

class CargosCredentialsResolver
{
    /**
     * @return array{
     *   username:string,
     *   password:string,
     *   puk:string,
     *   agency_id:string,
     *   apikey:string,
     *   source:string
     * }
     */
    public function resolveForAgency(?Organization $agency): array
    {
        if ($agency && $this->hasActiveRentalLicense($agency)) {
            return $this->fromOrganization($agency);
        }

        return $this->fromConfig();
    }

    protected function hasActiveRentalLicense(Organization $org): bool
    {
        if (!(bool) $org->rental_license) return false;
        if (empty($org->rental_license_expires_at)) return false;

        try {
            $expiresAt = Carbon::parse($org->rental_license_expires_at)->endOfDay();
            return $expiresAt->greaterThan(now());
        } catch (\Throwable) {
            return false;
        }
    }

    protected function fromOrganization(Organization $org): array
    {
        $rawUser = (string) $org->getRawOriginal('codice_utente_cargos');
        $rawAgId = (string) $org->getRawOriginal('agenzia_id_cargos');
        $rawPuk  = (string) $org->getRawOriginal('cargos_puk');
        $rawPwd  = (string) $org->getRawOriginal('cargos_password');

        // ✅ nuovo campo
        $rawApi  = (string) $org->getRawOriginal('cargos_apikey');

        $missing = [];
        if (trim($rawUser) === '') $missing[] = 'codice_utente_cargos';
        if (trim($rawAgId) === '') $missing[] = 'agenzia_id_cargos';
        if (trim($rawPuk) === '')  $missing[] = 'cargos_puk';
        if (trim($rawPwd) === '')  $missing[] = 'cargos_password';
        if (trim($rawApi) === '')  $missing[] = 'cargos_apikey';

        if (!empty($missing)) {
            throw new RuntimeException(
                "Credenziali CARGOS mancanti per Organization #{$org->id}: compila " . implode(', ', $missing) . '.'
            );
        }

        try {
            $username  = (string) $org->codice_utente_cargos;
            $agencyId  = (string) $org->agenzia_id_cargos;
            $puk       = (string) $org->cargos_puk;
            $password  = (string) $org->cargos_password;
            $apikey    = (string) $org->cargos_apikey;
        } catch (\Throwable) {
            throw new RuntimeException(
                "Credenziali CARGOS non decifrabili per Organization #{$org->id}: verifica APP_KEY e cast 'encrypted'."
            );
        }

        if (
            trim($username) === '' || trim($agencyId) === '' || trim($puk) === '' ||
            trim($password) === '' || trim($apikey) === ''
        ) {
            throw new RuntimeException(
                "Credenziali CARGOS vuote dopo lettura per Organization #{$org->id}: verifica salvataggio/cast."
            );
        }

        return [
            'username'  => $username,
            'password'  => $password,
            'puk'       => $puk,
            'agency_id' => $agencyId,
            'apikey'    => $apikey,
            'source'    => 'organization',
        ];
    }

    protected function fromConfig(): array
    {
        $username = trim((string) config('cargos.admin.username'));
        $password = (string) config('cargos.admin.password');
        $puk      = (string) config('cargos.admin.puk');
        $agencyId = trim((string) config('cargos.admin.agency_id'));

        // ✅ fallback attuale da .env
        $apiKey   = trim((string) config('cargos.apikey'));

        $missing = [];
        if ($username === '') $missing[] = 'CARGOS_ADMIN_USERNAME';
        if (trim($password) === '') $missing[] = 'CARGOS_ADMIN_PASSWORD';
        if (trim($puk) === '') $missing[] = 'CARGOS_ADMIN_PUK';
        if ($agencyId === '') $missing[] = 'CARGOS_ADMIN_AGENCY_ID';
        if ($apiKey === '')   $missing[] = 'CARGOS_APIKEY';

        if (!empty($missing)) {
            throw new RuntimeException(
                'Credenziali CARGOS admin mancanti: configura ' . implode(', ', $missing) . ' in .env.'
            );
        }

        return [
            'username'  => $username,
            'password'  => $password,
            'puk'       => $puk,
            'agency_id' => $agencyId,
            'apikey'    => $apiKey,
            'source'    => 'config',
        ];
    }
}
