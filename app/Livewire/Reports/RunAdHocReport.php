<?php

namespace App\Livewire\Reports;

use App\Domain\Fees\AdminFeeResolver;
use App\Models\Organization;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Models\ReportPreset;
use App\Services\Reports\ReportRunner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Componente Livewire per l'esecuzione di una statistica senza salvataggio preset.
 *
 * Responsabilità:
 * - configurare un report "al volo";
 * - eseguirlo senza persistere nulla su database;
 * - mostrare risultati, colonne, grafico e dati arricchiti
 *   con la stessa logica della tab "run saved preset".
 */
class RunAdHocReport extends Component
{
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
     * Filtri strutturali runtime.
     *
     * @var array<string, mixed>
     */
    public array $filters = [
        'organization_id' => null,
        'vehicle_id' => null,
        'payment_method' => null,
        'kind' => null,
        'is_commissionable' => null,
    ];

    /**
     * Data iniziale runtime.
     */
    public ?string $dateFrom = null;

    /**
     * Data finale runtime.
     */
    public ?string $dateTo = null;

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
     * Righe risultato del report eseguito.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $reportRows = [];

    /**
     * Colonne tabella risultati.
     *
     * @var array<int, string>
     */
    public array $reportColumns = [];

    /**
     * Stato grafico.
     */
    public bool $canRenderChart = false;

    /**
     * Messaggio informativo grafico.
     */
    public ?string $chartInfoMessage = null;

    /**
     * Dati grafico pronti al rendering.
     *
     * @var array<string, mixed>
     */
    public array $chartData = [];

    /**
     * Stato esecuzione.
     */
    public bool $hasRunReport = false;

    /**
     * Errore runtime.
     */
    public ?string $runError = null;

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
     * Etichette umane per i metodi di pagamento.
     *
     * @return array<string, string>
     */
    public function paymentMethodLabels(): array
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
     * Etichette umane per i tipi voce.
     *
     * @return array<string, string>
     */
    public function kindLabels(): array
    {
        return [
            'base' => 'Quota base',
            'distance_overage' => 'Eccedenza chilometrica',
            'damage' => 'Danno',
            'surcharge' => 'Supplemento',
            'fine' => 'Multa',
            'other' => 'Altro',
            'acconto' => 'Acconto',
            'base+distance_overage' => 'Quota base + eccedenza chilometrica',
        ];
    }

