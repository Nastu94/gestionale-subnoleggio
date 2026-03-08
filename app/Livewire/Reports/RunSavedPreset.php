<?php

namespace App\Livewire\Reports;

use App\Domain\Fees\AdminFeeResolver;
use App\Models\Organization;
use App\Models\Rental;
use App\Models\ReportPreset;
use App\Models\Vehicle;
use App\Services\Reports\ReportRunner;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Componente Livewire per il rilancio di un preset report salvato.
 *
 * Responsabilità:
 * - caricare l'elenco dei preset disponibili;
 * - permettere la selezione di un preset;
 * - mostrare il dettaglio del preset selezionato;
 * - raccogliere le date runtime (non salvate nel preset);
 * - eseguire il report fondendo preset + date runtime;
 * - tradurre etichette tecniche in linguaggio più umano per la UI;
 * - risolvere i nomi reali di renter/veicolo;
 * - aggiungere il totale da commissionare, calcolato col resolver,
 *   solo quando la granularità del report lo consente.
 */
class RunSavedPreset extends Component
{
    /**
     * Preset disponibili per la lista iniziale.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $reportPresets = [];

    /**
     * ID del preset selezionato.
     */
    public ?int $selectedReportPresetId = null;

    /**
     * Dati del preset selezionato.
     *
     * @var array<string, mixed>|null
     */
    public ?array $selectedReportPreset = null;

    /**
     * Data inizio runtime del report.
     */
    public ?string $dateFrom = null;

    /**
     * Data fine runtime del report.
     */
    public ?string $dateTo = null;

    /**
     * Righe risultato del report eseguito.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $reportRows = [];

    /**
     * Intestazioni tabellari derivate dai risultati.
     *
     * @var array<int, string>
     */
    public array $reportColumns = [];

    /**
     * Indica se il grafico può essere mostrato per il report corrente.
     */
    public bool $canRenderChart = false;

    /**
     * Messaggio informativo quando il grafico non è compatibile col report.
     */
    public ?string $chartInfoMessage = null;

    /**
     * Dati pronti per il rendering del grafico.
     *
     * @var array<string, mixed>
     */
    public array $chartData = [];

    /**
     * Messaggio di errore runtime.
     */
    public ?string $runError = null;

    /**
     * Indica se il report è stato eseguito almeno una volta.
     */
    public bool $hasRunReport = false;

    /**
     * Inizializza il componente caricando i preset disponibili.
     */
    public function mount(): void
    {
        $this->loadReportPresets();
    }

