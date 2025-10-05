<x-app-layout>
    <div class="p-6">
        <x-slot name="header">
            <div class="flex flex-wrap items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Modifica veicolo') }}
                </h2>
                <a href="{{ route('vehicles.show', $vehicle) }}"
                   class="inline-flex h-10 items-center rounded-md border border-gray-200 dark:border-gray-700 px-3 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                    Torna al dettaglio
                </a>
            </div>
        </x-slot>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
            {{-- KEY diverso per ogni id ⇒ nessuna confusione con altri stati --}}
            <livewire:vehicles.form :vehicle="$vehicle" :key="'vehicles-edit-'.$vehicle->id" />
        </div>
        
        {{-- Stato di caricamento (progress) per UX migliore--}}
        <div wire:loading class="mt-4 text-sm text-gray-500">Caricamento…</div>
    </div>
</x-app-layout>
