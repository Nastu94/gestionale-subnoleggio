<?php

namespace App\Livewire\Reports;

use App\Models\Organization;
use App\Models\ReportPreset;
use App\Models\Vehicle;
use App\Models\Rental;
use App\Services\Reports\ReportRunner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Componente Livewire per la creazione di un preset report.
 *
 * Responsabilità:
 * - raccogliere i dati del preset;
 * - validare i campi in base al report_type selezionato;
 * - salvare il preset senza includere filtri runtime come le date;
 * - supportare ricerca testuale per renter e veicolo;
 * - offrire valori uniformi per metodo di pagamento e tipo voce.
 */
class CreateReportPreset extends Component
{
    /**
     * Nome del preset.
     */
    public string $name = '';

    /**
     * Descrizione opzionale del preset.
     */
    public string $description = '';

    /**
     * Tipo report selezionato.
     */
    public string $report_type = '';

    /**
     * Tipo grafico preferito.
     */
    public string $chart_type = 'table';

    /**
     * Metriche selezionate.
     *
     * @var array<int, string>
     */
    public array $metrics = [];

    /**
     * Dimensioni selezionate.
     *
     * @var array<int, string>
     */
    public array $dimensions = [];

    /**
     * Filtri strutturali del preset.
     *
     * @var array<string, mixed>
     */
    public array $filters = [
        'organization_id' => null,
        'vehicle_id' => null,
        'rental_id' => null,
        'payment_method' => null,
        'kind' => null,
        'is_commissionable' => null,
    ];

    /**
     * Testo di ricerca renter.
     */
    public string $organizationSearch = '';

    /**
     * Risultati ricerca renter.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $organizationOptions = [];

    /**
     * Nome renter selezionato, usato solo per la UI.
     */
    public ?string $selectedOrganizationLabel = null;

    /**
     * Testo di ricerca veicolo.
     */
    public string $vehicleSearch = '';

    /**
     * Risultati ricerca veicolo.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $vehicleOptions = [];

    /**
     * Etichetta veicolo selezionato, usata solo per la UI.
     */
    public ?string $selectedVehicleLabel = null;

    /**
     * Testo di ricerca noleggio.
     */
    public string $rentalSearch = '';

    /**
     * Risultati ricerca noleggio.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $rentalOptions = [];

    /**
     * Etichetta noleggio selezionato, usata solo per la UI.
     */
    public ?string $selectedRentalLabel = null;

    /**
     * Messaggio di conferma dopo il salvataggio.
     */
    public ?string $successMessage = null;

    /**
     * Inizializzazione componente.
     */
    public function mount(): void
    {
        $this->organizationOptions = [];
        $this->vehicleOptions = [];
    }

    /**
     * Restituisce le definizioni del motore report.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return app(ReportRunner::class)->definitions();
    }

    /**
     * Opzioni report type disponibili.
     *
     * @return array<int, string>
     */
    public function reportTypeOptions(): array
    {
        return array_keys($this->definitions());
    }

    /**
     * Metriche disponibili per il report selezionato.
     *
     * @return array<int, string>
     */
    public function availableMetrics(): array
    {
        if ($this->report_type === '' || ! array_key_exists($this->report_type, $this->definitions())) {
            return [];
        }

        return $this->definitions()[$this->report_type]['allowed_metrics'] ?? [];
    }

    /**
     * Dimensioni disponibili per il report selezionato.
     *
     * @return array<int, string>
     */
    public function availableDimensions(): array
    {
        if ($this->report_type === '' || ! array_key_exists($this->report_type, $this->definitions())) {
            return [];
        }

        return $this->definitions()[$this->report_type]['allowed_dimensions'] ?? [];
    }

    /**
     * Filtri disponibili per il report selezionato.
     *
     * Nota:
     * date_from e date_to vengono esclusi perché runtime.
     *
     * @return array<int, string>
     */
    public function availableFilters(): array
    {
        if ($this->report_type === '' || ! array_key_exists($this->report_type, $this->definitions())) {
            return [];
        }

        $filters = $this->definitions()[$this->report_type]['allowed_filters'] ?? [];

        return array_values(array_diff($filters, ['date_from', 'date_to']));
    }

