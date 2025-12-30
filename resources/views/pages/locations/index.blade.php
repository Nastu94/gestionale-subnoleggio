{{-- resources/views/pages/locations/index.blade.php --}}

{{-- Sedi ▸ Index (toast-only) --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-white dark:text-gray-200 leading-tight">
                {{ __('Sedi (Punti operativi)') }}
            </h2>

            {{-- Pulsante: Nuova sede --}}
            <a href="{{ route('locations.create') }}"
               class="inline-flex items-center px-3 py-1.5 bg-indigo-600 rounded-md
                      text-xs font-semibold text-white uppercase hover:bg-indigo-500
                      focus:outline-none focus:ring-2 focus:ring-indigo-300 transition">
                <i class="fas fa-plus mr-1"></i> Nuova sede
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Componente Livewire tabellare --}}
            <livewire:locations.table />
        </div>
        {{-- Stato di caricamento (progress) per UX migliore--}}
        <div wire:loading class="mt-4 text-sm text-gray-500">Caricamento…</div>
    </div>
</x-app-layout>
