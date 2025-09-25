<?php

namespace App\Policies;

use App\Models\{User, VehicleDocument};
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\DB;

class VehicleDocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('vehicle_documents.viewAny');
    }

    public function view(User $user, VehicleDocument $doc): bool
    {
        if ($user->organization->isRenter()) {
            // Deve avere l'assegnamento attivo sul veicolo del documento
            return DB::table('vehicle_assignments as va')
                ->where('va.vehicle_id', $doc->vehicle_id)
                ->where('va.renter_org_id', $user->organization_id)
                ->where('va.status', 'active')
                ->where('va.start_at', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('va.end_at')->orWhere('va.end_at', '>', now());
                })
                ->exists();
        }
        return $user->can('vehicle_documents.view');
    }

    public function create(User $user): bool
    {
        return $user->can('vehicle_documents.manage');
    }
    public function update(User $user, VehicleDocument $doc): bool
    {
        return $user->can('vehicle_documents.manage');
    }
    public function delete(User $user, VehicleDocument $doc): bool
    {
        return $user->can('vehicle_documents.manage');
    }
}