    /**
     * Etichette umane per i tipi report.
     *
     * @return array<string, string>
     */
    public function reportTypeLabels(): array
    {
        return [
            'commissions_by_closure' => 'Commissioni per noleggi chiusi',
            'cash_by_payment_date' => 'Incassi per data registrazione pagamento',
            'cash_by_closure_month' => 'Incassi attribuiti al mese di chiusura',
        ];
    }

    /**
     * Etichette umane per le metriche.
     *
     * @return array<string, string>
     */
    public function metricLabels(): array
    {
        return [
            'sum_contract_total' => 'Totale noleggi',
            'sum_admin_fee_amount' => 'Totale commissioni admin',
            'avg_admin_fee_percent' => 'Percentuale media commissione admin',
            'count_rentals_closed' => 'Numero noleggi chiusi',
            'sum_paid_total' => 'Totale incassato',
            'sum_paid_commissionable' => 'Totale incassato commissionabile',
            'sum_paid_non_commissionable' => 'Totale incassato non commissionabile',
            'count_payments' => 'Numero pagamenti registrati',
            'count_unique_rentals_paid' => 'Numero noleggi con pagamenti',
        ];
    }

    /**
     * Etichette umane per le dimensioni.
     *
     * @return array<string, string>
     */
    public function dimensionLabels(): array
    {
        return [
            'month' => 'Mese',
            'renter' => 'Renter',
            'vehicle' => 'Veicolo',
            'rental' => 'Noleggio',
            'payment_method' => 'Metodo di pagamento',
            'kind' => 'Tipo di voce',
            'commissionable_flag' => 'Commissionabile',
        ];
    }

    /**
     * Etichette umane per i filtri.
     *
     * @return array<string, string>
     */
    public function filterLabels(): array
    {
        return [
            'organization_id' => 'Renter',
            'vehicle_id' => 'Veicolo',
            'rental_id' => 'Noleggio',
            'payment_method' => 'Metodo di pagamento',
            'kind' => 'Tipo di voce',
            'is_commissionable' => 'Commissionabile',
        ];
    }

    /**
     * Opzioni standard per il metodo di pagamento.
     *
     * NB:
     * i valori devono restare coerenti con quelli salvati in rental_charges.payment_method.
     *
     * @return array<string, string>
     */
    public function paymentMethodOptions(): array
    {
        return [
            'cash' => 'Contanti',
            'card' => 'Carta',
            'bank_transfer' => 'Bonifico',
            'pos' => 'POS',
            'other' => 'Altro',
        ];
    }

    /**
     * Opzioni standard per il tipo voce.
     *
     * NB:
     * i valori devono restare coerenti con quelli salvati in rental_charges.kind.
     *
     * @return array<string, string>
     */
    public function kindOptions(): array
    {
        return [
            'base' => 'Quota base',
            'deposit' => 'Acconto / cauzione',
            'overage' => 'Eccedenza',
            'damage' => 'Danno',
            'fine' => 'Multa',
            'other' => 'Altro',
        ];
    }

    /**
     * Restituisce l'etichetta umana di un tipo report.
     */
    public function getReportTypeLabel(string $value): string
    {
        return $this->reportTypeLabels()[$value] ?? $value;
    }

    /**
     * Restituisce l'etichetta umana di una metrica.
     */
    public function getMetricLabel(string $value): string
    {
        return $this->metricLabels()[$value] ?? $value;
    }

    /**
     * Restituisce l'etichetta umana di una dimensione.
     */
    public function getDimensionLabel(string $value): string
    {
        return $this->dimensionLabels()[$value] ?? $value;
    }

    /**
     * Restituisce l'etichetta umana di un filtro.
     */
    public function getFilterLabel(string $value): string
    {
        return $this->filterLabels()[$value] ?? $value;
    }

    /**
     * Descrizioni umane per i tipi report.
     *
     * @return array<string, string>
     */
    public function reportTypeDescriptions(): array
    {
        return [
            'commissions_by_closure' => 'Analizza i noleggi chiusi nel periodo selezionato. È utile per vedere totale noleggi, commissioni admin e dati economici collegati alla chiusura del noleggio.',
            'cash_by_payment_date' => 'Analizza i pagamenti in base alla data in cui sono stati registrati. È utile per vedere gli incassi effettivamente registrati in uno specifico periodo.',
            'cash_by_closure_month' => 'Analizza gli incassi attribuendoli al mese di chiusura del noleggio. È utile quando vuoi leggere gli incassi con la stessa logica mensile usata per le commissioni.',
        ];
    }

