<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDamage;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\DB;

class VehicleDamagePolicy
{
    use HandlesAuthorization;

    /**
     * Admin: short-circuit su tutte le azioni.
     * (Se preferisci richiedere anche i permessi espliciti all’admin, rimuovi questo before)
     */
    public function before(User $user, string $ability)
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }
        return null;
    }

    /** Access helper: il renter può agire solo su veicoli assegnati ora alla sua org. */
    private function canAccessVehicle(User $user, int $vehicleId): bool
    {
        // Utente senza org: niente accesso.
        $orgId = $user->organization_id ?? null;
        if (!$orgId) {
            return false;
        }

        $now = now();

        return DB::table('vehicle_assignments')
            ->where('vehicle_id', $vehicleId)
            ->where('renter_org_id', $orgId)
            ->where('status', 'active')
            ->where('start_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>', $now);
            })
            ->exists();
    }

    /* ---------------------------- Abilitazioni ---------------------------- */

    public function viewAny(User $user): bool
    {
        return $user->can('vehicle_damages.viewAny');
    }

    public function view(User $user, VehicleDamage $damage): bool
    {
        return $user->can('vehicle_damages.view')
            && $this->canAccessVehicle($user, $damage->vehicle_id);
    }

    /**
     * Create accetta anche il Vehicle come soggetto: authorize('create', [VehicleDamage::class, $vehicle])
     */
    public function create(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicle_damages.create')
            && $this->canAccessVehicle($user, $vehicle->id);
    }

    /**
     * Update: vietato per i danni originati da rental (campi area/severity/description sono “derivati”).
     * Se vuoi consentire solo l’aggiornamento delle note, spostalo nella logica del componente.
     */
    public function update(User $user, VehicleDamage $damage): bool
    {
        if ($damage->source === 'rental') {
            return false;
        }

        return $user->can('vehicle_damages.update')
            && $this->canAccessVehicle($user, $damage->vehicle_id)
            && $damage->is_open === true;
    }

    /** Chiusura danno (imposta fixed_at, fixed_by_user_id, repair_cost, append note) */
    public function close(User $user, VehicleDamage $damage): bool
    {
        return $user->can('vehicle_damages.close')
            && $this->canAccessVehicle($user, $damage->vehicle_id)
            && $damage->is_open === true;
    }

    /** Riapertura danno chiuso */
    public function reopen(User $user, VehicleDamage $damage): bool
    {
        return $user->can('vehicle_damages.reopen')
            && $this->canAccessVehicle($user, $damage->vehicle_id)
            && $damage->is_open === false;
    }

    public function delete(User $user, VehicleDamage $damage): bool
    {
        return $user->can('vehicle_damages.delete')
            && $this->canAccessVehicle($user, $damage->vehicle_id);
    }

    public function restore(User $user, VehicleDamage $damage): bool
    {
        return $this->delete($user, $damage);
    }

    public function forceDelete(User $user, VehicleDamage $damage): bool
    {
        return $this->delete($user, $damage);
    }
}
