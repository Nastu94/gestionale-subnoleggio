{{-- resources/views/pages/locations/show.blade.php --}}

{{-- Sedi ▸ Dettaglio (toast-only) --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-white dark:text-gray-200 leading-tight">
                {{ __('Sede') }}: {{ $location->name }}
            </h2>

            <div class="flex gap-2">
                <a href="{{ route('locations.index') }}"
                class="inline-flex items-center px-3 py-1.5 rounded-md border
                        text-xs font-semibold uppercase hover:bg-gray-400 dark:hover:bg-gray-700 bg-white
                        text-gray-800 dark:text-gray-200">
                    <i class="fas fa-arrow-left mr-1"></i> Torna alle Sedi
                </a>

                {{-- Mostra solo se l’utente può aggiornare --}}
                @can('update', $location)
                    <a href="{{ route('locations.edit', $location) }}"
                    class="inline-flex items-center px-3 py-1.5 bg-indigo-600 rounded-md
                            text-xs font-semibold text-white uppercase hover:bg-indigo-500
                            focus:outline-none focus:ring-2 focus:ring-indigo-300 transition">
                        <i class="fas fa-pencil-alt mr-1"></i> Modifica
                    </a>
                @endcan
            </div>
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
