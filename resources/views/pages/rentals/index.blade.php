{{-- resources/views/pages/rentals/index.blade.php --}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-white dark:text-gray-200 leading-tight">
                {{ __('Noleggi') }}
            </h2>
        </div>
    </x-slot>

    <div class="p-6">
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
            {{-- Dashboard KPI + Table/Kanban --}}
            <livewire:rentals.rentals-board />
        </div>
    </div>
</x-app-layout>