    /**
     * Regole di validazione runtime.
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
            'selectedReportPresetId.required' => 'Seleziona un report salvato.',
            'selectedReportPresetId.exists' => 'Il report selezionato non è valido.',
            'dateFrom.required' => 'La data iniziale è obbligatoria.',
            'dateFrom.date' => 'La data iniziale non è valida.',
            'dateTo.required' => 'La data finale è obbligatoria.',
            'dateTo.date' => 'La data finale non è valida.',
            'dateTo.after_or_equal' => 'La data finale non può essere precedente alla data iniziale.',
        ];
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
     * Etichette umane per il tipo di visualizzazione.
     *
     * @return array<string, string>
     */
    public function chartTypeLabels(): array
    {
        return [
            'table' => 'Tabella',
            'bar' => 'Grafico a barre',
            'line' => 'Grafico a linea',
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
            'sum_paid_commissionable' => 'Totale commissionabile',
            'sum_paid_non_commissionable' => 'Totale non commissionabile',
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
            'payment_method' => 'Metodo di pagamento',
            'kind' => 'Tipo di voce',
            'is_commissionable' => 'Commissionabile',
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
     * NB:
     * qui usiamo i valori reali presenti nel model RentalCharge.
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
     * Restituisce l'etichetta umana di un tipo report.
     */
    public function getReportTypeLabel(string $value): string
    {
        return $this->reportTypeLabels()[$value] ?? $value;
    }

    /**
     * Restituisce l'etichetta umana di un tipo visualizzazione.
     */
    public function getChartTypeLabel(?string $value): string
    {
        if (empty($value)) {
            return 'Tabella';
        }

        return $this->chartTypeLabels()[$value] ?? $value;
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
     * Restituisce l'etichetta umana di una colonna risultato.
     */
    public function getResultColumnLabel(string $value): string
    {
        return $this->resultColumnLabels()[$value] ?? $value;
    }

    /**
     * Determina se un preset può usare una vista grafica.
     *
     * Regola prodotto:
     * - se tra i raggruppamenti è presente "month",
     *   il report può usare anche barre o linea;
     * - altrimenti la vista effettiva resta tabella.
     *
     * @param array<int, string> $dimensions
     */
    protected function canUseChartView(array $dimensions): bool
    {
        return in_array('month', $dimensions, true);
    }

    /**
     * Restituisce il tipo di vista effettivo del preset.
     *
     * Se il preset non ha il raggruppamento per mese,
     * la vista effettiva viene forzata a tabella.
     *
     * @param array<int, string> $dimensions
     */
    protected function resolveEffectiveChartType(?string $chartType, array $dimensions): string
    {
        if (! $this->canUseChartView($dimensions)) {
            return 'table';
        }

        return $chartType ?: 'table';
    }

    /**
     * Restituisce un messaggio esplicativo sulla vista effettiva del report.
     *
     * @param array<int, string> $dimensions
     */
    protected function resolveChartTypeInfoMessage(?string $chartType, array $dimensions): string
    {
        if (! $this->canUseChartView($dimensions)) {
            return 'Questo report viene mostrato come tabella. Per usare una vista grafica, aggiungi "Mese" tra i raggruppamenti.';
        }

        return match ($chartType ?: 'table') {
            'bar' => 'Questo report è configurato per essere mostrato come grafico a barre.',
            'line' => 'Questo report è configurato per essere mostrato come grafico a linea.',
            default => 'Questo report è configurato per essere mostrato come tabella.',
        };
    }

    /**
     * Carica l'elenco dei preset report.
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
                'created_by',
                'created_at',
            ])
            ->map(function (ReportPreset $reportPreset): array {
                return [
                    'id' => $reportPreset->id,
                    'name' => $reportPreset->name,
                    'description' => $reportPreset->description,
                    'report_type' => $reportPreset->report_type,
                    'report_type_label' => $this->getReportTypeLabel($reportPreset->report_type),
                    'chart_type' => $reportPreset->chart_type,
                    'chart_type_label' => $this->getChartTypeLabel($reportPreset->chart_type),
                    'created_by' => $reportPreset->created_by,
                    'created_at' => $reportPreset->created_at?->toDateTimeString(),
                ];
            })
            ->toArray();
    }

    /**
     * Seleziona un preset e ne carica i dettagli principali.
     */
    public function selectReportPreset(int $reportPresetId): void
    {
        $reportPreset = ReportPreset::query()->findOrFail($reportPresetId);

        $filters = $reportPreset->filters ?? [];

        /**
         * Le date non fanno più parte del preset salvato:
         * rimuoviamo esplicitamente eventuali valori legacy.
         */
        unset($filters['date_from'], $filters['date_to']);

        $this->selectedReportPresetId = $reportPreset->id;

        $dimensions = $reportPreset->dimensions ?? [];
        $effectiveChartType = $this->resolveEffectiveChartType($reportPreset->chart_type, $dimensions);

        $this->selectedReportPreset = [
            'id' => $reportPreset->id,
            'name' => $reportPreset->name,
            'description' => $reportPreset->description,
            'report_type' => $reportPreset->report_type,
            'report_type_label' => $this->getReportTypeLabel($reportPreset->report_type),
            'metrics' => $reportPreset->metrics,
            'metrics_labels' => collect($reportPreset->metrics ?? [])
                ->map(fn (string $metric): string => $this->getMetricLabel($metric))
                ->values()
                ->toArray(),
            'dimensions' => $dimensions,
            'dimensions_labels' => collect($dimensions)
                ->map(fn (string $dimension): string => $this->getDimensionLabel($dimension))
                ->values()
                ->toArray(),
            'filters' => $filters,
            'filters_labels' => collect($filters)
                ->mapWithKeys(function ($value, $key): array {
                    return [
                        $key => [
                            'label' => $this->getFilterLabel((string) $key),
                            'value' => $this->humanizeFilterValue((string) $key, $value),
                        ],
                    ];
                })
                ->toArray(),
            'chart_type' => $effectiveChartType,
            'chart_type_label' => $this->getChartTypeLabel($effectiveChartType),
            'chart_type_info_message' => $this->resolveChartTypeInfoMessage($reportPreset->chart_type, $dimensions),
            'can_use_chart_view' => $this->canUseChartView($dimensions),
        ];

        /**
         * Reset stato esecuzione precedente quando cambio preset.
         */
        $this->resetRunState();
    }

    /**
     * Esegue il report usando il preset selezionato e le date runtime.
     */
    public function runReport(ReportRunner $reportRunner, AdminFeeResolver $adminFeeResolver): void
    {
        $this->validate();

        $this->runError = null;
        $this->hasRunReport = false;
        $this->reportRows = [];
        $this->reportColumns = [];

        $reportPreset = ReportPreset::query()->findOrFail($this->selectedReportPresetId);

        /**
         * Rimuove eventuali date legacy dal preset e applica le date runtime.
         */
        $filters = $reportPreset->filters ?? [];
        unset($filters['date_from'], $filters['date_to']);

        $filters['date_from'] = $this->dateFrom;
        $filters['date_to'] = $this->dateTo;

        /**
         * Cloniamo il preset in memoria senza alterare il record salvato.
         */
        $runtimePreset = $reportPreset->replicate();
        $runtimePreset->filters = $filters;

        try {
            $rows = $reportRunner->run($runtimePreset);

            $rawRows = collect($rows)
                ->map(function ($row): array {
                    return (array) $row;
                })
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
        } catch (InvalidArgumentException $exception) {
            $this->runError = $exception->getMessage();
        }
    }

    /**
     * Arricchisce le righe risultato con:
     * - nomi reali di renter e veicoli;
     * - etichette umane per payment_method/kind/commissionabile;
     * - totale da commissionare, quando il report lo consente.
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

        /**
         * Il totale da commissionare tramite resolver ha senso solo sui report
         * basati sulla chiusura del noleggio.
         */
        $commissionableTotalsByGroup = $this->shouldAppendResolvedCommissionableTotal($runtimePreset)
            ? $this->buildResolvedCommissionableTotalsByGroup($runtimePreset, $adminFeeResolver)
            : [];

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

                if (! empty($commissionableTotalsByGroup)) {
                    $resolvedGroupTotals = $commissionableTotalsByGroup[$groupKey] ?? [
                        'resolved_commissionable_total' => 0.0,
                        'resolved_admin_fee_amount' => 0.0,
                    ];

                    $row['resolved_commissionable_total'] = $resolvedGroupTotals['resolved_commissionable_total'] ?? 0.0;
                    $row['resolved_admin_fee_amount'] = $resolvedGroupTotals['resolved_admin_fee_amount'] ?? 0.0;
                }

                return $row;
            })
            ->values()
            ->toArray();
    }