    /**
     * Etichette umane per le colonne risultato.
     *
     * @return array<string, string>
     */
    public function resultColumnLabels(): array
    {
        return [
            'month' => 'Mese',
            'organization_id' => 'Renter',
            'vehicle_id' => 'Veicolo',
            'payment_method' => 'Metodo di pagamento',
            'kind' => 'Tipo di voce',
            'is_commissionable' => 'Commissionabile',
            'sum_contract_total' => 'Totale noleggi',
            'sum_admin_fee_amount' => 'Totale commissioni admin',
            'avg_admin_fee_percent' => 'Percentuale media commissione',
            'count_rentals_closed' => 'Noleggi chiusi',
            'sum_paid_total' => 'Totale incassato',
            'sum_paid_commissionable' => 'Totale commissionabile',
            'sum_paid_non_commissionable' => 'Totale non commissionabile',
            'count_payments' => 'Pagamenti registrati',
            'count_unique_rentals_paid' => 'Noleggi con pagamenti',
            'resolved_commissionable_total' => 'Totale da commissionare',
            'resolved_admin_fee_amount' => 'Commissione admin',
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
     * Determina se il report può usare una vista grafica.
     */
    public function canChooseChartType(): bool
    {
        return in_array('month', $this->dimensions, true);
    }

    /**
     * Restituisce il tipo di vista effettivo da usare.
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

        return 'Senza il raggruppamento per mese, questo report verrà mostrato come tabella. Se vuoi usare un grafico, aggiungi "Mese" tra i raggruppamenti.';
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
     * Restituisce l'etichetta umana di una colonna risultato.
     */
    public function getResultColumnLabel(string $value): string
    {
        return $this->resultColumnLabels()[$value] ?? $value;
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

        if (! $this->canChooseChartType()) {
            $this->chart_type = 'table';
        }

        $this->resetExecutionState();
        $this->resetValidation();
    }

    /**
     * Quando cambiano i raggruppamenti, riallinea la vista.
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

        $this->resetExecutionState();
        $this->resetValidation();
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
     * Regole di validazione del componente.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
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
            'dateFrom' => [
                'required',
                'date',
            ],
            'dateTo' => [
                'required',
                'date',
                'after_or_equal:dateFrom',
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
            'report_type.required' => 'Seleziona un tipo di analisi.',
            'metrics.required' => 'Seleziona almeno un dato da mostrare.',
            'metrics.min' => 'Seleziona almeno un dato da mostrare.',
            'dimensions.max' => 'Puoi selezionare al massimo 2 raggruppamenti.',
            'filters.payment_method.in' => 'Seleziona un metodo di pagamento valido.',
            'filters.kind.in' => 'Seleziona un tipo di voce valido.',
            'dateFrom.required' => 'La data iniziale è obbligatoria.',
            'dateTo.required' => 'La data finale è obbligatoria.',
            'dateTo.after_or_equal' => 'La data finale non può essere precedente alla data iniziale.',
        ];
    }

    /**
     * Esegue il report senza salvarlo.
     */
    public function runReport(ReportRunner $reportRunner, AdminFeeResolver $adminFeeResolver): void
    {
        $validated = $this->validate();

        $this->runError = null;
        $this->hasRunReport = false;
        $this->reportRows = [];
        $this->reportColumns = [];
        $this->chartData = [];
        $this->canRenderChart = false;
        $this->chartInfoMessage = null;

        $runtimeFilters = collect($validated['filters'] ?? [])
            ->except(['date_from', 'date_to'])
            ->reject(fn ($value) => $value === null || $value === '')
            ->toArray();

        $runtimeFilters['date_from'] = $validated['dateFrom'];
        $runtimeFilters['date_to'] = $validated['dateTo'];

        /**
         * Creiamo un preset runtime in memoria, senza persistenza.
         */
        $runtimePreset = new ReportPreset([
            'name' => 'Ad hoc',
            'description' => null,
            'report_type' => $validated['report_type'],
            'metrics' => array_values($validated['metrics']),
            'dimensions' => array_values($validated['dimensions'] ?? []),
            'filters' => $runtimeFilters,
            'chart_type' => $this->effectiveChartType(),
            'created_by' => Auth::id(),
        ]);

        try {
            $rows = $reportRunner->run($runtimePreset);

            $rawRows = collect($rows)
                ->map(fn ($row): array => (array) $row)
                ->values()
                ->toArray();

            $this->reportRows = $this->enrichReportRows(
                rows: $rawRows,
                runtimePreset: $runtimePreset,
                adminFeeResolver: $adminFeeResolver,
            );

            $this->reportColumns = ! empty($this->reportRows)
                ? $this->sortReportColumns(array_keys($this->reportRows[0]))
                : [];

            $this->prepareChartData($runtimePreset);
            $this->hasRunReport = true;
        } catch (\InvalidArgumentException $exception) {
            $this->runError = $exception->getMessage();
        }
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
    }

    /**
     * Arricchisce le righe risultato con nomi umani e totali resolver.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function enrichReportRows(
        array $rows,
        ReportPreset $runtimePreset,
        AdminFeeResolver $adminFeeResolver
    ): array {
        if (empty($rows)) {
            return [];
        }

        $organizationMap = $this->loadOrganizationMap($rows);
        $vehicleMap = $this->loadVehicleMap($rows);

        $commissionableTotalsByGroup = $this->buildResolvedCommissionableTotalsByGroup(
            runtimePreset: $runtimePreset,
            adminFeeResolver: $adminFeeResolver,
        );

        return collect($rows)
            ->map(function (array $row) use ($organizationMap, $vehicleMap, $commissionableTotalsByGroup): array {
                $groupKey = $this->buildRowGroupKey($row);

                if (array_key_exists('organization_id', $row)) {
                    $organizationId = $row['organization_id'];
                    $row['organization_id'] = $organizationMap[(int) $organizationId] ?? $organizationId;
                }

                if (array_key_exists('vehicle_id', $row)) {
                    $vehicleId = $row['vehicle_id'];
                    $row['vehicle_id'] = $vehicleMap[(int) $vehicleId] ?? $vehicleId;
                }

                if (array_key_exists('payment_method', $row)) {
                    $row['payment_method'] = $this->humanizePaymentMethod($row['payment_method']);
                }

                if (array_key_exists('kind', $row)) {
                    $row['kind'] = $this->humanizeKind($row['kind']);
                }

                if (array_key_exists('is_commissionable', $row)) {
                    $row['is_commissionable'] = $this->humanizeBooleanValue($row['is_commissionable']);
                }

                $resolvedGroupTotals = $commissionableTotalsByGroup[$groupKey] ?? [
                    'resolved_commissionable_total' => 0.0,
                    'resolved_admin_fee_amount' => 0.0,
                ];

                $row['resolved_commissionable_total'] = $resolvedGroupTotals['resolved_commissionable_total'] ?? 0.0;
                $row['resolved_admin_fee_amount'] = $resolvedGroupTotals['resolved_admin_fee_amount'] ?? 0.0;

                return $row;
            })
            ->values()
            ->toArray();
    }

    /**
     * Costruisce i totali resolver aggregati per gruppo.
     *
     * @return array<string, array<string, float>>
     */
    protected function buildResolvedCommissionableTotalsByGroup(
        ReportPreset $runtimePreset,
        AdminFeeResolver $adminFeeResolver
    ): array {
        $filters = $runtimePreset->filters ?? [];
        $dimensions = $runtimePreset->dimensions ?? [];

        $rentals = Rental::query()
            ->with([
                'charges:id,rental_id,is_commissionable,amount',
            ])
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [
                $filters['date_from'] . ' 00:00:00',
                $filters['date_to'] . ' 23:59:59',
            ]);

        if (! empty($filters['organization_id'])) {
            $rentals->where('organization_id', (int) $filters['organization_id']);
        }

        if (! empty($filters['vehicle_id'])) {
            $rentals->where('vehicle_id', (int) $filters['vehicle_id']);
        }

        $groupedTotals = [];

        foreach ($rentals->get([
            'id',
            'organization_id',
            'vehicle_id',
            'closed_at',
            'actual_return_at',
        ]) as $rental) {
            $feeReferenceDate = $rental->actual_return_at ?: $rental->closed_at;

            $resolved = $adminFeeResolver->calculateForRental($rental, $feeReferenceDate);

            $groupValues = [];

            foreach ($dimensions as $dimension) {
                if ($dimension === 'month') {
                    $groupValues['month'] = $rental->closed_at?->format('Y-m');
                }

                if ($dimension === 'renter') {
                    $groupValues['organization_id'] = $rental->organization_id;
                }

                if ($dimension === 'vehicle') {
                    $groupValues['vehicle_id'] = $rental->vehicle_id;
                }
            }

            $groupKey = $this->buildRowGroupKey($groupValues);

            if (! array_key_exists($groupKey, $groupedTotals)) {
                $groupedTotals[$groupKey] = [
                    'resolved_commissionable_total' => 0.0,
                    'resolved_admin_fee_amount' => 0.0,
                ];
            }

            $groupedTotals[$groupKey]['resolved_commissionable_total'] += (float) ($resolved['commissionable_total'] ?? 0.0);
            $groupedTotals[$groupKey]['resolved_admin_fee_amount'] += (float) ($resolved['amount'] ?? 0.0);
        }

        return collect($groupedTotals)
            ->mapWithKeys(function ($value, $key): array {
                return [
                    $key => [
                        'resolved_commissionable_total' => round((float) ($value['resolved_commissionable_total'] ?? 0.0), 2),
                        'resolved_admin_fee_amount' => round((float) ($value['resolved_admin_fee_amount'] ?? 0.0), 2),
                    ],
                ];
            })
            ->toArray();
    }

    /**
     * Costruisce una chiave stabile per il raggruppamento.
     *
     * @param array<string, mixed> $values
     */
    protected function buildRowGroupKey(array $values): string
    {
        $groupParts = [];

        foreach (['month', 'organization_id', 'vehicle_id'] as $key) {
            if (array_key_exists($key, $values)) {
                $groupParts[] = $key . ':' . ($values[$key] ?? '__null__');
            }
        }

        if (empty($groupParts)) {
            return '__all__';
        }

        return implode('|', $groupParts);
    }

    /**
     * Carica i nomi reali dei renter presenti nelle righe.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    protected function loadOrganizationMap(array $rows): array
    {
        $organizationIds = collect($rows)
            ->pluck('organization_id')
            ->filter(fn ($value) => ! empty($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        if ($organizationIds->isEmpty()) {
            return [];
        }

        return Organization::query()
            ->whereIn('id', $organizationIds->all())
            ->get(['id', 'name', 'legal_name'])
            ->mapWithKeys(function (Organization $organization): array {
                $label = $organization->name ?: $organization->legal_name ?: (string) $organization->id;

                return [$organization->id => $label];
            })
            ->toArray();
    }

    /**
     * Carica i nomi reali dei veicoli presenti nelle righe.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    protected function loadVehicleMap(array $rows): array
    {
        $vehicleIds = collect($rows)
            ->pluck('vehicle_id')
            ->filter(fn ($value) => ! empty($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        if ($vehicleIds->isEmpty()) {
            return [];
        }

        return Vehicle::query()
            ->whereIn('id', $vehicleIds->all())
            ->get(['id', 'plate', 'make', 'model'])
            ->mapWithKeys(function (Vehicle $vehicle): array {
                $label = trim(implode(' ', array_filter([
                    $vehicle->plate,
                    $vehicle->make,
                    $vehicle->model,
                ])));

                return [$vehicle->id => $label !== '' ? $label : (string) $vehicle->id];
            })
            ->toArray();
    }

    /**
     * Restituisce il tipo di vista effettivo del runtime.
     *
     * @param array<int, string> $dimensions
     */
    protected function resolveEffectiveChartType(?string $chartType, array $dimensions): string
    {
        if (! in_array('month', $dimensions, true)) {
            return 'table';
        }

        return $chartType ?: 'table';
    }

    /**
     * Restituisce un messaggio sulla vista effettiva del report.
     *
     * @param array<int, string> $dimensions
     */
    protected function resolveChartTypeInfoMessage(?string $chartType, array $dimensions): string
    {
        if (! in_array('month', $dimensions, true)) {
            return 'Questo report viene mostrato come tabella. Per usare una vista grafica, aggiungi "Mese" tra i raggruppamenti.';
        }

        return match ($chartType ?: 'table') {
            'bar' => 'Questo report è configurato per essere mostrato come grafico a barre.',
            'line' => 'Questo report è configurato per essere mostrato come grafico a linea.',
            default => 'Questo report è configurato per essere mostrato come tabella.',
        };
    }

    /**
     * Prepara i dati del grafico.
     */
    protected function prepareChartData(ReportPreset $runtimePreset): void
    {
        $this->canRenderChart = false;
        $this->chartInfoMessage = null;
        $this->chartData = [];

        $dimensions = $runtimePreset->dimensions ?? [];
        $chartType = $this->resolveEffectiveChartType($runtimePreset->chart_type, $dimensions);

        if ($chartType === 'table') {
            $this->chartInfoMessage = $this->resolveChartTypeInfoMessage($runtimePreset->chart_type, $dimensions);

            return;
        }

        if (empty($this->reportRows)) {
            $this->chartInfoMessage = 'Non ci sono dati da mostrare nel grafico.';

            return;
        }

        $metrics = $runtimePreset->metrics ?? [];

        if (empty($dimensions)) {
            $this->chartInfoMessage = 'Per mostrare un grafico serve almeno un raggruppamento dei dati.';

            return;
        }

        $labelColumn = $this->resolveChartLabelColumn($dimensions);

        if ($labelColumn === null) {
            $this->chartInfoMessage = 'Non è stato possibile determinare l’etichetta del grafico.';

            return;
        }

        if ($chartType === 'line' && ! in_array('month', $dimensions, true)) {
            $this->chartInfoMessage = 'Il grafico a linea è disponibile solo quando il report è raggruppato per mese.';

            return;
        }

        $primaryMetricColumn = $this->resolveChartMetricColumn($metrics);

        if ($primaryMetricColumn === null) {
            $this->chartInfoMessage = 'Questo report non contiene una metrica numerica adatta al grafico.';

            return;
        }

        $secondaryMetricColumn = 'resolved_admin_fee_amount';

        $points = collect($this->reportRows)
            ->map(function (array $row) use ($labelColumn, $primaryMetricColumn, $secondaryMetricColumn): array {
                return [
                    'label' => (string) ($row[$labelColumn] ?? '—'),
                    'primary_value' => (float) ($row[$primaryMetricColumn] ?? 0),
                    'secondary_value' => (float) ($row[$secondaryMetricColumn] ?? 0),
                ];
            })
            ->values()
            ->toArray();

        if (empty($points)) {
            $this->chartInfoMessage = 'Non ci sono dati validi da mostrare nel grafico.';

            return;
        }

        $maxValue = collect($points)
            ->flatMap(function (array $point): array {
                return [
                    (float) ($point['primary_value'] ?? 0),
                    (float) ($point['secondary_value'] ?? 0),
                ];
            })
            ->max();

        $this->chartData = [
            'type' => $chartType,
            'label_column' => $labelColumn,
            'primary_metric_column' => $primaryMetricColumn,
            'primary_metric_label' => $this->getResultColumnLabel($primaryMetricColumn),
            'secondary_metric_column' => $secondaryMetricColumn,
            'secondary_metric_label' => $this->getResultColumnLabel($secondaryMetricColumn),
            'points' => $points,
            'max_value' => max((float) $maxValue, 1),
        ];

        $this->canRenderChart = true;
    }

    /**
     * Determina la metrica principale del grafico.
     *
     * @param array<int, string> $metrics
     */
    protected function resolveChartMetricColumn(array $metrics): ?string
    {
        if (in_array('resolved_commissionable_total', $this->reportColumns, true)) {
            return 'resolved_commissionable_total';
        }

        $allowedPrimaryMetricColumns = [
            'sum_contract_total',
            'sum_admin_fee_amount',
            'avg_admin_fee_percent',
            'sum_paid_total',
            'sum_paid_commissionable',
            'sum_paid_non_commissionable',
            'count_rentals_closed',
            'count_payments',
            'count_unique_rentals_paid',
        ];

        foreach ($metrics as $metric) {
            if (in_array($metric, $allowedPrimaryMetricColumns, true)) {
                return $metric;
            }
        }

        return null;
    }

    /**
     * Determina quale colonna usare come etichetta del grafico.
     *
     * @param array<int, string> $dimensions
     */
    protected function resolveChartLabelColumn(array $dimensions): ?string
    {
        $mapping = [
            'month' => 'month',
            'renter' => 'organization_id',
            'vehicle' => 'vehicle_id',
            'payment_method' => 'payment_method',
            'kind' => 'kind',
            'commissionable_flag' => 'is_commissionable',
        ];

        foreach (['month', 'renter', 'vehicle', 'payment_method', 'kind', 'commissionable_flag'] as $dimension) {
            if (in_array($dimension, $dimensions, true) && isset($mapping[$dimension])) {
                return $mapping[$dimension];
            }
        }

        return null;
    }

    /**
     * Umanizza il metodo di pagamento.
     *
     * @param mixed $value
     */
    protected function humanizePaymentMethod(mixed $value): string
    {
        $value = (string) $value;

        return $this->paymentMethodLabels()[$value]
            ?? ucfirst(str_replace(['_', '-'], ' ', $value));
    }

    /**
     * Umanizza il tipo voce.
     *
     * @param mixed $value
     */
    protected function humanizeKind(mixed $value): string
    {
        $value = (string) $value;

        return $this->kindLabels()[$value]
            ?? ucfirst(str_replace(['_', '-'], ' ', $value));
    }

    /**
     * Umanizza i valori booleani.
     *
     * @param mixed $value
     */
    protected function humanizeBooleanValue(mixed $value): string
    {
        if ($value === true || $value === 1 || $value === '1') {
            return 'Sì';
        }

        if ($value === false || $value === 0 || $value === '0') {
            return 'No';
        }

        return '—';
    }

    /**
     * Restituisce il valore formattato in modo leggibile per la UI.
     *
     * @param mixed $value
     */
    public function formatResultValue(string $column, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($column === 'month') {
            return $this->formatMonthValue((string) $value);
        }

        if (in_array($column, [
            'sum_contract_total',
            'sum_admin_fee_amount',
            'sum_paid_total',
            'sum_paid_commissionable',
            'sum_paid_non_commissionable',
            'resolved_commissionable_total',
            'resolved_admin_fee_amount',
        ], true)) {
            return $this->formatMoneyValue($value);
        }

        if ($column === 'avg_admin_fee_percent') {
            return $this->formatPercentValue($value);
        }

        if (in_array($column, [
            'count_rentals_closed',
            'count_payments',
            'count_unique_rentals_paid',
        ], true)) {
            return number_format((float) $value, 0, ',', '.');
        }

        return (string) $value;
    }

    /**
     * Formatta un importo in euro.
     *
     * @param mixed $value
     */
    protected function formatMoneyValue(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.') . '€';
    }

    /**
     * Formatta una percentuale.
     *
     * @param mixed $value
     */
    protected function formatPercentValue(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.') . '%';
    }

    /**
     * Formatta un mese dal formato YYYY-MM a MM/YYYY.
     */
    protected function formatMonthValue(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            [$year, $month] = explode('-', $value);

            return $month . '/' . $year;
        }

        return $value;
    }

