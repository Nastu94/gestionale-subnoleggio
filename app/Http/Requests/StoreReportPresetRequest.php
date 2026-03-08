<?php

namespace App\Http\Requests\Admin;

use App\Services\Reports\ReportRunner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request per la creazione di un preset report.
 *
 * Responsabilità:
 * - validare i campi base del preset;
 * - validare la compatibilità tra report_type, metriche, dimensioni e filtri;
 * - impedire l'inserimento di chiavi non whitelisted;
 * - escludere dal salvataggio i filtri runtime (date_from/date_to).
 */
class StoreReportPresetRequest extends FormRequest
{
    /**
     * Determina se l'utente è autorizzato ad eseguire la request.
     *
     * Nota:
     * la route è già protetta dal middleware "can:manage.renters",
     * quindi qui restituiamo true per non duplicare la logica.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regole di validazione base.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var array<string, array<string, mixed>> $definitions */
        $definitions = app(ReportRunner::class)->definitions();

        return [
            /**
             * Metadati preset.
             */
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'report_type' => [
                'required',
                'string',
                Rule::in(array_keys($definitions)),
            ],

            'chart_type' => [
                'nullable',
                'string',
                Rule::in(['table', 'bar', 'line']),
            ],

            /**
             * Configurazione report.
             */
            'metrics' => [
                'required',
                'array',
                'min:1',
            ],

            'metrics.*' => [
                'required',
                'string',
            ],

            'dimensions' => [
                'nullable',
                'array',
                'max:2',
            ],

            'dimensions.*' => [
                'required',
                'string',
            ],

            'filters' => [
                'required',
                'array',
            ],

            /**
             * Filtri strutturali supportati nel payload.
             *
             * Nota:
             * date_from e date_to NON fanno più parte del preset salvato,
             * quindi non vengono validate qui.
             */
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
                'max:50',
            ],

            'filters.kind' => [
                'nullable',
                'string',
                'max:50',
            ],

            'filters.is_commissionable' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Validazione avanzata basata sul dizionario del ReportRunner.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Services\Reports\ReportRunner $reportRunner */
            $reportRunner = app(ReportRunner::class);

            /** @var array<string, array<string, mixed>> $definitions */
            $definitions = $reportRunner->definitions();

            $reportType = (string) $this->input('report_type');
            $metrics = array_values($this->input('metrics', []));
            $dimensions = array_values($this->input('dimensions', []));
            $filters = $this->sanitizeFilters($this->input('filters', []));

            /**
             * Se il report_type non è valido, le altre verifiche non servono.
             */
            if (! array_key_exists($reportType, $definitions)) {
                return;
            }

            $allowedMetrics = $definitions[$reportType]['allowed_metrics'] ?? [];
            $allowedDimensions = $definitions[$reportType]['allowed_dimensions'] ?? [];
            $allowedFilters = $definitions[$reportType]['allowed_filters'] ?? [];

            /**
             * Rimuove i filtri runtime non persistiti dal confronto whitelist.
             */
            $allowedFilters = array_values(array_diff($allowedFilters, ['date_from', 'date_to']));

            /**
             * Verifica metriche consentite.
             */
            foreach ($metrics as $metric) {
                if (! in_array($metric, $allowedMetrics, true)) {
                    $validator->errors()->add(
                        'metrics',
                        "La metrica '{$metric}' non è consentita per il report type '{$reportType}'."
                    );
                }
            }

            /**
             * Verifica dimensioni consentite.
             */
            foreach ($dimensions as $dimension) {
                if (! in_array($dimension, $allowedDimensions, true)) {
                    $validator->errors()->add(
                        'dimensions',
                        "La dimensione '{$dimension}' non è consentita per il report type '{$reportType}'."
                    );
                }
            }

            /**
             * Verifica filtri consentiti.
             *
             * Importante:
             * controlliamo solo le chiavi presenti nel payload,
             * al netto dei filtri runtime non persistiti.
             */
            foreach (array_keys($filters) as $filterKey) {
                if (! in_array($filterKey, $allowedFilters, true)) {
                    $validator->errors()->add(
                        'filters',
                        "Il filtro '{$filterKey}' non è consentito per il report type '{$reportType}'."
                    );
                }
            }
        });
    }

    /**
     * Restituisce i dati validati e normalizzati per il salvataggio del preset.
     *
     * @return array<string, mixed>
     */
    public function presetData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'description' => $this->filled('description')
                ? $this->string('description')->toString()
                : null,
            'report_type' => $this->string('report_type')->toString(),
            'metrics' => array_values($this->input('metrics', [])),
            'dimensions' => array_values($this->input('dimensions', [])),
            'filters' => $this->sanitizeFilters($this->input('filters', [])),
            'chart_type' => $this->filled('chart_type')
                ? $this->string('chart_type')->toString()
                : null,
        ];
    }

    /**
     * Rimuove dai filtri i valori runtime che non devono essere persistiti.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    protected function sanitizeFilters(array $filters): array
    {
        unset($filters['date_from'], $filters['date_to']);

        return $filters;
    }
}