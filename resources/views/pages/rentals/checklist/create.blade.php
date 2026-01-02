{{-- resources/views/pages/rentals/checklist/create.blade.php --}}
<x-app-layout>
    {{-- Header con titolo e pulsante "indietro" alla rentals/show --}}
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-white dark:text-gray-200 leading-tight">
                {{-- Mostro il tipo (pickup/return) letto da query string --}}
                {{ __('Checklist') }} — 
                <span class="uppercase">{{ request()->query('type', 'pickup') }}</span>
            </h2>

            {{-- Torna alla show del rental --}}
            <a href="{{ route('rentals.show', $rental) }}"
               class="btn btn-outline px-3 py-1.5 rounded-md border bg-gray-100 border-gray-300 text-gray-700 hover:bg-gray-50
                      dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">                      
                {{-- Icona minimale (accessoria) --}}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                    <path fill-rule="evenodd"
                          d="M9.707 14.707a1 1 0 01-1.414 0L3.586 10l4.707-4.707a1 1 0 111.414 1.414L6.414 10l3.293 3.293a1 1 0 010 1.414z"
                          clip-rule="evenodd" />
                    <path d="M5 10a1 1 0 011-1h9a1 1 0 110 2H6a1 1 0 01-1-1z" />
                </svg>
                {{ __('Torna al noleggio') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
            {{-- Montiamo il componente Livewire passandogli rental e type dalla query --}}
            @livewire('rentals.checklist-form', ['rental' => $rental, 'type' => request()->query('type', 'pickup')])
        </div>
    </div>
</x-app-layout>
