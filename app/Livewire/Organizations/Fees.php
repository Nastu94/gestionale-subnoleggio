<?php

namespace App\Livewire\Organizations;

use App\Models\Organization;
use App\Models\OrganizationFee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pannello laterale per la gestione delle fee admin per Organization (renter).
 *
 * - Solo admin: blocco via check ruolo.
 * - Mostra fee attiva e storico; consente create/update con prevenzione sovrapposizioni.
 * - Nessuna modifica a variabili esistenti del dominio.
 */
class Fees extends Component
{
    /** Visibilità pannello */
    public bool $open = false;

    /** Id organization corrente */
    public ?int $organizationId = null;

    /** Form stato locale */
    public ?int $editingId = null;
    public array $form = [
        'percent'        => '',
        'effective_from' => '',
        'effective_to'   => null,
        'notes'          => '',
    ];

    /** Apertura pannello via evento dalla tabella */
    #[On('org-fees:open')]
    public function handleOpen(...$payload): void
    {
        // $payload può essere:
        // - [123]
        // - [['organizationId' => 123]]
        // - []
        $arg = $payload[0] ?? null;

        $orgId = is_array($arg)
            ? ($arg['organizationId'] ?? $arg['id'] ?? null)
            : (is_numeric($arg) ? (int) $arg : null);

        if (!$orgId) {
            $this->dispatch('toast', type:'error', message:'Renter non valido.');
            return;
        }

        $this->authorizeAdmin();

        $this->organizationId = (int) $orgId;
        $this->resetForm();
        $this->open = true;
    }

    /** Chiudi pannello */
    public function close(): void
    {
        $this->open = false;
        $this->organizationId = null;
        $this->editingId = null;
        $this->resetForm();
    }

    /** Prepara form vuoto */
    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'percent'        => '',
            'effective_from' => '',
            'effective_to'   => null,
            'notes'          => '',
        ];
        $this->resetErrorBag();
        $this->resetValidation();
    }

    /** Carica una fee in edit */
    public function edit(int $feeId): void
    {
        $this->authorizeAdmin();

        /** @var OrganizationFee|null $fee */
        $fee = OrganizationFee::query()
            ->whereKey($feeId)
            ->where('organization_id', $this->organizationId)
            ->first();

        if (!$fee) return;

        $this->editingId = $fee->id;
        $this->form['percent']        = (string) $fee->percent;
        $this->form['effective_from'] = optional($fee->effective_from)->toDateString();
        $this->form['effective_to']   = optional($fee->effective_to)->toDateString();
        $this->form['notes']          = (string) ($fee->notes ?? '');
        $this->resetErrorBag();
    }

    /** Salvataggio create/update con prevenzione sovrapposizioni */
    public function save(): void
    {
        $this->authorizeAdmin();

        $data = $this->validateData();

        // Controllo sovrapposizioni (stessa organization)
        $from = CarbonImmutable::parse($data['effective_from']);
        $to   = $data['effective_to'] ? CarbonImmutable::parse($data['effective_to']) : null;
        $toStr = $to ? $to->toDateString() : '9999-12-31';

        $overlap = OrganizationFee::query()
            ->where('organization_id', $this->organizationId)
            ->when($this->editingId, fn($q) => $q->whereKeyNot($this->editingId))
            ->whereDate('effective_from', '<=', $toStr)
            ->where(function ($w) use ($from) {
                $w->whereNull('effective_to')
                  ->orWhereDate('effective_to', '>=', $from->toDateString());
            })
            ->exists();

        if ($overlap) {
            $this->addError('form.effective_from', 'Esiste già una fee attiva nel periodo indicato.');
            return;
        }

        DB::transaction(function () use ($data, $from) {
            if ($this->editingId) {
                // UPDATE
                OrganizationFee::whereKey($this->editingId)
                    ->where('organization_id', $this->organizationId)
                    ->update([
                        'percent'        => $data['percent'],
                        'effective_from' => $data['effective_from'],
                        'effective_to'   => $data['effective_to'],
                        'notes'          => $data['notes'],
                    ]);
            } else {
                // CREATE (chiudo eventuale fee “aperta” precedente, se presente e antecedente)
                $openPrev = OrganizationFee::query()
                    ->where('organization_id', $this->organizationId)
                    ->whereNull('effective_to')
                    ->whereDate('effective_from', '<=', $from->toDateString())
                    ->orderByDesc('effective_from')
                    ->first();

                if ($openPrev && $openPrev->effective_from->lt($from)) {
                    $openPrev->effective_to = $from->subDay();
                    $openPrev->save();
                }

                OrganizationFee::create([
                    'organization_id' => $this->organizationId,
                    'percent'         => $data['percent'],
                    'effective_from'  => $data['effective_from'],
                    'effective_to'    => $data['effective_to'],
                    'notes'           => $data['notes'],
                    'created_by'      => Auth::id(),
                ]);
            }
        });

        // UI: reset + ricarico lista; lascio pannello aperto
        $this->resetForm();
        $this->dispatch('toast', type:'success', message:'Fee salvata.');
    }

    /** Validazione base */
    private function validateData(): array
    {
        $this->validate([
            'organizationId'        => ['required','integer','exists:organizations,id'],
            'form.percent'          => ['required','numeric','between:0,100'],
            'form.effective_from'   => ['required','date'],
            'form.effective_to'     => ['nullable','date','after_or_equal:form.effective_from'],
            'form.notes'            => ['nullable','string','max:2000'],
        ]);

        return [
            'percent'        => (float) $this->form['percent'],
            'effective_from' => $this->form['effective_from'],
            'effective_to'   => $this->form['effective_to'] ?: null,
            'notes'          => $this->form['notes'] ?: null,
        ];
    }

    /** Solo admin */
    private function authorizeAdmin(): void
    {
        if (!Auth::user()?->hasRole('admin')) {
            abort(403);
        }
    }

    /** Fee attiva “oggi” (solo lettura) */
    public function getActiveFeeProperty(): ?OrganizationFee
    {
        if (!$this->organizationId) return null;

        return OrganizationFee::query()
            ->where('organization_id', $this->organizationId)
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->where(function ($w) {
                $w->whereNull('effective_to')
                  ->orWhereDate('effective_to', '>=', now()->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    /** Storico fee (desc) */
    public function getFeesProperty()
    {
        if (!$this->organizationId) return collect();

        return OrganizationFee::query()
            ->where('organization_id', $this->organizationId)
            ->orderByDesc('effective_from')
            ->get();
    }

    public function render()
    {
        /** @var Organization|null $org */
        $org = $this->organizationId
            ? Organization::find($this->organizationId)
            : null;

        return view('livewire.organizations.fees', [
            'organization' => $org,
            'activeFee'    => $this->activeFee,
            'fees'         => $this->fees,
        ]);
    }
}
