{{-- resources/views/pages/customers/index.blade.php --}}

{{-- Clienti ▸ Index (readonly) --}}
<x-app-layout>
    {{-- Header coerente con organizations.index --}}
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-white dark:text-gray-200 leading-tight">
                {{ __('Clienti') }}
            </h2>
        </div>
    </x-slot>

    {{-- Contenitore come nelle altre pagine --}}
    <div class="py-6">
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Componente Livewire della tabella --}}
            <livewire:customers.table />
        </div>

        {{-- Stato di caricamento (progress) per UX migliore--}}
        <div wire:loading class="mt-4 text-sm text-gray-500">Caricamento…</div>
    </div>
</x-app-layout>
