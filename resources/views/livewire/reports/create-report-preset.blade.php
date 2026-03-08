<div class="space-y-6">
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Crea nuovo report salvato
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Imposta un modello di report da riutilizzare in futuro. Il periodo non viene salvato:
                lo sceglierai ogni volta quando lancerai il report.
            </p>
        </div>

        @if ($successMessage)
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300">
                {{ $successMessage }}
            </div>
        @endif

        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="preset_name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Nome del report
                    </label>

                    <input
                        id="preset_name"
                        type="text"
                        wire:model.live="name"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                            focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                            dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-indigo-400"
                    >

                    @error('name')
                        <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="report_type" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Tipo di analisi
                    </label>

                    <select
                        id="report_type"
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
            </div>

            <div>
                <label for="description" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Descrizione
                </label>

                <textarea
                    id="description"
                    rows="3"
                    wire:model.live="description"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                        focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                        dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-indigo-400"
                    placeholder="Per esempio: report mensile delle commissioni per renter"
                ></textarea>

                @error('description')
                    <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Vista predefinita
                </label>

                @if ($this->canChooseChartType())
                    <select
                        id="chart_type"
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
                    Filtri fissi del report
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Questi filtri resteranno salvati nel report. Il periodo invece verrà scelto ogni volta al momento del lancio.
                </p>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @if (in_array('organization_id', $availableFilters, true))
                        <div class="relative">
                            <label for="organization_search" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $this->getFilterLabel('organization_id') }}
                            </label>

                            <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->getFilterDescription('organization_id') }}
                            </div>

                            <input
                                id="organization_search"
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
                                            wire:key="organization-option-{{ $organizationOption['id'] }}"
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
                            <label for="vehicle_search" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $this->getFilterLabel('vehicle_id') }}
                            </label>

                            <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->getFilterDescription('vehicle_id') }}
                            </div>

                            <input
                                id="vehicle_search"
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
                                            wire:key="vehicle-option-{{ $vehicleOption['id'] }}"
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

                    @if (in_array('payment_method', $availableFilters, true))
                        <div>
                            <label for="payment_method" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $this->getFilterLabel('payment_method') }}
                            </label>
                            
                            <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->getFilterDescription('payment_method') }}
                            </div>

                            <select
                                id="payment_method"
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
                            <label for="kind" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $this->getFilterLabel('kind') }}
                            </label>

                            <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->getFilterDescription('kind') }}
                            </div>

                            <select
                                id="kind"
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
                            <label for="is_commissionable" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $this->getFilterLabel('is_commissionable') }}
                            </label>

                            <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->getFilterDescription('is_commissionable') }}
                            </div>

                            <select
                                id="is_commissionable"
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

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    class="rounded px-3 py-1 ring-1 ring-slate-300 bg-slate-800 text-white"
                >
                    Salva report
                </button>
            </div>
        </form>
    </div>
</div>