    /**
     * Determina se il report può mostrare il totale da commissionare
     * calcolato tramite resolver.
     */
    protected function shouldAppendResolvedCommissionableTotal(ReportPreset $runtimePreset): bool
    {
        return true;
    }

    /**
     * Costruisce una mappa aggregata per gruppo usando il resolver:
     * - totale da commissionare
     * - commissione admin
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
            ]);

        /**
         * Applichiamo comunque il range date al mondo del noleggio,
         * usando la chiusura come riferimento coerente col resolver.
         */
        $rentals->where('status', 'closed')
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
            /**
             * Per il calcolo fee usiamo una data coerente col noleggio chiuso:
             * - actual_return_at se presente;
             * - altrimenti closed_at.
             *
             * Evitiamo così il fallback implicito a "now()",
             * che può produrre commissioni errate o nulle.
             */
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
     * Costruisce una chiave stabile per raggruppare le righe.
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

        /**
         * Nessuna dimensione selezionata: gruppo unico.
         */
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

                return [
                    $organization->id => $label,
                ];
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

                return [
                    $vehicle->id => $label !== '' ? $label : (string) $vehicle->id,
                ];
            })
            ->toArray();
    }

    /**
     * Umanizza il valore di un filtro salvato nel preset.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function humanizeFilterValue(string $key, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($key) {
            'organization_id' => $this->resolveOrganizationLabel((int) $value),
            'vehicle_id' => $this->resolveVehicleLabel((int) $value),
            'payment_method' => $this->humanizePaymentMethod((string) $value),
            'kind' => $this->humanizeKind((string) $value),
            'is_commissionable' => $this->humanizeBooleanValue($value),
            default => $value,
        };
    }

    /**
     * Restituisce il nome umano di un renter.
     */
    protected function resolveOrganizationLabel(int $organizationId): string
    {
        $organization = Organization::query()
            ->find($organizationId, ['id', 'name', 'legal_name']);

        if (! $organization) {
            return (string) $organizationId;
        }

        return $organization->name ?: $organization->legal_name ?: (string) $organizationId;
    }

    /**
     * Restituisce il nome umano di un veicolo.
     */
    protected function resolveVehicleLabel(int $vehicleId): string
    {
        $vehicle = Vehicle::query()
            ->find($vehicleId, ['id', 'plate', 'make', 'model']);

        if (! $vehicle) {
            return (string) $vehicleId;
        }

        $label = trim(implode(' ', array_filter([
            $vehicle->plate,
            $vehicle->make,
            $vehicle->model,
        ])));

        return $label !== '' ? $label : (string) $vehicleId;
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
     * Restituisce il valore formattato in modo leggibile per la UI,
     * in base alla colonna del report.
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
     * Prepara i dati del grafico in base al preset runtime e ai risultati ottenuti.
     *
     * Regole:
     * - table: nessun grafico richiesto;
     * - bar: serve almeno una dimensione e una metrica numerica;
     * - line: serve almeno la dimensione "month".
     *
     * Il grafico mostra sempre due serie:
     * - totale principale del report
     * - commissione admin
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

        /**
         * Serie principale del grafico.
         */
        $primaryMetricColumn = $this->resolveChartMetricColumn($metrics);

        if ($primaryMetricColumn === null) {
            $this->chartInfoMessage = 'Questo report non contiene una metrica numerica adatta al grafico.';

            return;
        }

        /**
         * Serie secondaria fissa: commissione admin.
         * Deve essere disponibile in tutte le righe arricchite.
         */
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
     * Regola:
     * - se è disponibile il totale da commissionare calcolato tramite resolver,
     *   usiamo sempre quello come serie principale;
     * - altrimenti ripieghiamo sulla prima metrica numerica compatibile.
     *
     * @param array<int, string> $metrics
     */
    protected function resolveChartMetricColumn(array $metrics): ?string
    {
        /**
         * Se il totale da commissionare è presente nelle colonne arricchite,
         * deve avere priorità assoluta nel grafico.
         *
         * Questo consente di confrontare sempre:
         * - totale da commissionare
         * - commissione admin
         */
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
     * Priorità:
     * - month
     * - organization_id
     * - vehicle_id
     * - payment_method
     * - kind
     * - is_commissionable
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
     * Riordina le colonne della tabella risultati in un ordine più leggibile.
     *
     * Ordine logico:
     * - contesto / raggruppamenti;
     * - valori economici principali;
     * - valori secondari / di supporto.
     *
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    protected function sortReportColumns(array $columns): array
    {
        $preferredOrder = [
            /**
             * Contesto / raggruppamenti
             */
            'month',
            'organization_id',
            'vehicle_id',
            'payment_method',
            'kind',
            'is_commissionable',

            /**
             * Valori economici principali
             */
            'sum_contract_total',
            'sum_paid_total',
            'sum_paid_commissionable',
            'resolved_commissionable_total',
            'resolved_admin_fee_amount',
            'sum_admin_fee_amount',

            /**
             * Valori secondari
             */
            'sum_paid_non_commissionable',
            'avg_admin_fee_percent',
            'count_rentals_closed',
            'count_payments',
            'count_unique_rentals_paid',
        ];

        $sorted = [];

        /**
         * Inserisce prima le colonne note nell'ordine desiderato.
         */
        foreach ($preferredOrder as $column) {
            if (in_array($column, $columns, true)) {
                $sorted[] = $column;
            }
        }

        /**
         * Aggiunge in coda eventuali colonne non previste,
         * così non perdiamo nulla se in futuro ne comparirà una nuova.
         */
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
    protected function resetRunState(): void
    {
        $this->resetValidation();

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
        return view('livewire.reports.run-saved-preset');
    }
}