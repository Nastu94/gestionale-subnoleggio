<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Dettaglio noleggio') }}
            </h2>
        </div>
    </x-slot>

    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
        {{-- Scheda con Tabs + Action Drawer --}}
        <livewire:rentals.show :rental="$rental" />
    </div>
</x-app-layout>
