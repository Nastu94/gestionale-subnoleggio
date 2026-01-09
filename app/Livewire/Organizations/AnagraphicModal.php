<?php

namespace App\Livewire\Organizations;

use App\Models\Organization;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Modale Livewire: Anagrafica + Licenza per Organization di tipo "renter".
 *
 * - Si apre ascoltando l'evento: open-org-anagraphic
 * - Aggiorna solo campi anagrafici/licenza (nessun dato sensibile tipo cargos).
 * - Non consente modifiche su organization archiviata (soft delete).
 */
class AnagraphicModal extends Component
{
    /**
     * Stato di apertura del modale.
     */
    public bool $open = false;

    /**
     * Organization selezionata.
     */
    public ?int $organizationId = null;

    /**
     * Stato form (bind sui campi).
     *
     * NB: usiamo un array per mantenere il codice compatto e coerente
     * con i pattern Livewire già presenti nel progetto.
     *
     * @var array<string, mixed>
     */
    public array $state = [
        // Anagrafica
        'legal_name'   => null,
        'vat'          => null,
        'address_line' => null,
        'city'         => null,
        'province'     => null,
        'postal_code'  => null,
        'country_code' => null,
        'phone'        => null,
        'email'        => null,

        // Licenza
        'rental_license'            => false,
        'rental_license_number'     => null,
        'rental_license_expires_at' => null,
    ];

    /**
     * Listener eventi Livewire/Browser.
     *
     * @var array<string, string>
     */
    protected $listeners = [
        'open-org-anagraphic' => 'openModal',
    ];

        /**
     * Messaggi di validazione personalizzati.
     *
     * @var array<string, string>
     */
    protected array $messages = [
        // Anagrafica
        'state.legal_name.max' => 'La ragione sociale può contenere al massimo :max caratteri.',
        'state.vat.max'        => 'La Partita IVA può contenere al massimo :max caratteri.',
        'state.vat.unique'     => 'Esiste già un’organizzazione con questa Partita IVA.',
        'state.email.email'    => 'Inserisci un indirizzo email valido.',
        'state.email.max'      => 'L’email può contenere al massimo :max caratteri.',
        'state.phone.max'      => 'Il telefono può contenere al massimo :max caratteri.',

        'state.address_line.max' => 'L’indirizzo può contenere al massimo :max caratteri.',
        'state.city.max'         => 'La città può contenere al massimo :max caratteri.',
        'state.province.max'     => 'La provincia può contenere al massimo :max caratteri.',
        'state.postal_code.max'  => 'Il CAP può contenere al massimo :max caratteri.',
        'state.country_code.max' => 'Il codice paese può contenere al massimo :max caratteri.',

        // Licenza
        'state.rental_license_number.required_if' => 'Il numero licenza è obbligatorio se la licenza è presente.',
        'state.rental_license_number.max'         => 'Il numero licenza può contenere al massimo :max caratteri.',
        'state.rental_license_expires_at.required_if' => 'La scadenza licenza è obbligatoria se la licenza è presente.',
        'state.rental_license_expires_at.date'         => 'La scadenza licenza deve essere una data valida.',
        'state.rental_license_expires_at.after_or_equal' => 'La scadenza licenza non può essere nel passato.',
    ];

    /**
     * Nomi “umani” degli attributi (opzionale ma utile).
     *
     * @var array<string, string>
     */
    protected array $validationAttributes = [
        'state.legal_name' => 'ragione sociale',
        'state.vat' => 'Partita IVA',
        'state.address_line' => 'indirizzo',
        'state.city' => 'città',
        'state.province' => 'provincia',
        'state.postal_code' => 'CAP',
        'state.country_code' => 'codice paese',
        'state.phone' => 'telefono',
        'state.email' => 'email',
        'state.rental_license' => 'licenza di noleggio',
        'state.rental_license_number' => 'numero licenza',
        'state.rental_license_expires_at' => 'scadenza licenza',
    ];

    /**
     * Gate di accesso: la pagina è admin-only, ma teniamo comunque il guard.
     */
    public function mount(): void
    {
        if (! Gate::allows('manage.renters')) {
            abort(403);
        }
    }

