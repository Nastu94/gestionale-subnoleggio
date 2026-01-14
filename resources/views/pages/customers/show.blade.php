{{-- resources/views/pages/customers/show.blade.php --}}

{{-- Clienti ▸ Dettaglio --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-white dark:text-gray-200 leading-tight">
                {{ __('Cliente') }}: {{ $customer->name }}
            </h2>
            
            <a href="{{ route('customers.index') }}"
                class="inline-flex items-center px-3 py-1.5 rounded-md border text-xs font-semibold
                        uppercase bg-white hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-1"></i> Indietro
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Componente Livewire per editare dati identitari + residenza --}}
            <livewire:customers.show :customer="$customer" />
        </div>
        
        {{-- Stato di caricamento (progress) per UX migliore--}}
        <div wire:loading class="mt-4 text-sm text-gray-500">Caricamento…</div>
    </div>
</x-app-layout>
