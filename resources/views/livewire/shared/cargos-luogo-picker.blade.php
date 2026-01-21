{{-- resources/views/livewire/shared/cargos-luogo-picker.blade.php --}}
<div class="space-y-4">
    {{-- CSS dropdown sotto al campo --}}
    <style>
        .cargos-dropdown{
            position: absolute;
            left: 0; right: 0;
            top: calc(100% + 6px);
            z-index: 60;
            background: white;
            border: 1px solid rgba(0,0,0,.12);
            border-radius: .5rem;
            box-shadow: 0 10px 20px rgba(0,0,0,.08);
            max-height: 12rem;
            overflow-y: auto;
            overscroll-behavior: contain;
        }
        .cargos-dropdown li{ padding: .5rem .75rem; cursor: pointer; }
        .cargos-dropdown li:hover{ background: rgba(0,0,0,.05); }
        .cargos-muted{ color: rgba(0,0,0,.55); font-size: .75rem; }
        .dark .cargos-dropdown{ background:#111827; border-color:#374151; }
        .dark .cargos-dropdown li:hover{ background:#1f2937; }
        .dark .cargos-muted{ color:#9ca3af; }
    </style>

    <h3 class="text-sm font-semibold mb-1 text-gray-900 dark:text-gray-100">{{ $title }}</h3>
    @if(!empty($hint))
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ $hint }}</p>
    @endif

    {{-- NAZIONE --}}
    <div class="relative mb-4">
        <label class="text-xs text-gray-600 dark:text-gray-300">Nazione</label>

        <input
            wire:model.live.debounce.300ms="state.country_search"
            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                   text-gray-900 dark:text-gray-100 w-full"
            placeholder="Es. Italia, Francia, Germania">

        @if(!empty($countryResults))
            <ul class="cargos-dropdown">
                @foreach($countryResults as $c)
                    <li
                        wire:click="selectCountry(
                            {{ (int) $c['code'] }},
                            '{{ addslashes($c['name']) }}',
                            {{ array_key_exists('country_code', $c) && $c['country_code'] !== null ? ("'".addslashes($c['country_code'])."'") : 'null' }},
                            {{ !empty($c['is_italian']) ? 'true' : 'false' }}
                        )"
                    >
                        {{ $c['name'] }}
                        @if(empty($c['is_italian']))
                            <span class="cargos-muted"> — estero</span>
                        @endif
                        @if(!empty($c['is_italian']))
                            <span class="cargos-muted"> — Italia</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- SOLO ITALIA --}}
    @if(($state['is_italian'] ?? false) === true && $mode === 'full')
        <div class="grid md:grid-cols-2 gap-4">
            {{-- PROVINCIA --}}
            <div class="relative">
                <label class="text-xs text-gray-600 dark:text-gray-300">Provincia</label>

                <input
                    wire:model.live.debounce.300ms="state.province_search"
                    class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                           text-gray-900 dark:text-gray-100 w-full">

                @if(!empty($provinceResults))
                    <ul class="cargos-dropdown">
                        @foreach($provinceResults as $prov)
                            <li wire:click="selectProvince('{{ $prov }}')">{{ $prov }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- COMUNE --}}
            <div class="relative">
                <label class="text-xs text-gray-600 dark:text-gray-300">Comune</label>

                <input
                    wire:model.live.debounce.300ms="state.city_search"
                    class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                           text-gray-900 dark:text-gray-100 w-full"
                    @disabled(!($state['province'] ?? null))>

                @if(!empty($cityResults))
                    <ul class="cargos-dropdown">
                        @foreach($cityResults as $c)
                            <li wire:click="selectCity({{ (int) $c['code'] }}, '{{ addslashes($c['name']) }}')">
                                {{ $c['name'] }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
</div>
