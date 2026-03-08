{{-- resources/views/livewire/reports/run-saved-preset.blade.php --}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
    <div class="lg:col-span-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Report salvati
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Seleziona un report già salvato per vedere come è configurato e lanciarlo sul periodo che ti serve.
                </p>
            </div>

            <div class="space-y-3">
                @forelse ($reportPresets as $reportPreset)
                    <button
                        type="button"
                        wire:key="saved-preset-{{ $reportPreset['id'] }}"
                        wire:click="selectReportPreset({{ $reportPreset['id'] }})"
                        class="w-full rounded-lg border px-4 py-3 text-left transition
                            {{ $selectedReportPresetId === $reportPreset['id']
                                ? 'border-indigo-500 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-900/20'
                                : 'border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700/50' }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $reportPreset['name'] }}
                                </div>

                                @if (! empty($reportPreset['description']))
                                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $reportPreset['description'] }}
                                    </div>
                                @endif

                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $reportPreset['report_type_label'] }}
                                </div>
                            </div>

                            <span class="shrink-0 rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                {{ $reportPreset['chart_type_label'] }}
                            </span>
                        </div>
                    </button>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-600 dark:border-gray-600 dark:text-gray-300">
                        Non ci sono ancora report salvati.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="lg:col-span-8">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Dettaglio report
            </h2>

            @if ($selectedReportPreset)
                <div class="mt-4 space-y-4">
                    <div>
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Nome</div>
                        <div class="text-sm text-gray-900 dark:text-white">{{ $selectedReportPreset['name'] }}</div>
                    </div>

                    <div>
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Descrizione</div>
                        <div class="text-sm text-gray-900 dark:text-white">
                            {{ $selectedReportPreset['description'] ?: '—' }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Tipo di analisi</div>
                            <div class="text-sm text-gray-900 dark:text-white">
                                {{ $selectedReportPreset['report_type_label'] }}
                            </div>
                        </div>

                        <div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Vista del risultato</div>
                            <div class="text-sm text-gray-900 dark:text-white">
                                {{ $selectedReportPreset['chart_type_label'] }}
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                        {{ $selectedReportPreset['chart_type_info_message'] }}
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="date_from" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                Data iniziale
                            </label>

                            <input
                                id="date_from"
                                type="date"
                                wire:model.live="dateFrom"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                    focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                                    dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-indigo-400"
                            >

                            @error('dateFrom')
                                <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label for="date_to" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                Data finale
                            </label>

                            <input
                                id="date_to"
                                type="date"
                                wire:model.live="dateTo"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                    focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                                    dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-indigo-400"
                            >

                            @error('dateTo')
                                <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    @error('selectedReportPresetId')
                        <div class="text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                    @enderror

                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            wire:click="runReport"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                        >
                            Lancia report
                        </button>
                    </div>

                    @if ($runError)
                        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                            {{ $runError }}
                        </div>
                    @endif

                    <div>
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Dati mostrati nel report</div>

                        @if (! empty($selectedReportPreset['metrics_labels']))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($selectedReportPreset['metrics_labels'] as $metricLabel)
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                        {{ $metricLabel }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">—</div>
                        @endif
                    </div>

                    <div>
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Raggruppamenti</div>

                        @if (! empty($selectedReportPreset['dimensions_labels']))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($selectedReportPreset['dimensions_labels'] as $dimensionLabel)
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                        {{ $dimensionLabel }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Nessun raggruppamento impostato.
                            </div>
                        @endif
                    </div>

                    <div>
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Filtri fissi del report</div>

                        @if (! empty($selectedReportPreset['filters_labels']))
                            <div class="mt-2 space-y-2">
                                @foreach ($selectedReportPreset['filters_labels'] as $filterData)
                                    <div class="flex items-start justify-between gap-4 rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-900">
                                        <span class="font-medium text-gray-700 dark:text-gray-200">
                                            {{ $filterData['label'] }}
                                        </span>

                                        <span class="text-right text-gray-900 dark:text-white">
                                            {{ $filterData['value'] }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Nessun filtro fisso impostato.
                            </div>
                        @endif
                    </div>

                    <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            Risultati
                        </h3>

                        @if ($chartInfoMessage)
                            <div class="mt-3 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:bg-blue-900/20 dark:text-blue-200">
                                {{ $chartInfoMessage }}
                            </div>
                        @endif

                        @if ($canRenderChart && ! empty($chartData['points']))
                            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                <div class="mb-3 flex flex-wrap items-center gap-4 text-sm">
                                    <div class="font-medium text-gray-700 dark:text-gray-200">
                                        Confronto tra:
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-3 w-3 rounded-full bg-indigo-600"></span>
                                        <span class="text-gray-700 dark:text-gray-200">
                                            {{ $chartData['primary_metric_label'] }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-3 w-3 rounded-full bg-emerald-500"></span>
                                        <span class="text-gray-700 dark:text-gray-200">
                                            {{ $chartData['secondary_metric_label'] }}
                                        </span>
                                    </div>
                                </div>

                                @if (($chartData['type'] ?? 'table') === 'bar')
                                    <div class="space-y-4">
                                        @foreach ($chartData['points'] as $point)
                                            @php
                                                $primaryPercent = $chartData['max_value'] > 0
                                                    ? (($point['primary_value'] / $chartData['max_value']) * 100)
                                                    : 0;

                                                $secondaryPercent = $chartData['max_value'] > 0
                                                    ? (($point['secondary_value'] / $chartData['max_value']) * 100)
                                                    : 0;
                                            @endphp

                                            <div>
                                                <div class="mb-2 text-sm font-medium text-gray-800 dark:text-gray-100">
                                                    {{ $point['label'] }}
                                                </div>

                                                <div class="space-y-2">
                                                    <div>
                                                        <div class="mb-1 flex items-center justify-between gap-4 text-xs">
                                                            <span class="text-gray-600 dark:text-gray-300">
                                                                {{ $chartData['primary_metric_label'] }}
                                                            </span>
                                                            <span class="font-medium text-gray-900 dark:text-white">
                                                                {{ $this->formatResultValue($chartData['primary_metric_column'], $point['primary_value']) }}
                                                            </span>
                                                        </div>

                                                        <div class="h-3 rounded-full bg-gray-200 dark:bg-gray-700">
                                                            <div
                                                                class="h-3 rounded-full bg-indigo-600"
                                                                style="width: {{ max($primaryPercent, 1) }}%;"
                                                            ></div>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <div class="mb-1 flex items-center justify-between gap-4 text-xs">
                                                            <span class="text-gray-600 dark:text-gray-300">
                                                                {{ $chartData['secondary_metric_label'] }}
                                                            </span>
                                                            <span class="font-medium text-gray-900 dark:text-white">
                                                                {{ $this->formatResultValue($chartData['secondary_metric_column'], $point['secondary_value']) }}
                                                            </span>
                                                        </div>

                                                        <div class="h-3 rounded-full bg-gray-200 dark:bg-gray-700">
                                                            <div
                                                                class="h-3 rounded-full bg-emerald-500"
                                                                style="width: {{ max($secondaryPercent, 1) }}%;"
                                                            ></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif (($chartData['type'] ?? 'table') === 'line')
                                    <div class="space-y-4">
                                        <div class="overflow-x-auto pb-2">
                                            <div class="flex min-w-max items-end gap-6">
                                                @foreach ($chartData['points'] as $point)
                                                    @php
                                                        $primaryPercent = $chartData['max_value'] > 0
                                                            ? (($point['primary_value'] / $chartData['max_value']) * 100)
                                                            : 0;

                                                        $secondaryPercent = $chartData['max_value'] > 0
                                                            ? (($point['secondary_value'] / $chartData['max_value']) * 100)
                                                            : 0;
                                                    @endphp

                                                    <div class="flex w-[120px] flex-col items-center">
                                                        <div class="mb-2 text-center text-xs text-gray-600 dark:text-gray-300">
                                                            {{ $point['label'] }}
                                                        </div>

                                                        <div class="flex h-56 w-full items-end justify-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 dark:border-gray-700 dark:bg-gray-800">
                                                            <div class="flex h-full w-10 flex-col justify-end">
                                                                <div class="mb-1 text-center text-[11px] font-medium text-gray-900 dark:text-white">
                                                                    {{ $this->formatResultValue($chartData['primary_metric_column'], $point['primary_value']) }}
                                                                </div>

                                                                <div class="flex-1 flex items-end">
                                                                    <div
                                                                        class="w-full rounded-t-lg bg-indigo-600"
                                                                        style="height: {{ max($primaryPercent, 1) }}%;"
                                                                    ></div>
                                                                </div>
                                                            </div>

                                                            <div class="flex h-full w-10 flex-col justify-end">
                                                                <div class="mb-1 text-center text-[11px] font-medium text-gray-900 dark:text-white">
                                                                    {{ $this->formatResultValue($chartData['secondary_metric_column'], $point['secondary_value']) }}
                                                                </div>

                                                                <div class="flex-1 flex items-end">
                                                                    <div
                                                                        class="w-full rounded-t-lg bg-emerald-500"
                                                                        style="height: {{ max($secondaryPercent, 1) }}%;"
                                                                    ></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Vista semplificata del confronto tra totale da commissionare e commissione admin.
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if ($hasRunReport && empty($reportRows))
                            <div class="mt-3 rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-600 dark:border-gray-600 dark:text-gray-300">
                                Nessun risultato trovato per il periodo e i filtri selezionati.
                            </div>
                        @elseif (! empty($reportRows))
                            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-900">
                                        <tr>
                                            @foreach ($reportColumns as $column)
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                                    {{ $this->getResultColumnLabel($column) }}
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                        @foreach ($reportRows as $row)
                                            <tr>
                                                @foreach ($reportColumns as $column)
                                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                        @php
                                                            $value = $row[$column] ?? null;
                                                        @endphp

                                                        {{ $this->formatResultValue($column, $value) }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="mt-3 rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-600 dark:border-gray-600 dark:text-gray-300">
                                Seleziona il periodo e lancia il report.
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="mt-4 rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-600 dark:border-gray-600 dark:text-gray-300">
                    Seleziona un report salvato dalla colonna di sinistra.
                </div>
            @endif
        </div>
    </div>
</div>