<div>
    @if($open)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            {{-- Overlay --}}
            <div class="absolute inset-0 bg-black opacity-75" wire:click="closeModal"></div>

            {{-- Box --}}
            <div class="relative z-10 w-full max-w-3xl bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden max-h-[90vh] flex flex-col">
                {{-- Header --}}
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Cargos
                        </h3>
                        <p class="text-xs text-gray-600 dark:text-gray-300">
                            Password e PUK per segnalazione noleggio (salvati cifrati)
                        </p>
                    </div>

                    <button type="button"
                            wire:click="closeModal"
                            class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                {{-- Body --}}
                <form wire:submit.prevent="save" class="px-6 py-4 overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Info stato --}}
                        <div class="sm:col-span-2 text-xs text-gray-600 dark:text-gray-300">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">Stato:</span>

                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                                             {{ $hasCargosPassword ? 'bg-emerald-200 text-emerald-900 dark:bg-emerald-700 dark:text-white' : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-100' }}">
                                    Password {{ $hasCargosPassword ? 'impostata' : 'non impostata' }}
                                </span>

                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                                             {{ $hasCargosPuk ? 'bg-emerald-200 text-emerald-900 dark:bg-emerald-700 dark:text-white' : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-100' }}">
                                    PUK {{ $hasCargosPuk ? 'impostato' : 'non impostato' }}
                                </span>

                                <span class="ml-auto italic">
                                    Lascia vuoto per non modificare i valori esistenti.
                                </span>
                            </div>
                        </div>

                        {{-- Nuova Password cargos --}}
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Nuova password Cargos</label>
                            <input type="password"
                                   wire:model.defer="state.cargos_password"
                                   class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                                   autocomplete="off">
                            @error('state.cargos_password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Nuovo PUK cargos --}}
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Nuovo PUK Cargos</label>
                            <input type="password"
                                   wire:model.defer="state.cargos_puk"
                                   class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                                   autocomplete="off">
                            @error('state.cargos_puk') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Conferma password admin per reveal --}}
                        <div class="sm:col-span-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">
                                Conferma password admin (per visualizzare i dati)
                            </label>

                            <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                                <input type="password"
                                       wire:model.defer="confirmPassword"
                                       class="flex-1 px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                                       autocomplete="current-password">

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

                            @error('confirmPassword') <p class="text-xs text-red-600 mt-2">{{ $message }}</p> @enderror
                        </div>

                        {{-- Valori rivelati (auto-hide) --}}
                        <div class="sm:col-span-2"
                             x-data
                             x-init="
                                // Se ci sono valori rivelati, auto-hide dopo 8 secondi
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
                    </div>

                    {{-- Footer --}}
                    <div class="mt-6 flex items-center justify-end gap-2">
                        <button type="button"
                                wire:click="closeModal"
                                class="px-3 py-2 rounded-md border text-xs font-semibold uppercase
                                       text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            Chiudi
                        </button>

                        <button type="submit"
                                class="px-3 py-2 rounded-md bg-indigo-600 text-white text-xs font-semibold uppercase
                                       hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300 transition">
                            Salva
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
