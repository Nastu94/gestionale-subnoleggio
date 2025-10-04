<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Index extends Component
{
    use WithPagination, AuthorizesRequests;

    /** @var string|null Ricerca libera */
    public ?string $q = null;

    /** @var string|null Filtra per stato "verificato/incompleto/bloccato" se/quanto lo userai più avanti */
    public ?string $status = null;

    /** @var int Paginazione */
    public int $perPage = 15;

    public function mount(): void
    {
        // Autorizzazione ad elencare
        $this->authorize('viewAny', Customer::class);
    }

    public function updatingQ(): void    { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    public function render()
    {
        $user  = Auth::user();
        $orgId = (int) $user->organization_id;

        // Ricerca "tollerante": * → %, spazi → %
        $term = $this->q ? strtolower(trim($this->q)) : null;
        if ($term) {
            $term = str_replace('*', '%', $term);
            $term = preg_replace('/\s+/', '%', $term);
        }
        $like = $term ? "%{$term}%" : null;

        $query = Customer::query()
            // Tenant-scoped: i renter vedono solo i propri; gli admin qui possono restare scopiati
            ->when($user->organization && method_exists($user->organization, 'isRenter') && $user->organization->isRenter(),
                fn (Builder $q) => $q->where('organization_id', $orgId)
            )
            // Ricerca multi-campo
            ->when($term, function (Builder $q) use ($like) {
                $q->where(function (Builder $w) use ($like) {
                    $w->whereRaw('LOWER(name) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(doc_id_number) LIKE ?', [$like]);
                });
            })
            ->orderBy('name');

        return view('livewire.customers.index', [
            'items' => $query->paginate($this->perPage),
        ]);
    }
}
