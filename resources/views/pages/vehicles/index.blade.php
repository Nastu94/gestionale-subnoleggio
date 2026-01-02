{{-- resources/views/vehicles/index.blade.php --}}
<x-app-layout>
    <div class="p-6">
        <x-slot name="header">
            <div class="flex flex-wrap items-center justify-between">
                <h2 class="font-semibold text-xl text-white dark:text-gray-200 leading-tight">
                    {{ __('Gestione veicoli') }}
                </h2>

                @can('vehicles.create')
                    <a href="{{ route('vehicles.create') }}"
                    class="inline-flex h-10 items-center rounded-md px-3 bg-gray-100 text-gray-900 hover:bg-gray-200">
                        {{-- icona opzionale: + --}}
                        <span class="mr-1 text-lg leading-none">+</span>
                        Nuovo veicolo
                    </a>
                @endcan
            </div>
        </x-slot>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
            {{-- Componente Livewire: tabella veicoli con ricerca, ordinamento, paginazione --}}
            {{-- Nota: la policy è applicata dentro al componente tramite $this->authorize(...) --}}
            <livewire:vehicles.table />

            {{-- Facoltativo: stato di caricamento (progress) per UX migliore--}}
            <div wire:loading class="mt-4 text-sm text-gray-500">Caricamento…</div>
            
        </div>
    </div>
</x-app-layout>
