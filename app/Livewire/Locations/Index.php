<?php

namespace App\Livewire\Locations;

use App\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Index extends Component
{
    use WithPagination, AuthorizesRequests;

    public ?string $q = null;
    public int $perPage = 15;

    public function mount(): void
    {
        $this->authorize('viewAny', Location::class);
    }

    public function updatingQ(): void { $this->resetPage(); }

    public function render()
    {
        $user  = Auth::user();
        $orgId = (int) $user->organization_id;

        $term = $this->q ? strtolower(trim($this->q)) : null;
        $term = $term ? str_replace('*', '%', preg_replace('/\s+/', '%', $term)) : null;
        $like = $term ? "%{$term}%" : null;

        $query = Location::query()
            ->when($user->organization && method_exists($user->organization, 'isRenter') && $user->organization->isRenter(),
                fn (Builder $q) => $q->where('organization_id', $orgId)
            )
            ->when($term, function (Builder $q) use ($like) {
                $q->where(function (Builder $w) use ($like) {
                    $w->whereRaw('LOWER(name) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(city) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(postal_code) LIKE ?', [$like]);
                });
            })
            ->orderBy('name');

        return view('livewire.locations.index', [
            'items' => $query->paginate($this->perPage),
        ]);
    }
}
