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
                class="inline-flex h-10 items-center rounded-md px-3 bg-gray-100 text-gray-900 hover:bg-gray-200">
                    <span class="mr-1 text-lg leading-none">←</span> Torna alle Sedi
                </a>

                {{-- Mostra solo se l’utente può aggiornare --}}
                @can('update', $location)
                    <a href="{{ route('locations.edit', $location) }}"
                    class="inline-flex h-10 items-center rounded-md px-3 bg-gray-100 text-gray-900 hover:bg-gray-200">
                        <span class="mr-1 text-lg leading-none">✎</span> Modifica
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
