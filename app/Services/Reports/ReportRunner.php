<?php

namespace App\Services\Reports;

use App\Models\ReportPreset;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Servizio responsabile dell'esecuzione dei preset report economici.
 *
 * Regole principali:
 * - Nessuna SQL libera inserita dall'utente.
 * - Solo report_type, metriche, dimensioni e filtri whitelisted.
 * - Ogni report_type decide:
 *   - tabella base
 *   - join necessari
 *   - timestamp di riferimento
 *   - metriche/dimensioni compatibili
 */
class ReportRunner
{
    /**
     * Esegue un preset report e restituisce i risultati.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function run(ReportPreset $preset): Collection
    {
        /**
         * Normalizza i dati del preset.
         */
        $reportType = $preset->report_type;
        $metrics = array_values($preset->metrics ?? []);
        $dimensions = array_values($preset->dimensions ?? []);
        $filters = $preset->filters ?? [];

        /**
         * Valida la configurazione prima di costruire qualsiasi query.
         */
        $this->validatePresetConfiguration(
            reportType: $reportType,
            metrics: $metrics,
            dimensions: $dimensions,
            filters: $filters,
        );

        /**
         * Costruisce la query base in funzione del tipo report.
         */
        $query = $this->makeBaseQuery($reportType);

        /**
         * Applica i filtri.
         */
        $this->applyFilters(
            query: $query,
            reportType: $reportType,
            filters: $filters,
        );

        /**
         * Applica dimensioni (group by / select).
         */
        $this->applyDimensions(
            query: $query,
            reportType: $reportType,
            dimensions: $dimensions,
        );

        /**
         * Applica metriche aggregate.
         */
        $this->applyMetrics(
            query: $query,
            reportType: $reportType,
            metrics: $metrics,
        );

        /**
         * Se sono presenti dimensioni, ordina per dimensioni.
         * Altrimenti restituisce la singola riga aggregata.
         */
        $this->applyDefaultOrdering(
            query: $query,
            dimensions: $dimensions,
        );

