{{-- resources/views/pages/assignments/index.blade.php --}}
<x-app-layout>
    <div class="p-6">
        <h1 class="text-xl font-semibold mb-4">Assegnazioni – elenco</h1>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">

            {{-- Componente Livewire: vista di assegnazione veicoli ai renter (organizzazioni) --}}
            {{-- Nota: la policy è applicata dentro al componente tramite $this->authorize(...) --}}
            <livewire:assignments.vehicle-assigner />

            {{-- Stato di caricamento (progress) per UX migliore--}}
            <div wire:loading class="mt-4 text-sm text-gray-500">Caricamento…</div>
        </div>
    </div>
</x-app-layout>