    /**
     * Descrizioni umane per le metriche.
     *
     * @return array<string, string>
     */
    public function metricDescriptions(): array
    {
        return [
            'sum_contract_total' => 'Somma del totale economico dei noleggi considerati.',
            'sum_admin_fee_amount' => 'Somma delle commissioni admin già calcolate e salvate nei noleggi.',
            'avg_admin_fee_percent' => 'Media della percentuale di commissione admin applicata ai noleggi inclusi nel report.',
            'count_rentals_closed' => 'Numero totale di noleggi chiusi inclusi nel report.',
            'sum_paid_total' => 'Somma di tutti gli importi registrati come pagamenti.',
            'sum_paid_commissionable' => 'Somma dei soli importi che possono generare commissione admin.',
            'sum_paid_non_commissionable' => 'Somma degli importi che non generano commissione admin, come per esempio danni o multe.',
            'count_payments' => 'Numero totale delle registrazioni di pagamento incluse nel report.',
            'count_unique_rentals_paid' => 'Numero di noleggi distinti che hanno almeno un pagamento registrato nel periodo.',
        ];
    }

    /**
     * Descrizioni umane per le dimensioni.
     *
     * @return array<string, string>
     */
    public function dimensionDescriptions(): array
    {
        return [
            'month' => 'Raggruppa i risultati per mese.',
            'renter' => 'Raggruppa i risultati per renter.',
            'vehicle' => 'Raggruppa i risultati per veicolo.',
            'rental' => 'Raggruppa i risultati per singolo noleggio.',
            'payment_method' => 'Raggruppa i risultati per metodo di pagamento.',
            'kind' => 'Raggruppa i risultati per tipo di voce economica.',
            'commissionable_flag' => 'Separa i risultati tra voci commissionabili e non commissionabili.',
        ];
    }

    /**
     * Descrizioni umane per i filtri.
     *
     * @return array<string, string>
     */
    public function filterDescriptions(): array
    {
        return [
            'organization_id' => 'Limita il report a un renter specifico.',
            'vehicle_id' => 'Limita il report a un singolo veicolo.',
            'rental_id' => 'Limita il report a un singolo noleggio specifico.',
            'payment_method' => 'Limita il report a uno specifico metodo di pagamento.',
            'kind' => 'Limita il report a una specifica tipologia di voce economica.',
            'is_commissionable' => 'Limita il report alle sole voci che generano commissione, oppure a quelle che non la generano.',
        ];
    }

    /**
     * Descrizioni umane per i tipi di visualizzazione.
     *
     * @return array<string, string>
     */
    public function chartTypeDescriptions(): array
    {
        return [
            'table' => 'Mostra il risultato come tabella dettagliata. È la vista più completa.',
            'bar' => 'Mostra il risultato come grafico a barre. Ha senso soprattutto quando il report ha almeno un raggruppamento e una metrica numerica.',
            'line' => 'Mostra il risultato come grafico a linea. È utile soprattutto per vedere l’andamento nel tempo, per esempio per mese.',
        ];
    }

    /**
     * Restituisce la descrizione di un tipo report.
     */
    public function getReportTypeDescription(string $value): string
    {
        return $this->reportTypeDescriptions()[$value] ?? '';
    }

    /**
     * Restituisce la descrizione di una metrica.
     */
    public function getMetricDescription(string $value): string
    {
        return $this->metricDescriptions()[$value] ?? '';
    }

    /**
     * Restituisce la descrizione di una dimensione.
     */
    public function getDimensionDescription(string $value): string
    {
        return $this->dimensionDescriptions()[$value] ?? '';
    }

    /**
     * Restituisce la descrizione di un filtro.
     */
    public function getFilterDescription(string $value): string
    {
        return $this->filterDescriptions()[$value] ?? '';
    }

    /**
     * Restituisce la descrizione di un tipo di visualizzazione.
     */
    public function getChartTypeDescription(string $value): string
    {
        return $this->chartTypeDescriptions()[$value] ?? '';
    }

    /**
     * Determina se il preset può usare una vista grafica.
     *
     * Regola UI/prodotto:
     * - se tra i raggruppamenti è presente "month",
     *   l'utente può scegliere anche grafico a barre o grafico a linea;
     * - altrimenti la vista viene salvata come tabella.
     */
    public function canChooseChartType(): bool
    {
        return in_array('month', $this->dimensions, true);
    }

