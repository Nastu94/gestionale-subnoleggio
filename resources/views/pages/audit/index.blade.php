{{-- Esempio: resources/views/audit/index.blade.php --}}
<x-app-layout>
    <div class="p-6">
        <x-slot name="header">
            <div class="flex flex-wrap items-center justify-between">
                <h2 class="font-semibold text-xl text-white dark:text-gray-200 leading-tight">
                    {{ __('Audit') }}
                </h2>
                <!-- Component per le dashboard tiles -->
                <!--<x-dashboard-tiles />-->
            </div>
        </x-slot>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Placeholder della vista. Qui inseriremo tabella/Livewire/filtri.
            </p>
        </div>
    </div>
</x-app-layout>
