<?php

namespace App\Services\Cargos;

use App\Models\{Rental, Organization, Location, Customer, Vehicle, User};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Resolver del "contesto" per una singola comunicazione CARGOS.
 *
 * Responsabilità:
 * - Caricare il Rental con tutte le relazioni necessarie: niente N+1.
 * - Applicare i fallback di business (es. agenzia = renter con licenza o admin owner del veicolo).
 * - Pre-costruire alcuni campi derivati (es. indirizzi concatenati, contratto_id univoco).
 *
 * NOTA:
 * - Qui NON formattiamo ancora secondo il tracciato record (larghezza fissa / charset CARGOS).
 *   Quello sarà lo step successivo (builder/formatter).
 */
class CargosRentalContextResolver
{
    /**
     * Recupera e prepara il contesto completo per una comunicazione CARGOS.
     *
     * @param  int       $rentalId  ID del noleggio.
     * @param  User|null $operator  Operatore (se null usa l'utente autenticato).
     * @return array<string,mixed>  Contesto strutturato per il builder CARGOS.
     */
    public function resolveOrFail(int $rentalId, ?User $operator = null): array
    {
        /**
         * Caricamento completo (eager loading):
         * - organization: organizzazione renter del noleggio
         * - vehicle.adminOrganization: organizzazione admin proprietaria del veicolo (fallback agenzia)
         * - customer: contraente/conducente principale
         * - secondDriver: seconda guida (opzionale)
         * - pickupLocation/returnLocation: luoghi consegna/riconsegna
         */
        $rental = Rental::query()
            ->with([
                'organization',
                'vehicle.adminOrganization',
                'customer',
                'secondDriver',
                'pickupLocation',
                'returnLocation',
            ])
            ->findOrFail($rentalId);

        /** Operatore: preferisci parametro, fallback su Auth. */
        $operator = $operator ?: Auth::user();

        if (!$operator) {
            throw new RuntimeException('Operatore non disponibile: utente non autenticato.');
        }

        // ---- Verifiche minime di presenza (validazione completa nel prossimo step) ----

        if (!$rental->vehicle) {
            throw new RuntimeException('Veicolo mancante sul noleggio (vehicle_id nullo o relazione assente).');
        }

        if (!$rental->customer) {
            throw new RuntimeException('Cliente mancante sul noleggio (customer_id nullo o relazione assente).');
        }

        if (!$rental->pickupLocation) {
            throw new RuntimeException('Pickup location mancante sul noleggio (pickup_location_id nullo o relazione assente).');
        }

        if (!$rental->returnLocation) {
            throw new RuntimeException('Return location mancante sul noleggio (return_location_id nullo o relazione assente).');
        }

        // ---- Fallback agenzia: renter con licenza o admin proprietario del veicolo ----

        $agency = $this->resolveAgencyOrganization($rental);

        // ---- Campi derivati (NON ancora normalizzati per CARGOS) ----

        /**
         * Se manca actual_pickup_at (draft), NON possiamo creare un contratto_id univoco.
         * In quel caso lasciamo vuoto: sarà il builder a produrre l'errore “umano”.
         */
        $contractIdUnique = '';

        if ($rental->number_id && $rental->actual_pickup_at) {
            $contractIdUnique = $this->buildContractIdUnique(
                $rental->number_id,
                $rental->actual_pickup_at->format('YmdHi')
            );
        }

        $checkoutAddress = $this->buildAddressLineFromLocation($rental->pickupLocation);
        $checkinAddress  = $this->buildAddressLineFromLocation($rental->returnLocation);

        $agencyAddress   = $this->buildAddressLineFromOrganization($agency);

        $customerResidenceAddress = $this->buildAddressLineFromCustomer($rental->customer);

        // Operatore ID “umano” e univoco (normalizzazione charset CARGOS nel prossimo step)
        $operatorId = (string) $operator->id . '-' . (string) $operator->name;

        return [
            // Modelli già caricati (utili al builder)
            'rental'          => $rental,
            'vehicle'         => $rental->vehicle,
            'customer'        => $rental->customer,
            'second_driver'   => $rental->secondDriver,  // può essere null
            'pickup_location' => $rental->pickupLocation,
            'return_location' => $rental->returnLocation,
            'agency'          => $agency,
            'operator'        => $operator,

            // Derivati “comodi” per costruire il payload
            'derived' => [
                'contract_id_unique'            => $contractIdUnique,
                'operator_id'                   => $operatorId,

                'checkout_address'              => $checkoutAddress,
                'checkin_address'               => $checkinAddress,

                'agency_address'                => $agencyAddress,
                'customer_residence_address'    => $customerResidenceAddress,
            ],
        ];
    }

    /**
     * Risolve l'organizzazione "agenzia" da inviare a CARGOS:
     * - se l'organizzazione del rental ha rental_license = true => usa quella
     * - altrimenti fallback su organizzazione admin proprietaria del veicolo
     *
     * @param  Rental $rental
     * @return Organization
     */
    protected function resolveAgencyOrganization(Rental $rental): Organization
    {
        $renterOrg = $rental->organization;
        if ($renterOrg && (bool) $renterOrg->rental_license === true) {
            return $renterOrg;
        }

        $adminOrg = $rental->vehicle?->adminOrganization;
        if ($adminOrg) {
            return $adminOrg;
        }

        throw new RuntimeException('Impossibile risolvere AGENZIA: manca sia organization (renter con licenza) sia adminOrganization del veicolo.');
    }

    /**
     * Costruisce il contratto_id univoco:
     * - number_id + "-" + YYYYMMDDHHii
     *
     * NOTA:
     * - Usiamo "-" e non "/" (la normalizzazione completa charset CARGOS la faremo nel builder).
     *
     * @param  int|null $numberId
     * @param  string   $suffixYmdHi
     * @return string
     */
    protected function buildContractIdUnique(?int $numberId, string $suffixYmdHi): string
    {
        $base = (string) ($numberId ?? 0);

        return $base . '-' . $suffixYmdHi;
    }

    /**
     * Costruisce una riga indirizzo da Location.
     * Se city/province/postal_code sono null, resta address_line (come da tuo scenario).
     *
     * @param  Location $location
     * @return string
     */
    protected function buildAddressLineFromLocation(Location $location): string
    {
        return $this->implodeAddressParts([
            $location->address_line,
            $location->postal_code,
            $location->city,
            $location->province,
        ]);
    }

    /**
     * Costruisce una riga indirizzo da Organization.
     *
     * @param  Organization $org
     * @return string
     */
    protected function buildAddressLineFromOrganization(Organization $org): string
    {
        return $this->implodeAddressParts([
            $org->address_line,
            $org->postal_code,
            $org->city,
            $org->province,
        ]);
    }

    /**
     * Costruisce una riga indirizzo da Customer (residenza).
     * Come da tua nota: address_line è la colonna sicuramente valorizzata.
     *
     * @param  Customer $customer
     * @return string
     */
    protected function buildAddressLineFromCustomer(Customer $customer): string
    {
        return $this->implodeAddressParts([
            $customer->address_line,
            $customer->postal_code,
            $customer->city,
            $customer->province,
        ]);
    }

    /**
     * Helper: unisce i pezzi indirizzo ignorando i null/empty.
     *
     * @param  array<int, string|null> $parts
     * @return string
     */
    protected function implodeAddressParts(array $parts): string
    {
        $clean = [];

        foreach ($parts as $p) {
            $p = is_string($p) ? trim($p) : null;
            if ($p !== null && $p !== '') {
                $clean[] = $p;
            }
        }

        return implode(', ', $clean);
    }
}