    /**
     * Restituisce il tipo di vista effettivo da salvare.
     *
     * Se il report non è compatibile con una vista grafica,
     * forziamo sempre la tabella.
     */
    public function effectiveChartType(): string
    {
        return $this->canChooseChartType()
            ? $this->chart_type
            : 'table';
    }

    /**
     * Messaggio informativo per la UI sulla vista del report.
     */
    public function chartTypeHelpMessage(): string
    {
        if ($this->canChooseChartType()) {
            return 'Hai selezionato il raggruppamento per mese, quindi puoi scegliere se visualizzare il risultato come tabella o grafico.';
        }

        return 'Senza il raggruppamento per mese, questo report verrà salvato come tabella. Se vuoi usare un grafico, aggiungi "Mese" tra i raggruppamenti.';
    }

    /**
     * Quando cambia il report type, ripulisce metriche/dimensioni/filtri non compatibili.
     */
    public function updatedReportType(string $value): void
    {
        $this->metrics = array_values(array_intersect($this->metrics, $this->availableMetrics()));
        $this->dimensions = array_values(array_intersect($this->dimensions, $this->availableDimensions()));

        $availableFilters = $this->availableFilters();

        foreach (array_keys($this->filters) as $key) {
            if (! in_array($key, $availableFilters, true)) {
                $this->filters[$key] = null;
            }
        }

        /**
         * Se il report non usa renter/veicolo, azzeriamo anche la UI relativa.
         */
        if (! in_array('organization_id', $availableFilters, true)) {
            $this->organizationSearch = '';
            $this->organizationOptions = [];
            $this->selectedOrganizationLabel = null;
        }

        if (! in_array('vehicle_id', $availableFilters, true)) {
            $this->vehicleSearch = '';
            $this->vehicleOptions = [];
            $this->selectedVehicleLabel = null;
        }

        if (! in_array('rental_id', $availableFilters, true)) {
            $this->rentalSearch = '';
            $this->rentalOptions = [];
            $this->selectedRentalLabel = null;
            $this->filters['rental_id'] = null;
        }

        /**
         * Se il nuovo tipo report non consente più un grafico,
         * la vista torna automaticamente a tabella.
         */
        if (! $this->canChooseChartType()) {
            $this->chart_type = 'table';
        }

        $this->resetValidation();
        $this->successMessage = null;
    }

    /**
     * Quando cambiano i raggruppamenti, controlla se la vista grafica
     * è ancora compatibile con la configurazione corrente.
     *
     * Nota Livewire:
     * con proprietà array, l'hook può ricevere il singolo valore aggiornato
     * e la relativa chiave, non necessariamente l'intero array.
     *
     * @param mixed $value
     * @param mixed $key
     */
    public function updatedDimensions($value, $key = null): void
    {
        /**
         * Normalizza sempre la proprietà dimensions partendo dallo stato
         * attuale del componente, invece di fidarsi del payload del hook.
         */
        $this->dimensions = collect($this->dimensions)
            ->filter(fn ($dimension) => is_string($dimension) && $dimension !== '')
            ->unique()
            ->values()
            ->toArray();

        if (! $this->canChooseChartType()) {
            $this->chart_type = 'table';
        }

        $this->resetValidation();
        $this->successMessage = null;
    }

    /**
     * Aggiorna la ricerca renter quando l'utente digita.
     */
    public function updatedOrganizationSearch(string $value): void
    {
        $this->filters['organization_id'] = null;
        $this->selectedOrganizationLabel = null;

        $this->searchOrganizations($value);
    }

    /**
     * Aggiorna la ricerca veicolo quando l'utente digita.
     */
    public function updatedVehicleSearch(string $value): void
    {
        $this->filters['vehicle_id'] = null;
        $this->selectedVehicleLabel = null;

        $this->searchVehicles($value);
    }

    /**
     * Aggiorna la ricerca noleggio quando l'utente digita.
     */
    public function updatedRentalSearch(string $value): void
    {
        $this->filters['rental_id'] = null;
        $this->selectedRentalLabel = null;

        $this->searchRentals($value);
    }

