<?php

namespace App\Domain\Rentals\Guards;

use App\Models\Rental;

class CloseRentalGuard
{
    /**
     * $rules:
     *  - require_signed           bool
     *  - require_base_payment     bool
     *  - grace_minutes            int  (finestra di ricalcolo snapshot)
     */
    public function check(Rental $rental, array $rules = []): array
    {
        $rules = array_merge([
            'require_signed'       => false,
            'require_base_payment' => true,
            'grace_minutes'        => 0,
        ], $rules);

        // 1) Stato coerente
        if ($rental->status !== 'checked_in') {
            return $this->fail('not_checked_in', 'Il noleggio deve essere in stato checked_in.');
        }

        // 2) Checklist return presente
        if (!$rental->checklists()->where('type', 'return')->exists()) {
            return $this->fail('missing_return_checklist', 'Checklist di return mancante.');
        }

        // 3) Firma richiesta?
        if ($rules['require_signed'] === true) {
            $pickup = $rental->checklists()->where('type','pickup')->first();
            $signedOnRental    = $rental->getMedia('signatures')->isNotEmpty();
            $signedOnChecklist = $pickup ? $pickup->getMedia('signatures')->isNotEmpty() : false;
            if (!$signedOnRental || !$signedOnChecklist) {
                return $this->fail('missing_signatures', 'Contratto firmato assente per la chiusura.');
            }
        }

        // 4) Pagamento base richiesto?
        if ($rules['require_base_payment'] === true && !$rental->has_base_payment) {
            return $this->fail('base_payment_missing', 'Pagamento base non registrato.');
        }

        // 5) Km extra dovuti ⇒ deve esistere un pagamento distance_overage
        if ($rental->needs_distance_overage_payment && !$rental->has_distance_overage_payment) {
            return $this->fail('overage_unpaid', 'Devi registrare il pagamento dei km extra prima di chiudere.');
        }

        // 6) Snapshot già consolidato?
        if ($rental->closed_at) {
            $graceOk = $rules['grace_minutes'] > 0
                && now()->diffInMinutes($rental->closed_at) <= (int) $rules['grace_minutes'];

            if (!$graceOk) {
                return $this->fail('snapshot_locked', 'Il noleggio risulta già chiuso: snapshot commissione bloccato.');
            }
        }

        return ['ok' => true, 'code' => null, 'message' => null];
    }

    private function fail(string $code, string $message): array
    {
        return ['ok' => false, 'code' => $code, 'message' => $message];
    }
}
