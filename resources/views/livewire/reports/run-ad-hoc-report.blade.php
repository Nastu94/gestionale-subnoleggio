{{-- resources/views/livewire/reports/run-ad-hoc-report.blade.php --}}
<div class="space-y-6">
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Statistica senza salvataggio
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Configura e lancia un report al volo senza salvarlo tra i preset.
            </p>
        </div>

        <form wire:submit="runReport" class="space-y-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="adhoc_report_type" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Tipo di analisi
                    </label>

                    <select
                        id="adhoc_report_type"
                        wire:model.live="report_type"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                            focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                            dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-indigo-400"
                    >
                        <option value="">Seleziona...</option>

                        @foreach ($reportTypeOptions as $reportTypeOption)
                            <option value="{{ $reportTypeOption }}">
                                {{ $this->getReportTypeLabel($reportTypeOption) }}
                            </option>
                        @endforeach
                    </select>

                    @error('report_type')
                        <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                    @enderror

                    @if ($report_type !== '')
                        <div class="mt-2 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:bg-blue-900/20 dark:text-blue-200">
                            {{ $this->getReportTypeDescription($report_type) }}
                        </div>
                    @endif
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Vista del risultato
                    </label>

                    @if ($this->canChooseChartType())
                        <select
                            id="adhoc_chart_type"
                            wire:model.live="chart_type"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                                dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-indigo-400"
                        >
                            <option value="table">Tabella</option>
                            <option value="bar">Grafico a barre</option>
                            <option value="line">Grafico a linea</option>
                        </select>

                        @error('chart_type')
                            <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                        @enderror

                        <div class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                            <div>{{ $this->getChartTypeDescription($chart_type) }}</div>
                            <div class="mt-2">{{ $this->chartTypeHelpMessage() }}</div>
                        </div>
                    @else
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                            Tabella
                        </div>

                        <div class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                            <div>{{ $this->getChartTypeDescription('table') }}</div>
                            <div class="mt-2">{{ $this->chartTypeHelpMessage() }}</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                        Cosa vuoi vedere
                    </div>

                    <div class="space-y-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        @forelse ($availableMetrics as $metric)
                            <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                                <input
                                    type="checkbox"
                                    value="{{ $metric }}"
                                    wire:model.live="metrics"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                >
                                <div>
                                    <div>{{ $this->getMetricLabel($metric) }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $this->getMetricDescription($metric) }}
                                    </div>
                                </div>
                            </label>
                        @empty
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Seleziona prima il tipo di analisi.
                            </div>
                        @endforelse
                    </div>

                    @error('metrics')
                        <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                    @enderror
                    @error('metrics.*')
                        <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                        Come vuoi raggruppare i dati
                    </div>

                    <div class="space-y-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        @forelse ($availableDimensions as $dimension)
                            <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                                <input
                                    type="checkbox"
                                    value="{{ $dimension }}"
                                    wire:model.live="dimensions"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                >
                                <div>
                                    <div>{{ $this->getDimensionLabel($dimension) }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $this->getDimensionDescription($dimension) }}
                                    </div>
                                </div>
                            </label>
                        @empty
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Seleziona prima il tipo di analisi.
                            </div>
                        @endforelse
                    </div>

                    @error('dimensions')
                        <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                    @enderror
                    @error('dimensions.*')
                        <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="space-y-4">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    Filtri del report
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Questi filtri valgono solo per questa esecuzione e non verranno salvati.
                </p>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @if (in_array('organization_id', $availableFilters, true))
                        <div class="relative">
                            <label for="adhoc_organization_search" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $this->getFilterLabel('organization_id') }}
                            </label>

                            <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->getFilterDescription('organization_id') }}
                            </div>

                            <input
                                id="adhoc_organization_search"
                                type="text"
                                wire:model.live.debounce.300ms="organizationSearch"
                                placeholder="Cerca renter per nome, ragione sociale, email o città"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                    focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                                    dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-indigo-400"
                            >

                            @if ($selectedOrganizationLabel && $filters['organization_id'])
                                <div class="mt-2 flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300">
                                    <span>Selezionato: {{ $selectedOrganizationLabel }}</span>

                                    <button
                                        type="button"
                                        wire:click="clearOrganizationSelection"
                                        class="font-medium hover:underline"
                                    >
                                        Rimuovi
                                    </button>
                                </div>
                            @endif

                            @if (! empty($organizationOptions))
                                <div class="mt-2 max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                    @foreach ($organizationOptions as $organizationOption)
                                        <button
                                            type="button"
                                            wire:key="adhoc-organization-option-{{ $organizationOption['id'] }}"
                                            wire:click="selectOrganization({{ $organizationOption['id'] }}, @js($organizationOption['label']))"
                                            class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm text-gray-800 hover:bg-gray-50 last:border-b-0 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-800"
                                        >
                                            {{ $organizationOption['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @error('filters.organization_id')
                                <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    @if (in_array('vehicle_id', $availableFilters, true))
                        <div class="relative">
                            <label for="adhoc_vehicle_search" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $this->getFilterLabel('vehicle_id') }}
                            </label>

                            <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->getFilterDescription('vehicle_id') }}
                            </div>

                            <input
                                id="adhoc_vehicle_search"
                                type="text"
                                wire:model.live.debounce.300ms="vehicleSearch"
                                placeholder="Cerca veicolo per targa, VIN, marca o modello"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                    focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                                    dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-indigo-400"
                            >

                            @if ($selectedVehicleLabel && $filters['vehicle_id'])
                                <div class="mt-2 flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300">
                                    <span>Selezionato: {{ $selectedVehicleLabel }}</span>

                                    <button
                                        type="button"
                                        wire:click="clearVehicleSelection"
                                        class="font-medium hover:underline"
                                    >
                                        Rimuovi
                                    </button>
                                </div>
                            @endif

                            @if (! empty($vehicleOptions))
                                <div class="mt-2 max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                    @foreach ($vehicleOptions as $vehicleOption)
                                        <button
                                            type="button"
                                            wire:key="adhoc-vehicle-option-{{ $vehicleOption['id'] }}"
                                            wire:click="selectVehicle({{ $vehicleOption['id'] }}, @js($vehicleOption['label']))"
                                            class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm text-gray-800 hover:bg-gray-50 last:border-b-0 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-800"
                                        >
                                            {{ $vehicleOption['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @error('filters.vehicle_id')
                                <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                    
                    @if (in_array('rental_id', $availableFilters, true))
                        <div class="relative">
                            <label for="adhoc_rental_search" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $this->getFilterLabel('rental_id') }}
                            </label>

                            <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->getFilterDescription('rental_id') }}
                            </div>

                            <input
                                id="adhoc_rental_search"
                                type="text"
                                wire:model.live.debounce.300ms="rentalSearch"
                                placeholder="Cerca noleggio per numero, cliente o targa"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                    focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                                    dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-indigo-400"
                            >

                            @if ($selectedRentalLabel && $filters['rental_id'])
                                <div class="mt-2 flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300">
                                    <span>Selezionato: {{ $selectedRentalLabel }}</span>

                                    <button
                                        type="button"
                                        wire:click="clearRentalSelection"
                                        class="font-medium hover:underline"
                                    >
                                        Rimuovi
                                    </button>
                                </div>
                            @endif

                            @if (! empty($rentalOptions))
                                <div class="mt-2 max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                    @foreach ($rentalOptions as $rentalOption)
                                        <button
                                            type="button"
                                            wire:key="adhoc-rental-option-{{ $rentalOption['id'] }}"
                                            wire:click="selectRental({{ $rentalOption['id'] }}, @js($rentalOption['label']))"
                                            class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm text-gray-800 hover:bg-gray-50 last:border-b-0 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-800"
                                        >
                                            {{ $rentalOption['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @error('filters.rental_id')
                                <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    @if (in_array('payment_method', $availableFilters, true))
                        <div>
                            <label for="adhoc_payment_method" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $this->getFilterLabel('payment_method') }}
                            </label>

                            <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->getFilterDescription('payment_method') }}
                            </div>

                            <select
                                id="adhoc_payment_method"
                                wire:model.live="filters.payment_method"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                    focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                                    dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-indigo-400"
                            >
                                <option value="">Tutti</option>

                                @foreach ($paymentMethodOptions as $paymentMethodValue => $paymentMethodLabel)
                                    <option value="{{ $paymentMethodValue }}">
                                        {{ $paymentMethodLabel }}
                                    </option>
                                @endforeach
                            </select>

                            @error('filters.payment_method')
                                <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    @if (in_array('kind', $availableFilters, true))
                        <div>
                            <label for="adhoc_kind" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $this->getFilterLabel('kind') }}
                            </label>

                            <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->getFilterDescription('kind') }}
                            </div>

                            <select
                                id="adhoc_kind"
                                wire:model.live="filters.kind"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                    focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                                    dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-indigo-400"
                            >
                                <option value="">Tutti</option>

                                @foreach ($kindOptions as $kindValue => $kindLabel)
                                    <option value="{{ $kindValue }}">
                                        {{ $kindLabel }}
                                    </option>
                                @endforeach
                            </select>

                            @error('filters.kind')
                                <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    @if (in_array('is_commissionable', $availableFilters, true))
                        <div>
                            <label for="adhoc_is_commissionable" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $this->getFilterLabel('is_commissionable') }}
                            </label>

                            <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->getFilterDescription('is_commissionable') }}
                            </div>

                            <select
                                id="adhoc_is_commissionable"
                                wire:model.live="filters.is_commissionable"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                    focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                                    dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-indigo-400"
                            >
                                <option value="">Tutti</option>
                                <option value="1">Solo sì</option>
                                <option value="0">Solo no</option>
                            </select>

                            @error('filters.is_commissionable')
                                <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="adhoc_date_from" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Data iniziale
                    </label>

                    <input
                        id="adhoc_date_from"
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
                    <label for="adhoc_date_to" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Data finale
                    </label>

                    <input
                        id="adhoc_date_to"
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

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                >
                    Lancia statistica
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
            Risultati
        </h2>

        @if ($runError)
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                {{ $runError }}
            </div>
        @endif

        @if ($chartInfoMessage)
            <div class="mt-4 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:bg-blue-900/20 dark:text-blue-200">
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
            <div class="mt-4 rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-600 dark:border-gray-600 dark:text-gray-300">
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
            <div class="mt-4 rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-600 dark:border-gray-600 dark:text-gray-300">
                Configura i parametri sopra e lancia la statistica.
            </div>
        @endif
    </div>
</div>