    /**
     * Cerca noleggi usando un termine libero.
     *
     * Regole:
     * - se è selezionato un renter, limita la ricerca ai noleggi di quel renter;
     * - se è selezionato un veicolo, limita la ricerca ai noleggi di quel veicolo.
     *
     * Ricerca su:
     * - id noleggio
     * - number_id
     * - nome cliente
     * - targa / marca / modello veicolo
     */
    protected function searchRentals(string $search): void
    {
        if (trim($search) === '') {
            $this->rentalOptions = [];

            return;
        }

        $term = mb_strtolower(trim($search));
        $term = str_replace('*', '%', $term);
        $term = preg_replace('/\s+/', '%', $term);
        $like = "%{$term}%";

        $q = Rental::query()
            ->with([
                'customer:id,name',
                'vehicle:id,plate,make,model',
            ])
            ->whereNull('deleted_at');

        if (! empty($this->filters['organization_id'])) {
            $q->where('organization_id', (int) $this->filters['organization_id']);
        }

        if (! empty($this->filters['vehicle_id'])) {
            $q->where('vehicle_id', (int) $this->filters['vehicle_id']);
        }

        $this->rentalOptions = $q
            ->where(function ($query) use ($like) {
                $query->whereRaw('CAST(rentals.id AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(rentals.number_id AS CHAR) LIKE ?', [$like])
                    ->orWhereHas('customer', function ($customerQuery) use ($like) {
                        $customerQuery->whereRaw('LOWER(name) LIKE ?', [$like]);
                    })
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($like) {
                        $vehicleQuery->whereRaw('LOWER(plate) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(make) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(model) LIKE ?', [$like]);
                    });
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get([
                'id',
                'number_id',
                'customer_id',
                'vehicle_id',
                'organization_id',
            ])
            ->map(function (Rental $rental): array {
                $vehicleLabel = trim(implode(' ', array_filter([
                    optional($rental->vehicle)->plate,
                    optional($rental->vehicle)->make,
                    optional($rental->vehicle)->model,
                ])));

                $labelParts = array_filter([
                    $rental->display_number_label,
                    optional($rental->customer)->name,
                    $vehicleLabel,
                ]);

                $label = implode(' — ', $labelParts);

                return [
                    'id' => $rental->id,
                    'label' => $label !== '' ? $label : ('#' . $rental->id),
                ];
            })
            ->toArray();
    }

    /**
     * Seleziona un noleggio dai risultati di ricerca.
     */
    public function selectRental(int $rentalId, string $rentalLabel): void
    {
        $this->filters['rental_id'] = $rentalId;
        $this->selectedRentalLabel = $rentalLabel;
        $this->rentalSearch = $rentalLabel;
        $this->rentalOptions = [];
    }

    /**
     * Azzera il noleggio selezionato.
     */
    public function clearRentalSelection(): void
    {
        $this->filters['rental_id'] = null;
        $this->rentalSearch = '';
        $this->rentalOptions = [];
        $this->selectedRentalLabel = null;
    }

    /**
     * Cerca renter attivi di tipo "renter" usando un termine libero.
     *
     * Ricerca su:
     * - name
     * - legal_name
     * - email
     * - city
     */
    protected function searchOrganizations(string $search): void
    {
        if (trim($search) === '') {
            $this->organizationOptions = [];

            return;
        }

        $term = mb_strtolower(trim($search));
        $term = str_replace('*', '%', $term);
        $term = preg_replace('/\s+/', '%', $term);
        $like = "%{$term}%";

        $this->organizationOptions = Organization::query()
            ->where('type', 'renter')
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(legal_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(city) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'legal_name', 'email', 'city'])
            ->map(function (Organization $organization): array {
                $label = $organization->name;

                if (! empty($organization->city)) {
                    $label .= ' — ' . $organization->city;
                }

                return [
                    'id' => $organization->id,
                    'label' => $label,
                    'name' => $organization->name,
                ];
            })
            ->toArray();
    }

