{{-- resources/views/pages/locations/create.blade.php --}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-white dark:text-gray-200 leading-tight">
                {{ __('Nuova sede') }}
            </h2>

            {{-- Indietro all’indice --}}
            <a href="{{ route('locations.index') }}"
               class="inline-flex items-center px-3 py-1.5 rounded-md border
                      text-xs font-semibold uppercase hover:bg-gray-100 dark:hover:bg-gray-700
                      text-gray-800 dark:text-gray-200">
                <i class="fas fa-arrow-left mr-1"></i> Torna alle Sedi
            </a>
        </div>
    </x-slot>

    {{-- Listener opzionale per navigazione post-toast --}}
    <div class="py-6" x-data
         @navigate.window="setTimeout(() => window.location = $event.detail.url, 700)">
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg max-w-3xl mx-auto sm:px-6 lg:px-8">
            <livewire:locations.create />
        </div>
    </div>
</x-app-layout>
