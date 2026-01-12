<x-form-section submit="save">
    <x-slot name="title">
        Cargos (Admin)
    </x-slot>

    <x-slot name="description">
        Gestisci Password e PUK Cargos dell’Admin. I valori sono letti/salvati nel file <code>.env</code>.
        Per visualizzarli è richiesta la conferma della password admin.
    </x-slot>

    <x-slot name="form">
        {{-- Stato --}}
        <div class="col-span-6">
            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                <span class="font-semibold">Stato:</span>

                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                    {{ $hasCargosPassword ? 'bg-emerald-200 text-emerald-900 dark:bg-emerald-700 dark:text-white' : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-100' }}">
                    Password {{ $hasCargosPassword ? 'impostata' : 'non impostata' }}
                </span>

                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                    {{ $hasCargosPuk ? 'bg-emerald-200 text-emerald-900 dark:bg-emerald-700 dark:text-white' : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-100' }}">
                    PUK {{ $hasCargosPuk ? 'impostato' : 'non impostato' }}
                </span>

            </div>
        </div>

        {{-- Conferma password admin per reveal --}}
        <div class="col-span-6 pt-2 border-t border-gray-200 dark:border-gray-700">
            <x-label for="admin_confirm_password" value="Conferma password admin (per visualizzare i dati)" />

            <div class="mt-1 flex flex-col sm:flex-row gap-2 sm:items-center">
                <x-input id="admin_confirm_password" type="password" class="block w-full sm:flex-1"
                         wire:model.defer="confirmPassword" autocomplete="current-password" />

                <div class="flex gap-2">
                    <button type="button"
                            wire:click="reveal('password')"
                            class="inline-flex items-center px-3 py-2 rounded-md border text-xs font-semibold uppercase
                                   text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <i class="fas fa-eye mr-1"></i> Mostra password
                    </button>

                    <button type="button"
                            wire:click="reveal('puk')"
                            class="inline-flex items-center px-3 py-2 rounded-md border text-xs font-semibold uppercase
                                   text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <i class="fas fa-eye mr-1"></i> Mostra PUK
                    </button>
                </div>
            </div>

            <x-input-error for="confirmPassword" class="mt-2" />
        </div>

        {{-- Valori rivelati (auto-hide) --}}
        <div class="col-span-6"
             x-data
             x-init="
                @if($revealedCargosPassword || $revealedCargosPuk)
                    setTimeout(() => { $wire.hideReveals() }, 8000);
                @endif
             ">
            @if($revealedCargosPassword)
                <div class="mt-3 p-3 rounded-md bg-amber-50 border border-amber-200 text-amber-900">
                    <div class="text-xs font-semibold mb-1">Password Cargos (visibile temporaneamente)</div>
                    <div class="font-mono text-sm break-all">{{ $revealedCargosPassword }}</div>
                </div>
            @endif

            @if($revealedCargosPuk)
                <div class="mt-3 p-3 rounded-md bg-amber-50 border border-amber-200 text-amber-900">
                    <div class="text-xs font-semibold mb-1">PUK Cargos (visibile temporaneamente)</div>
                    <div class="font-mono text-sm break-all">{{ $revealedCargosPuk }}</div>
                </div>
            @endif
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            Salvato.
        </x-action-message>

        <x-button wire:loading.attr="disabled">
            Salva
        </x-button>
    </x-slot>
</x-form-section>