        return $query->get();
    }

    /**
     * Restituisce il dizionario completo dei report supportati.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'commissions_by_closure' => [
                'allowed_metrics' => [
                    'sum_contract_total',
                    'sum_admin_fee_amount',
                    'avg_admin_fee_percent',
                    'count_rentals_closed',
                ],
                'allowed_dimensions' => [
                    'month',
                    'renter',
                    'vehicle',
                    'rental',
                ],
                'allowed_filters' => [
                    'date_from',
                    'date_to',
                    'organization_id',
                    'vehicle_id',
                ],
            ],

            'cash_by_payment_date' => [
                'allowed_metrics' => [
                    'sum_paid_total',
                    'sum_paid_commissionable',
                    'sum_paid_non_commissionable',
                    'count_payments',
                    'count_unique_rentals_paid',
                ],
                'allowed_dimensions' => [
                    'month',
                    'renter',
                    'vehicle',
                    'rental',
                    'payment_method',
                    'kind',
                    'commissionable_flag',
                ],
                'allowed_filters' => [
                    'date_from',
                    'date_to',
                    'organization_id',
                    'vehicle_id',
                    'payment_method',
                    'kind',
                    'is_commissionable',
                ],
            ],

            'cash_by_closure_month' => [
                'allowed_metrics' => [
                    'sum_paid_total',
                    'sum_paid_commissionable',
                    'sum_paid_non_commissionable',
                    'count_payments',
                    'count_unique_rentals_paid',
                ],
                'allowed_dimensions' => [
                    'month',
                    'renter',
                    'vehicle',
                    'rental',
                    'payment_method',
                    'kind',
                    'commissionable_flag',
                ],
                'allowed_filters' => [
                    'date_from',
                    'date_to',
                    'organization_id',
                    'vehicle_id',
                    'payment_method',
                    'kind',
                    'is_commissionable',
                ],
            ],
        ];
    }

    /**
     * Valida la configurazione del preset.
     *
     * @param array<int, string> $metrics
     * @param array<int, string> $dimensions
     * @param array<string, mixed> $filters
     */
    protected function validatePresetConfiguration(
        string $reportType,
        array $metrics,
        array $dimensions,
        array $filters
    ): void {
        $definitions = $this->definitions();

        if (! array_key_exists($reportType, $definitions)) {
            throw new InvalidArgumentException("Report type non supportato: {$reportType}");
        }

        if (empty($metrics)) {
            throw new InvalidArgumentException('Il preset deve contenere almeno una metrica.');
        }

        if (count($dimensions) > 2) {
            throw new InvalidArgumentException('Sono consentite al massimo 2 dimensioni.');
        }

        $allowedMetrics = $definitions[$reportType]['allowed_metrics'];
        $allowedDimensions = $definitions[$reportType]['allowed_dimensions'];
        $allowedFilters = $definitions[$reportType]['allowed_filters'];

        foreach ($metrics as $metric) {
            if (! in_array($metric, $allowedMetrics, true)) {
                throw new InvalidArgumentException("Metrica non consentita per {$reportType}: {$metric}");
            }
        }

        foreach ($dimensions as $dimension) {
            if (! in_array($dimension, $allowedDimensions, true)) {
                throw new InvalidArgumentException("Dimensione non consentita per {$reportType}: {$dimension}");
            }
        }

        foreach (array_keys($filters) as $filterKey) {
            if (! in_array($filterKey, $allowedFilters, true)) {
                throw new InvalidArgumentException("Filtro non consentito per {$reportType}: {$filterKey}");
            }
        }

        if (empty($filters['date_from']) || empty($filters['date_to'])) {
            throw new InvalidArgumentException('I filtri date_from e date_to sono obbligatori.');
        }

        if ($filters['date_from'] > $filters['date_to']) {
            throw new InvalidArgumentException('Il filtro date_from non può essere successivo a date_to.');
        }
    }

    /**
     * Costruisce la query base in funzione del tipo report.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function makeBaseQuery(string $reportType)
    {
        return match ($reportType) {
            'commissions_by_closure' => DB::table('rentals')
                ->where('rentals.status', 'closed')
                ->whereNotNull('rentals.closed_at'),

            'cash_by_payment_date' => DB::table('rental_charges')
                ->join('rentals', 'rentals.id', '=', 'rental_charges.rental_id'),

            'cash_by_closure_month' => DB::table('rental_charges')
                ->join('rentals', 'rentals.id', '=', 'rental_charges.rental_id')
                ->where('rentals.status', 'closed')
                ->whereNotNull('rentals.closed_at'),

            default => throw new InvalidArgumentException("Report type non supportato: {$reportType}"),
        };
    }

    /**
     * Applica i filtri previsti al query builder.
     *
     * @param array<string, mixed> $filters
     */
    protected function applyFilters($query, string $reportType, array $filters): void
    {
        /**
         * Individua la colonna data ufficiale in base al report.
         */
        $dateColumn = match ($reportType) {
            'commissions_by_closure' => 'rentals.closed_at',
            'cash_by_payment_date' => 'rental_charges.payment_recorded_at',
            'cash_by_closure_month' => 'rentals.closed_at',
            default => throw new InvalidArgumentException("Report type non supportato: {$reportType}"),
        };

        $query->whereBetween($dateColumn, [
            $filters['date_from'] . ' 00:00:00',
            $filters['date_to'] . ' 23:59:59',
        ]);

        if (array_key_exists('organization_id', $filters) && filled($filters['organization_id'])) {
            $query->where('rentals.organization_id', $filters['organization_id']);
        }

        if (array_key_exists('vehicle_id', $filters) && filled($filters['vehicle_id'])) {
            $query->where('rentals.vehicle_id', $filters['vehicle_id']);
        }

        /**
         * Filtro per singolo noleggio.
         *
         * Usiamo sempre rentals.id perché tutti i report
         * partono da rentals oppure fanno join con rentals.
         */
        if (array_key_exists('rental_id', $filters) && filled($filters['rental_id'])) {
            $query->where('rentals.id', $filters['rental_id']);
        }

        if (
            in_array($reportType, ['cash_by_payment_date', 'cash_by_closure_month'], true)
            && array_key_exists('payment_method', $filters)
            && filled($filters['payment_method'])
        ) {
            $query->where('rental_charges.payment_method', $filters['payment_method']);
        }

        if (
            in_array($reportType, ['cash_by_payment_date', 'cash_by_closure_month'], true)
            && array_key_exists('kind', $filters)
            && filled($filters['kind'])
        ) {
            $query->where('rental_charges.kind', $filters['kind']);
        }

        if (
            in_array($reportType, ['cash_by_payment_date', 'cash_by_closure_month'], true)
            && array_key_exists('is_commissionable', $filters)
            && $filters['is_commissionable'] !== null
            && $filters['is_commissionable'] !== ''
        ) {
            $query->where('rental_charges.is_commissionable', (bool) $filters['is_commissionable']);
        }
    }

    /**
     * Applica le dimensioni richieste.
     *
     * @param array<int, string> $dimensions
     */
    protected function applyDimensions($query, string $reportType, array $dimensions): void
    {
        foreach ($dimensions as $dimension) {
            match ($dimension) {
                'month' => $this->applyMonthDimension($query, $reportType),

                'renter' => $this->applySimpleDimension(
                    query: $query,
                    column: 'rentals.organization_id',
                    alias: 'organization_id'
                ),

                'vehicle' => $this->applySimpleDimension(
                    query: $query,
                    column: 'rentals.vehicle_id',
                    alias: 'vehicle_id'
                ),

                /**
                 * Nuova dimensione:
                 * raggruppa il report per singolo noleggio.
                 */
                'rental' => $this->applySimpleDimension(
                    query: $query,
                    column: 'rentals.id',
                    alias: 'rental_id'
                ),

                'payment_method' => $this->applySimpleDimension(
                    query: $query,
                    column: 'rental_charges.payment_method',
                    alias: 'payment_method'
                ),

                'kind' => $this->applySimpleDimension(
                    query: $query,
                    column: 'rental_charges.kind',
                    alias: 'kind'
                ),

                'commissionable_flag' => $this->applySimpleDimension(
                    query: $query,
                    column: 'rental_charges.is_commissionable',
                    alias: 'is_commissionable'
                ),

                default => throw new InvalidArgumentException("Dimensione non supportata: {$dimension}"),
            };
        }
    }

    /**
     * Applica una dimensione semplice (colonna diretta).
     */
    protected function applySimpleDimension($query, string $column, string $alias): void
    {
        $query->addSelect(DB::raw("{$column} as {$alias}"));
        $query->groupBy($column);
    }

    /**
     * Applica la dimensione "month" usando il timestamp corretto per il report.
     */
    protected function applyMonthDimension($query, string $reportType): void
    {
        $column = match ($reportType) {
            'commissions_by_closure' => 'rentals.closed_at',
            'cash_by_payment_date' => 'rental_charges.payment_recorded_at',
            'cash_by_closure_month' => 'rentals.closed_at',
            default => throw new InvalidArgumentException("Report type non supportato: {$reportType}"),
        };

        /**
         * Formato YYYY-MM, utile per tabelle e grafici mensili.
         */
        $query->addSelect(DB::raw("DATE_FORMAT({$column}, '%Y-%m') as month"));
        $query->groupBy(DB::raw("DATE_FORMAT({$column}, '%Y-%m')"));
    }

    /**
     * Applica le metriche aggregate richieste.
     *
     * @param array<int, string> $metrics
     */
    protected function applyMetrics($query, string $reportType, array $metrics): void
    {
        foreach ($metrics as $metric) {
            match ($metric) {
                'sum_contract_total' => $query->addSelect(DB::raw(
                    'SUM(COALESCE(rentals.final_amount_override, rentals.amount)) as sum_contract_total'
                )),

                'sum_admin_fee_amount' => $query->addSelect(DB::raw(
                    'SUM(rentals.admin_fee_amount) as sum_admin_fee_amount'
                )),

                'avg_admin_fee_percent' => $query->addSelect(DB::raw(
                    'AVG(rentals.admin_fee_percent) as avg_admin_fee_percent'
                )),

                'count_rentals_closed' => $query->addSelect(DB::raw(
                    'COUNT(*) as count_rentals_closed'
                )),

                'sum_paid_total' => $query->addSelect(DB::raw(
                    'SUM(rental_charges.amount) as sum_paid_total'
                )),

                'sum_paid_commissionable' => $query->addSelect(DB::raw(
                    'SUM(CASE WHEN rental_charges.is_commissionable = 1 THEN rental_charges.amount ELSE 0 END) as sum_paid_commissionable'
                )),

                'sum_paid_non_commissionable' => $query->addSelect(DB::raw(
                    'SUM(CASE WHEN rental_charges.is_commissionable = 0 THEN rental_charges.amount ELSE 0 END) as sum_paid_non_commissionable'
                )),

                'count_payments' => $query->addSelect(DB::raw(
                    'COUNT(rental_charges.id) as count_payments'
                )),

                'count_unique_rentals_paid' => $query->addSelect(DB::raw(
                    'COUNT(DISTINCT rental_charges.rental_id) as count_unique_rentals_paid'
                )),

                default => throw new InvalidArgumentException("Metrica non supportata: {$metric}"),
            };
        }
    }

    /**
     * Applica un ordinamento di default leggibile.
     *
     * @param array<int, string> $dimensions
     */
    protected function applyDefaultOrdering($query, array $dimensions): void
    {
        /**
         * Se c'è il mese, ordina prima per mese.
         */
        if (in_array('month', $dimensions, true)) {
            $query->orderBy('month');
        }

        /**
         * Ordina poi le altre dimensioni disponibili.
         */
        if (in_array('renter', $dimensions, true)) {
            $query->orderBy('organization_id');
        }

        if (in_array('vehicle', $dimensions, true)) {
            $query->orderBy('vehicle_id');
        }

        /**
         * Se il report è raggruppato per noleggio,
         * ordiniamo anche per ID del noleggio.
         */
        if (in_array('rental', $dimensions, true)) {
            $query->orderBy('rental_id');
        }

        if (in_array('payment_method', $dimensions, true)) {
            $query->orderBy('payment_method');
        }

        if (in_array('kind', $dimensions, true)) {
            $query->orderBy('kind');
        }

        if (in_array('commissionable_flag', $dimensions, true)) {
            $query->orderBy('is_commissionable');
        }
    }
}