    /**
     * Regole di validazione.
     * - rental_license_number e rental_license_expires_at diventano obbligatori se rental_license=true
     * - expires_at non può essere nel passato (riduce incoerenze future)
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'state.legal_name'   => ['nullable', 'string', 'max:191'],
            'state.vat'          => ['nullable', 'string', 'max:64', Rule::unique('organizations', 'vat')->ignore($this->organizationId),],
            'state.address_line' => ['nullable', 'string', 'max:191'],
            'state.city'         => ['nullable', 'string', 'max:191'],
            'state.province'     => ['nullable', 'string', 'max:191'],
            'state.postal_code'  => ['nullable', 'string', 'max:32'],
            'state.country_code' => ['nullable', 'string', 'max:8'],
            'state.phone'        => ['nullable', 'string', 'max:64'],
            'state.email'        => ['nullable', 'email', 'max:191'],

            'state.rental_license' => ['boolean'],

            'state.rental_license_number' => [
                'nullable',
                'string',
                'max:64',
                'required_if:state.rental_license,true',
            ],

            'state.rental_license_expires_at' => [
                'nullable',
                'date',
                'required_if:state.rental_license,true',
                'after_or_equal:today',
            ],
        ];
    }

    /**
     * Apre il modale ricevendo i dati non sensibili dal Table component.
     *
     * @param  array<string, mixed>  $org
     */
    public function openModal(array $org): void
    {
        $this->resetValidation();

        $this->organizationId = (int) ($org['id'] ?? 0);

        // Popoliamo lo state con quanto arriva dall'evento
        $this->state['legal_name']   = $org['legal_name']   ?? null;
        $this->state['vat']          = $org['vat']          ?? null;
        $this->state['address_line'] = $org['address_line'] ?? null;
        $this->state['city']         = $org['city']         ?? null;
        $this->state['province']     = $org['province']     ?? null;
        $this->state['postal_code']  = $org['postal_code']  ?? null;
        $this->state['country_code'] = $org['country_code'] ?? null;
        $this->state['phone']        = $org['phone']        ?? null;
        $this->state['email']        = $org['email']        ?? null;

        $this->state['rental_license']            = (bool) ($org['rental_license'] ?? false);
        $this->state['rental_license_number']     = $org['rental_license_number'] ?? null;
        $this->state['rental_license_expires_at'] = $org['rental_license_expires_at'] ?? null;

        $this->open = true;
    }

    /**
     * Chiude il modale e ripulisce selezione.
     */
    public function closeModal(): void
    {
        $this->open = false;
        $this->organizationId = null;
        $this->resetValidation();
    }

    /**
     * Salva i dati anagrafici/licenza sul renter.
     * - Validazione con messaggi personalizzati (form errors)
     * - Toast success su salvataggio
     * - Toast error per problemi di salvataggio non legati alla validazione
     */
    public function save(): void
    {
        $this->validate();

        if (empty($this->organizationId)) {
            return;
        }

        try {
            /**
             * Carichiamo SOLO renter attivi:
             * - se è archiviato (soft delete) non permettiamo modifica
             */
            $org = Organization::query()
                ->where('type', 'renter')
                ->whereKey($this->organizationId)
                ->firstOrFail();

            $org->update([
                'legal_name' => $this->state['legal_name'],
                'vat'        => $this->state['vat'],

                'address_line' => $this->state['address_line'],
                'city'         => $this->state['city'],
                'province'     => $this->state['province'],
                'postal_code'  => $this->state['postal_code'],
                'country_code' => $this->state['country_code'],
                'phone'        => $this->state['phone'],
                'email'        => $this->state['email'],

                'rental_license'            => (bool) $this->state['rental_license'],
                'rental_license_number'     => $this->state['rental_license_number'],
                'rental_license_expires_at' => $this->state['rental_license_expires_at'],
            ]);

            // Chiudiamo il modale
            $this->closeModal();

            // Refresh soft per componenti collegati
            $this->dispatch('organizations:updated');

            // Toast successo
            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Anagrafica renter salvata correttamente.',
                'duration' => 3000,
            ]);

        } catch (QueryException $e) {
            /**
             * Caso tipico: UNIQUE constraint su Partita IVA.
             * DB driver-specific:
             * - MySQL: SQLSTATE 23000, error code 1062 (Duplicate entry)
             */
            $sqlState = $e->errorInfo[0] ?? null;
            $driverCode = $e->errorInfo[1] ?? null;

            if ($sqlState === '23000' && (int) $driverCode === 1062) {
                // Mostriamo un errore “amichevole” via toast (oltre alla validazione client-side)
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'Partita IVA già presente: verifica il valore inserito.',
                    'duration' => 4500,
                ]);

                return;
            }

            Log::error('AnagraphicModal save QueryException', [
                'organization_id' => $this->organizationId,
                'message' => $e->getMessage(),
            ]);

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Errore durante il salvataggio. Riprova.',
                'duration' => 4500,
            ]);

        } catch (\Throwable $e) {
            Log::error('AnagraphicModal save Throwable', [
                'organization_id' => $this->organizationId,
                'message' => $e->getMessage(),
            ]);

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Errore durante il salvataggio. Riprova.',
                'duration' => 4500,
            ]);
        }
    }


    public function render()
    {
        return view('livewire.organizations.anagraphic-modal');
    }
}
