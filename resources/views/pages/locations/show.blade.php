{{-- resources/views/pages/locations/show.blade.php --}}

{{-- Sedi ▸ Dettaglio (toast-only) --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Sede') }}: {{ $location->name }}
            </h2>

            {{-- Pulsante "Indietro": torna all'indice Sedi --}}
            <a href="{{ route('locations.index') }}"
               class="inline-flex items-center px-3 py-1.5 rounded-md border
                      text-xs font-semibold uppercase
                      hover:bg-gray-100 dark:hover:bg-gray-700
                      text-gray-800 dark:text-gray-200">
                <i class="fas fa-arrow-left mr-1"></i> Torna alle Sedi
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Componente Livewire: informazioni + assegnazione veicoli --}}
            <livewire:locations.show :location="$location" />
        </div>
        {{-- Stato di caricamento (progress) per UX migliore--}}
        <div wire:loading class="mt-4 text-sm text-gray-500">Caricamento…</div>
    </div>
</x-app-layout>
