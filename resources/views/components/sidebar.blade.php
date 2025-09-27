{{-- resources/views/components/sidebar.blade.php --}}

<?php
/**
 * Componente Sidebar – Gestionale Divani v3.0
 *
 * - Accordion “una sola sezione aperta” usando `openSection` dal layout
 * - Nessun `x-data` proprio: eredita `isOpen` e `openSection` dal genitore
 * - Fa parte del layout Flex (non è più fixed)
 */
?>
<aside
    x-cloak
    x-show="isOpen"
    x-transition:enter="transition-all duration-200"
    x-transition:leave="transition-all duration-200"
    :class="isOpen ? 'w-48 sm:w-56 md:w-64' : 'w-0'"
    class="overflow-hidden bg-white dark:bg-gray-800 border-r dark:border-gray-700 flex flex-col"
>
    {{-- Spazio in testa (logo, padding) --}}
    <div class="h-16 flex items-center justify-center">
        
    </div>

    {{-- Link rapido alla Dashboard --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        @php $dashboardActive = request()->routeIs('dashboard'); @endphp
        <a
            href="{{ route('dashboard') }}"
            class="block px-8 py-2 text-sm transition-colors font-semibold
                   {{ $dashboardActive
                        ? 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100'
                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700' }}"
        >
            <div class="flex items-center">
                <span class="font-semibold">{{ __('Dashboard') }}</span>
            </div>
        </a>
    </div>

    {{-- Menu a fisarmonica: itero solo le sezioni con almeno una voce accessibile --}}
    @foreach(config('menu.sidebar') as $i => $section)
        @php
            // filtro gli items cui l'utente ha effettivo accesso
            $accessible = collect($section['items'])
                ->filter(fn($item) => empty($item['permission']) || auth()->user()->can($item['permission']));
        @endphp

        @if($accessible->isEmpty())
            @continue
        @endif

        <div class="border-b border-gray-200 dark:border-gray-700">
            {{-- Intestazione sezione --}}
            <button
                @click="openSection = (openSection === {{ $i }} ? null : {{ $i }})"
                class="w-full flex justify-between items-center px-6 py-3 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none"
            >
                <span class="font-semibold text-gray-700 dark:text-gray-200">
                    {{ __($section['section']) }}
                </span>
                <i
                    :class="openSection === {{ $i }} ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"
                    class="text-gray-500 dark:text-gray-400"
                ></i>
            </button>

            {{-- Voci accessibili della sezione --}}
            <ul
                x-show="openSection === {{ $i }}"
                x-transition
                class="space-y-1 bg-gray-50 dark:bg-gray-900"
            >
                @foreach($accessible as $item)
                    @php
                        $isActive = request()->routeIs($item['route']);
                    @endphp
                    <li>
                        <a
                            href="{{ route($item['route']) }}"
                            class="block px-8 py-2 text-sm transition-colors
                                   {{ $isActive
                                        ? 'bg-gray-200 dark:bg-gray-700 font-medium text-gray-900 dark:text-gray-100'
                                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700' }}"
                        >
                            {{ __($item['label']) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</aside>