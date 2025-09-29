<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            <!-- Component per le dashboard tiles -->
            <!--<x-dashboard-tiles />-->
        </div>
    </x-slot>

    <!-- Main Content: Grid Menu Component -->
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-visible shadow-xl sm:rounded-lg py-6">
                <!-- Sostituiamo x-welcome con menu a griglia -->
                <x-radial-grid-menu />
            </div>
        </div>
    </div>
</x-app-layout>
