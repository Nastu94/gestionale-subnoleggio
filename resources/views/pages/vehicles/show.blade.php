{{-- resources/views/vehicles/index.blade.php --}}
<x-app-layout>
    <div class="p-6">
        <x-slot name="header">
            <div class="flex flex-wrap items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Dettaglio veicolo') }} — {{ $vehicle->plate }}
                </h2>

                @can('vehicles.update')
                    <a href="{{ route('vehicles.edit', $vehicle) }}"
                    class="inline-flex h-10 items-center rounded-md bg-slate-800 px-3 text-white hover:bg-slate-900">
                        Modifica
                    </a>
                @endcan
            </div>
        </x-slot>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
            {{-- Componente Livewire: dettaglio veicolo --}}
            {{-- Nota: la policy è applicata dentro al componente tramite $this->authorize(...) --}}
            <livewire:vehicles.show :vehicle-id="$vehicle->id" />
            
            {{-- Stato di caricamento (progress) per UX migliore--}}
            <div wire:loading class="mt-4 text-sm text-gray-500">Caricamento…</div>
        </div>
    </div>
</x-app-layout>
