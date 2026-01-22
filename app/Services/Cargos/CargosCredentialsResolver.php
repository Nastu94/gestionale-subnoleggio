<?php

namespace App\Services\Cargos;

use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use RuntimeException;

/**
 * Risolve le credenziali CARGOS (PUK + Password) in base alla licenza.
 *
 * Regola:
 * - Organizzazione con licenza attiva => usa organizations.cargos_puk + organizations.cargos_password (decrypt)
 * - Altrimenti => usa config('cargos.admin.*') (env)
 *
 * Nota sicurezza:
 * - NON loggare mai i valori in chiaro.
 */
class CargosCredentialsResolver
{
    /**
     * Risolve le credenziali CARGOS per una data "agency" (Organization).
     *
     * @param  Organization $agency
     * @return array{puk:string,password:string,source:string}
     */
    public function resolveForAgency(Organization $agency): array
    {
        // Licenza "attiva" = flag true e non scaduta (se c'è una data scadenza)
        if ($this->hasActiveRentalLicense($agency)) {
            return $this->fromOrganization($agency);
        }

        return $this->fromConfig();
    }

    /**
     * Verifica se la licenza noleggio è attiva (flag + non scaduta).
     *
     * @param  Organization $org
     * @return bool
     */
    protected function hasActiveRentalLicense(Organization $org): bool
    {
        if (!(bool) $org->rental_license) {
            return false;
        }

        // Se non c'è una scadenza, consideriamola attiva.
        if (empty($org->rental_license_expires_at)) {
            return true;
        }

        try {
            $expiresAt = Carbon::parse($org->rental_license_expires_at)->endOfDay();
            return $expiresAt->greaterThanOrEqualTo(now());
        } catch (\Throwable) {
            // Se la data è corrotta, meglio NON fidarsi => fallback su config admin
            return false;
        }
    }

    /**
     * Credenziali da Organization.
     *
     * Nota:
     * - Se in Organization hai i cast `encrypted`, leggere $org->cargos_* restituisce già il valore in chiaro.
     * - NON usare decrypt() qui, altrimenti fai double-decrypt e ottieni "non decifrabile".
     *
     * @param  Organization $org
     * @return array{puk:string,password:string,source:string}
     */
    protected function fromOrganization(Organization $org): array
    {
        /**
         * Verifica presenza valori salvati (senza forzare decrypt).
         * Usiamo getRawOriginal per controllare che in DB ci sia qualcosa.
         */
        $rawPuk = (string) $org->getRawOriginal('cargos_puk');
        $rawPwd = (string) $org->getRawOriginal('cargos_password');

        if (trim($rawPuk) === '' || trim($rawPwd) === '') {
            throw new RuntimeException(
                "Credenziali CARGOS mancanti per Organization #{$org->id}: compila cargos_puk e cargos_password."
            );
        }

        /**
         * Qui leggiamo gli attributi "castati".
         * Se i cast encrypted sono presenti, Laravel decripta automaticamente.
         */
        try {
            $puk = (string) $org->cargos_puk;
            $password = (string) $org->cargos_password;
        } catch (\Throwable) {
            throw new RuntimeException(
                "Credenziali CARGOS non decifrabili per Organization #{$org->id}: verifica APP_KEY e che i cast 'encrypted' siano corretti."
            );
        }

        if (trim($puk) === '' || trim($password) === '') {
            throw new RuntimeException(
                "Credenziali CARGOS vuote dopo lettura per Organization #{$org->id}: verifica salvataggio/cast."
            );
        }

        return [
            'puk'      => $puk,
            'password' => $password,
            'source'   => 'organization',
        ];
    }

    /**
     * Credenziali admin da config.
     *
     * @return array{puk:string,password:string,source:string}
     */
    protected function fromConfig(): array
    {
        $puk = (string) config('cargos.admin.puk');
        $password = (string) config('cargos.admin.password');

        if ($puk === '' || $password === '') {
            throw new RuntimeException(
                'Credenziali CARGOS admin mancanti: configura CARGOS_ADMIN_PUK e CARGOS_ADMIN_PASSWORD in .env.'
            );
        }

        return [
            'puk'      => $puk,
            'password' => $password,
            'source'   => 'config',
        ];
    }
}
