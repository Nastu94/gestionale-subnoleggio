<?php

namespace App\Livewire\Reports;

use App\Models\Organization;
use App\Models\ReportPreset;
use App\Models\Vehicle;
use App\Services\Reports\ReportRunner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Componente Livewire per la modifica di un preset report.
 *
 * Responsabilità:
 * - mostrare l'elenco dei report salvati;
 * - caricare un report selezionato nel form;
 * - permettere la modifica dei dati salvabili;
 * - mantenere la stessa logica della creazione:
 *   - niente date nei filtri persistiti;
 *   - ricerca renter / veicolo;
 *   - vista grafica disponibile solo con raggruppamento "month".
 */
class EditReportPreset extends Component
{
    /**
     * Elenco report disponibili.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $reportPresets = [];

    /**
     * ID del report selezionato per la modifica.
     */
    public ?int $selectedReportPresetId = null;

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
     * Messaggio informativo iniziale.
     */
    public ?string $infoMessage = 'Seleziona un report salvato per modificarlo.';

    /**
     * Inizializzazione componente.
     */
    public function mount(): void
    {
        $this->loadReportPresets();
    }

    /**
     * Carica l'elenco dei report salvati.
     */
    public function loadReportPresets(): void
    {
        $this->reportPresets = ReportPreset::query()
            ->latest('id')
            ->get([
                'id',
                'name',
                'description',
                'report_type',
                'chart_type',
            ])
            ->map(function (ReportPreset $reportPreset): array {
                return [
                    'id' => $reportPreset->id,
                    'name' => $reportPreset->name,
                    'description' => $reportPreset->description,
                    'report_type' => $reportPreset->report_type,
                    'chart_type' => $reportPreset->chart_type,
                ];
            })
            ->toArray();
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
     * Opzioni standard per il metodo di pagamento.
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
     * Determina se il preset può usare una vista grafica.
     */
    public function canChooseChartType(): bool
    {
        return in_array('month', $this->dimensions, true);
    }

    /**
     * Restituisce il tipo di vista effettivo da salvare.
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
     * Restituisce l'etichetta umana di un tipo report.
     */
    public function getReportTypeLabel(string $value): string
    {
        return $this->reportTypeLabels()[$value] ?? $value;
    }

    /**
     * Restituisce la descrizione di un tipo report.
     */
    public function getReportTypeDescription(string $value): string
    {
        return $this->reportTypeDescriptions()[$value] ?? '';
    }

    /**
     * Restituisce l'etichetta umana di una metrica.
     */
    public function getMetricLabel(string $value): string
    {
        return $this->metricLabels()[$value] ?? $value;
    }

    /**
     * Restituisce la descrizione di una metrica.
     */
    public function getMetricDescription(string $value): string
    {
        return $this->metricDescriptions()[$value] ?? '';
    }

    /**
     * Restituisce l'etichetta umana di una dimensione.
     */
    public function getDimensionLabel(string $value): string
    {
        return $this->dimensionLabels()[$value] ?? $value;
    }

    /**
     * Restituisce la descrizione di una dimensione.
     */
    public function getDimensionDescription(string $value): string
    {
        return $this->dimensionDescriptions()[$value] ?? '';
    }

    /**
     * Restituisce l'etichetta umana di un filtro.
     */
    public function getFilterLabel(string $value): string
    {
        return $this->filterLabels()[$value] ?? $value;
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
     * Seleziona un report salvato e carica i dati nel form.
     */
    public function selectReportPreset(int $reportPresetId): void
    {
        $reportPreset = ReportPreset::query()->findOrFail($reportPresetId);

        $filters = $reportPreset->filters ?? [];

        unset($filters['date_from'], $filters['date_to']);

        $this->selectedReportPresetId = $reportPreset->id;
        $this->name = $reportPreset->name;
        $this->description = $reportPreset->description ?? '';
        $this->report_type = $reportPreset->report_type;
        $this->metrics = array_values($reportPreset->metrics ?? []);
        $this->dimensions = array_values($reportPreset->dimensions ?? []);
        $this->chart_type = $reportPreset->chart_type ?: 'table';

        $this->filters = array_merge([
            'organization_id' => null,
            'vehicle_id' => null,
            'rental_id' => null,
            'payment_method' => null,
            'kind' => null,
            'is_commissionable' => null,
        ], $filters);

        /**
         * Riallinea la vista grafica alle regole correnti.
         */
        if (! $this->canChooseChartType()) {
            $this->chart_type = 'table';
        }

        /**
         * Precarica le etichette UI per renter e veicolo.
         */
        $this->loadSelectedOrganizationLabel();
        $this->loadSelectedVehicleLabel();
        $this->loadSelectedRentalLabel();

        $this->organizationOptions = [];
        $this->vehicleOptions = [];
        $this->successMessage = null;
        $this->infoMessage = 'Stai modificando il report selezionato.';
        $this->resetValidation();
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
     * @param mixed $value
     * @param mixed $key
     */
    public function updatedDimensions($value, $key = null): void
    {
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
     * Cerca renter attivi di tipo "renter".
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
     * Se è selezionato un renter, limita la ricerca ai veicoli assegnati a quel renter.
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
     * Precarica l'etichetta UI del noleggio selezionato.
     */
    protected function loadSelectedRentalLabel(): void
    {
        if (empty($this->filters['rental_id'])) {
            $this->rentalSearch = '';
            $this->selectedRentalLabel = null;

            return;
        }

        $rental = Rental::query()
            ->with([
                'customer:id,name',
                'vehicle:id,plate,make,model',
            ])
            ->find($this->filters['rental_id'], [
                'id',
                'number_id',
                'customer_id',
                'vehicle_id',
            ]);

        if (! $rental) {
            $this->rentalSearch = '';
            $this->selectedRentalLabel = null;

            return;
        }

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

        $this->selectedRentalLabel = $label !== '' ? $label : ('#' . $rental->id);
        $this->rentalSearch = $this->selectedRentalLabel;
    }

    /**
     * Seleziona un renter dai risultati di ricerca.
     */
    public function selectOrganization(int $organizationId, string $organizationLabel): void
    {
        $currentOrganizationId = ! empty($this->filters['organization_id'])
            ? (int) $this->filters['organization_id']
            : null;

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
     */
    public function clearOrganizationSelection(): void
    {
        $this->filters['organization_id'] = null;
        $this->organizationSearch = '';
        $this->organizationOptions = [];
        $this->selectedOrganizationLabel = null;

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
            'selectedReportPresetId' => [
                'required',
                'integer',
                'exists:report_presets,id',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('report_presets', 'name')
                    ->where(fn ($query) => $query->where('created_by', Auth::id()))
                    ->ignore($this->selectedReportPresetId),
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
            'selectedReportPresetId.required' => 'Seleziona un report da modificare.',
            'selectedReportPresetId.exists' => 'Il report selezionato non è valido.',
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
     * Salva le modifiche del preset selezionato.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $reportPreset = ReportPreset::query()->findOrFail($this->selectedReportPresetId);

        $filters = collect($validated['filters'] ?? [])
            ->except(['date_from', 'date_to'])
            ->reject(function ($value) {
                return $value === null || $value === '';
            })
            ->toArray();

        $reportPreset->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'report_type' => $validated['report_type'],
            'metrics' => array_values($validated['metrics']),
            'dimensions' => array_values($validated['dimensions'] ?? []),
            'filters' => $filters,
            'chart_type' => $this->effectiveChartType(),
        ]);

        $this->loadReportPresets();
        $this->successMessage = 'Report aggiornato correttamente.';
        $this->infoMessage = null;
    }

    /**
     * Precarica l'etichetta UI del renter selezionato.
     */
    protected function loadSelectedOrganizationLabel(): void
    {
        if (empty($this->filters['organization_id'])) {
            $this->organizationSearch = '';
            $this->selectedOrganizationLabel = null;

            return;
        }

        $organization = Organization::query()
            ->find($this->filters['organization_id'], ['id', 'name', 'city']);

        if (! $organization) {
            $this->organizationSearch = '';
            $this->selectedOrganizationLabel = null;

            return;
        }

        $label = $organization->name;

        if (! empty($organization->city)) {
            $label .= ' — ' . $organization->city;
        }

        $this->selectedOrganizationLabel = $label;
        $this->organizationSearch = $label;
    }

    /**
     * Precarica l'etichetta UI del veicolo selezionato.
     */
    protected function loadSelectedVehicleLabel(): void
    {
        if (empty($this->filters['vehicle_id'])) {
            $this->vehicleSearch = '';
            $this->selectedVehicleLabel = null;

            return;
        }

        $vehicle = Vehicle::query()
            ->find($this->filters['vehicle_id'], ['id', 'plate', 'make', 'model']);

        if (! $vehicle) {
            $this->vehicleSearch = '';
            $this->selectedVehicleLabel = null;

            return;
        }

        $label = trim(implode(' ', array_filter([
            $vehicle->plate,
            $vehicle->make,
            $vehicle->model,
        ])));

        $this->selectedVehicleLabel = $label;
        $this->vehicleSearch = $label;
    }

    /**
     * Renderizza la view del componente.
     */
    public function render()
    {
        return view('livewire.reports.edit-report-preset', [
            'reportTypeOptions' => $this->reportTypeOptions(),
            'availableMetrics' => $this->availableMetrics(),
            'availableDimensions' => $this->availableDimensions(),
            'availableFilters' => $this->availableFilters(),
            'paymentMethodOptions' => $this->paymentMethodOptions(),
            'kindOptions' => $this->kindOptions(),
        ]);
    }
}