    /**
     * Riordina le colonne risultati.
     *
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    protected function sortReportColumns(array $columns): array
    {
        $preferredOrder = [
            'month',
            'organization_id',
            'vehicle_id',
            'payment_method',
            'kind',
            'is_commissionable',
            'sum_contract_total',
            'sum_paid_total',
            'sum_paid_commissionable',
            'resolved_commissionable_total',
            'resolved_admin_fee_amount',
            'sum_admin_fee_amount',
            'sum_paid_non_commissionable',
            'avg_admin_fee_percent',
            'count_rentals_closed',
            'count_payments',
            'count_unique_rentals_paid',
        ];

        $sorted = [];

        foreach ($preferredOrder as $column) {
            if (in_array($column, $columns, true)) {
                $sorted[] = $column;
            }
        }

        foreach ($columns as $column) {
            if (! in_array($column, $sorted, true)) {
                $sorted[] = $column;
            }
        }

        return $sorted;
    }

    /**
     * Resetta lo stato dell'ultima esecuzione.
     */
    protected function resetExecutionState(): void
    {
        $this->runError = null;
        $this->hasRunReport = false;
        $this->reportRows = [];
        $this->reportColumns = [];
        $this->canRenderChart = false;
        $this->chartInfoMessage = null;
        $this->chartData = [];
    }

    /**
     * Renderizza la view del componente.
     */
    public function render()
    {
        return view('livewire.reports.run-ad-hoc-report', [
            'reportTypeOptions' => $this->reportTypeOptions(),
            'availableMetrics' => $this->availableMetrics(),
            'availableDimensions' => $this->availableDimensions(),
            'availableFilters' => $this->availableFilters(),
            'paymentMethodOptions' => $this->paymentMethodOptions(),
            'kindOptions' => $this->kindOptions(),
        ]);
    }
}