<x-action-section>
    <x-slot name="title">
        Cargos (Admin)
    </x-slot>

    <x-slot name="description">
        Visualizza le credenziali Cargos dell’Admin lette dal file <code>.env</code>.
        Per mostrarle è richiesta la conferma della password admin.
        In questa sezione non è possibile modificarle.
    </x-slot>

    <x-slot name="content">
        {{-- Stato --}}
        <div class="col-span-6">
            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                <span class="font-semibold">Stato:</span>

                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                    {{ $hasCargosUsername ? 'bg-emerald-200 text-emerald-900 dark:bg-emerald-700 dark:text-white' : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-100' }}">
                    Username {{ $hasCargosUsername ? 'impostato' : 'non impostato' }}
                </span>

                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                    {{ $hasCargosAgencyId ? 'bg-emerald-200 text-emerald-900 dark:bg-emerald-700 dark:text-white' : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-100' }}">
                    Agency ID {{ $hasCargosAgencyId ? 'impostato' : 'non impostato' }}
                </span>

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
        <div class="col-span-6 pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
            <x-label for="admin_confirm_password" value="Conferma password admin (per visualizzare i dati)" />

            <div class="mt-1 flex flex-col sm:flex-row gap-2 sm:items-center">
                <x-input id="admin_confirm_password"
                         type="password"
                         class="block w-full sm:flex-1"
                         wire:model.defer="confirmPassword"
                         autocomplete="current-password" />

                <div class="flex flex-wrap gap-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <button type="button"
                                wire:click="reveal('username')"
                                class="inline-flex items-center px-3 py-2 rounded-md border text-xs font-semibold uppercase
                                    text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            <i class="fas fa-eye mr-1"></i> Mostra username
                        </button>
                        <button type="button"
                                wire:click="reveal('agency_id')"
                                class="inline-flex items-center px-3 py-2 rounded-md border text-xs font-semibold uppercase
                                    text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            <i class="fas fa-eye mr-1"></i> Mostra agency id
                        </button>

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
            </div>

            <x-input-error for="confirmPassword" class="mt-2" />
        </div>

        {{-- Valori rivelati (auto-hide) --}}
        <div class="col-span-6"
             x-data
             x-init="
                @if($revealedCargosUsername || $revealedCargosAgencyId || $revealedCargosPassword || $revealedCargosPuk)
                    setTimeout(() => { $wire.hideReveals() }, 8000);
                @endif
             ">
            @if($revealedCargosUsername)
                <div class="mt-3 p-3 rounded-md bg-amber-50 border border-amber-200 text-amber-900">
                    <div class="text-xs font-semibold mb-1">Username Cargos (visibile temporaneamente)</div>
                    <div class="font-mono text-sm break-all">{{ $revealedCargosUsername }}</div>
                </div>
            @endif

            @if($revealedCargosAgencyId)
                <div class="mt-3 p-3 rounded-md bg-amber-50 border border-amber-200 text-amber-900">
                    <div class="text-xs font-semibold mb-1">Agency ID Cargos (visibile temporaneamente)</div>
                    <div class="font-mono text-sm break-all">{{ $revealedCargosAgencyId }}</div>
                </div>
            @endif

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
</x-action-section>
