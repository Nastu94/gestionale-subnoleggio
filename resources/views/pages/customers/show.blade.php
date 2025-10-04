{{-- resources/views/customers/show.blade.php --}}
<x-app-layout>
    <div class="p-6">
        <x-slot name="header">
            <div class="flex flex-wrap items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Dettaglio Cliente') }}
                </h2>
                <!-- Component per le dashboard tiles -->
                <!--<x-dashboard-tiles />-->
            </div>
        </x-slot>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
            {{-- Componente Livewire: scheda cliente con form dati, contatti, indirizzo, note --}}
            {{-- Nota: la policy è applicata dentro al componente tramite $this->authorize(...) --}}
            <livewire:customers.show :customer="$customer" />

            {{-- Facoltativo: stato di caricamento (progress) per UX migliore
            <div wire:loading class="mt-4 text-sm text-gray-500">Caricamento…</div>
            --}}
        </div>
    </div>
</x-app-layout>