    /**
     * Cerca veicoli usando un termine libero.
     *
     * Regole:
     * - se non è selezionato alcun renter, cerca su tutti i veicoli attivi;
     * - se è selezionato un renter, limita la ricerca ai veicoli assegnati a quel renter.
     *
     * Ricerca su:
     * - plate
     * - vin
     * - make
     * - model
     */
    protected function searchVehicles(string $search): void
    {
        if (trim($search) === '') {
            $this->vehicleOptions = [];

            return;
        }

        $term = mb_strtolower(trim($search));
        $term = str_replace('*', '%', $term);
        $term = preg_replace('/\s+/', '%', $term);
        $like = "%{$term}%";

        $q = Vehicle::query()
            ->where('is_active', true);

        /**
         * Se è stato selezionato un renter, mostra solo i veicoli
         * che hanno almeno un'assegnazione verso quell'organizzazione.
         *
         * Nota:
         * per la UI ci basta sapere che il veicolo è assegnato al renter.
         * Il report continuerà comunque a filtrare sui rentals reali.
         */
        if (! empty($this->filters['organization_id'])) {
            $organizationId = (int) $this->filters['organization_id'];

            $q->whereHas('assignments', function ($assignmentQuery) use ($organizationId) {
                $assignmentQuery->where('renter_org_id', $organizationId);
            });
        }

        $this->vehicleOptions = $q
            ->where(function ($query) use ($like) {
                $query->whereRaw('LOWER(plate) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(vin) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(make) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(model) LIKE ?', [$like]);
            })
            ->orderBy('plate')
            ->limit(10)
            ->get(['id', 'plate', 'vin', 'make', 'model'])
            ->map(function (Vehicle $vehicle): array {
                $label = trim(implode(' ', array_filter([
                    $vehicle->plate,
                    $vehicle->make,
                    $vehicle->model,
                ])));

                return [
                    'id' => $vehicle->id,
                    'label' => $label,
                    'plate' => $vehicle->plate,
                ];
            })
            ->toArray();
    }

    /**
     * Seleziona un renter dai risultati di ricerca.
     *
     * Quando cambia il renter:
     * - salviamo il nuovo organization_id;
     * - azzeriamo l'eventuale veicolo già selezionato,
     *   perché potrebbe non appartenere al renter scelto;
     * - puliamo anche la UI della ricerca veicolo.
     */
    public function selectOrganization(int $organizationId, string $organizationLabel): void
    {
        $currentOrganizationId = ! empty($this->filters['organization_id'])
            ? (int) $this->filters['organization_id']
            : null;

        /**
         * Se il renter cambia davvero, azzeriamo il filtro veicolo
         * e tutta la UI collegata alla sua ricerca/selezione.
         */
        if ($currentOrganizationId !== $organizationId) {
            $this->clearVehicleSelection();
        }

        $this->filters['organization_id'] = $organizationId;
        $this->selectedOrganizationLabel = $organizationLabel;
        $this->organizationSearch = $organizationLabel;
        $this->organizationOptions = [];
    }

    /**
     * Seleziona un veicolo dai risultati di ricerca.
     */
    public function selectVehicle(int $vehicleId, string $vehicleLabel): void
    {
        $currentVehicleId = ! empty($this->filters['vehicle_id'])
            ? (int) $this->filters['vehicle_id']
            : null;

        if ($currentVehicleId !== $vehicleId) {
            $this->clearRentalSelection();
        }

        $this->filters['vehicle_id'] = $vehicleId;
        $this->selectedVehicleLabel = $vehicleLabel;
        $this->vehicleSearch = $vehicleLabel;
        $this->vehicleOptions = [];
    }

    /**
     * Azzera il renter selezionato.
     *
     * Quando il renter viene rimosso:
     * - azzeriamo anche il veicolo selezionato;
     * - puliamo la UI della ricerca renter e veicolo;
     * - evitiamo combinazioni di filtri incoerenti.
     */
    public function clearOrganizationSelection(): void
    {
        $this->filters['organization_id'] = null;
        $this->organizationSearch = '';
        $this->organizationOptions = [];
        $this->selectedOrganizationLabel = null;

        /**
         * Il veicolo dipende dal renter lato UI:
         * se rimuovo il renter, rimuovo anche il veicolo selezionato.
         */
        $this->clearVehicleSelection();
    }

    /**
     * Azzera il veicolo selezionato.
     */
    public function clearVehicleSelection(): void
    {
        $this->filters['vehicle_id'] = null;
        $this->vehicleSearch = '';
        $this->vehicleOptions = [];
        $this->selectedVehicleLabel = null;

        /**
         * Il noleggio dipende anche dal contesto del veicolo:
         * se rimuovo il veicolo, rimuovo anche l'eventuale noleggio selezionato.
         */
        $this->clearRentalSelection();
    }

    /**
     * Regole di validazione del componente.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('report_presets', 'name')
                    ->where(fn ($query) => $query->where('created_by', Auth::id())),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'report_type' => [
                'required',
                'string',
                Rule::in($this->reportTypeOptions()),
            ],

            'chart_type' => [
                'nullable',
                'string',
                Rule::in(['table', 'bar', 'line']),
            ],

            'metrics' => [
                'required',
                'array',
                'min:1',
            ],

            'metrics.*' => [
                'required',
                'string',
                Rule::in($this->availableMetrics()),
            ],

            'dimensions' => [
                'nullable',
                'array',
                'max:2',
            ],

            'dimensions.*' => [
                'required',
                'string',
                Rule::in($this->availableDimensions()),
            ],

            'filters.organization_id' => [
                'nullable',
                'integer',
                'exists:organizations,id',
            ],

            'filters.vehicle_id' => [
                'nullable',
                'integer',
                'exists:vehicles,id',
            ],

            'filters.rental_id' => [
                'nullable',
                'integer',
                'exists:rentals,id',
            ],

            'filters.payment_method' => [
                'nullable',
                'string',
                Rule::in(array_keys($this->paymentMethodOptions())),
            ],

            'filters.kind' => [
                'nullable',
                'string',
                Rule::in(array_keys($this->kindOptions())),
            ],

            'filters.is_commissionable' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Messaggi di validazione personalizzati.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Il nome del preset è obbligatorio.',
            'name.unique' => 'Esiste già un preset con questo nome.',
            'report_type.required' => 'Seleziona un tipo di analisi.',
            'metrics.required' => 'Seleziona almeno un dato da mostrare.',
            'metrics.min' => 'Seleziona almeno un dato da mostrare.',
            'dimensions.max' => 'Puoi selezionare al massimo 2 raggruppamenti.',
            'filters.payment_method.in' => 'Seleziona un metodo di pagamento valido.',
            'filters.kind.in' => 'Seleziona un tipo di voce valido.',
        ];
    }

    /**
     * Salva il preset report.
     */
    public function save(): void
    {
        $validated = $this->validate();

        /**
         * Ripulisce i filtri vuoti e si assicura che i filtri runtime
         * non vengano mai persistiti.
         */
        $filters = collect($validated['filters'] ?? [])
            ->except(['date_from', 'date_to'])
            ->reject(function ($value) {
                return $value === null || $value === '';
            })
            ->toArray();

        ReportPreset::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'report_type' => $validated['report_type'],
            'metrics' => array_values($validated['metrics']),
            'dimensions' => array_values($validated['dimensions'] ?? []),
            'filters' => $filters,
            'chart_type' => $this->effectiveChartType(),
            'created_by' => Auth::id(),
        ]);

        $this->resetForm();

        $this->successMessage = 'Report salvato correttamente.';
    }

    /**
     * Reimposta il form dopo il salvataggio.
     */
    protected function resetForm(): void
    {
        $this->name = '';
        $this->description = '';
        $this->report_type = '';
        $this->chart_type = 'table';
        $this->metrics = [];
        $this->dimensions = [];
        $this->filters = [
            'organization_id' => null,
            'vehicle_id' => null,
            'rental_id' => null,
            'payment_method' => null,
            'kind' => null,
            'is_commissionable' => null,
        ];

        $this->organizationSearch = '';
        $this->organizationOptions = [];
        $this->selectedOrganizationLabel = null;
        $this->rentalSearch = '';
        $this->rentalOptions = [];
        $this->selectedRentalLabel = null;
        $this->vehicleSearch = '';
        $this->vehicleOptions = [];
        $this->selectedVehicleLabel = null;

        $this->resetValidation();
    }

    /**
     * Renderizza la view del componente.
     */
    public function render()
    {
        return view('livewire.reports.create-report-preset', [
            'reportTypeOptions' => $this->reportTypeOptions(),
            'availableMetrics' => $this->availableMetrics(),
            'availableDimensions' => $this->availableDimensions(),
            'availableFilters' => $this->availableFilters(),
            'paymentMethodOptions' => $this->paymentMethodOptions(),
            'kindOptions' => $this->kindOptions(),
        ]);
    }
}