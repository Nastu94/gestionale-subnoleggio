{{-- resources/views/livewire/organizations/fees.blade.php --}}
<div wire:key="fees-root" class="relative">
    @if($open)
        <div
            class="fixed inset-0 z-[200]"
            x-data
            @keydown.escape.window="$wire.close()"
        >
            <!-- backdrop -->
            <div class="absolute inset-0 bg-black/50" @click="$wire.close()"></div>

            <!-- panel -->
            <aside class="absolute right-0 top-0 h-full w-full sm:w-[520px] bg-white dark:bg-gray-800 shadow-xl overflow-y-auto">
                <div class="p-4 border-b dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            Commissioni admin – Renter
                        </h3>
                        @if($organization)
                            <p class="text-xs text-gray-500">{{ $organization->name }}</p>
                        @endif
                    </div>
                    <button class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" @click="$wire.close()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-4 space-y-6">
                    {{-- Fee attiva --}}
                    <section class="rounded-lg border dark:border-gray-700 p-4">
                        <h4 class="text-sm font-semibold mb-2 text-gray-800 dark:text-gray-200">Fee attiva</h4>
                        @if($activeFee)
                            <p class="text-sm">
                                <span class="font-semibold">{{ rtrim(rtrim(number_format($activeFee->percent, 2, ',', '.'), '0'), ',') }}%</span>
                                dal {{ optional($activeFee->effective_from)->format('d/m/Y') }}
                                @if($activeFee->effective_to) al {{ $activeFee->effective_to->format('d/m/Y') }} @endif
                            </p>
                        @else
                            <p class="text-sm text-gray-500">Nessuna fee attiva oggi.</p>
                        @endif
                    </section>

                    {{-- Form nuovo periodo --}}
                    <section class="rounded-lg border dark:border-gray-700 p-4">
                        <h4 class="text-sm font-semibold mb-3 text-gray-800 dark:text-gray-200">Nuovo periodo</h4>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs text-gray-600 dark:text-gray-300">Percentuale (%)</label>
                                <input type="number" step="0.01" min="0" max="100" wire:model.defer="form.percent"
                                       class="mt-1 w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm">
                                @error('form.percent') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs text-gray-600 dark:text-gray-300">Inizio validità</label>
                                <input type="date" wire:model.defer="form.effective_from"
                                       class="mt-1 w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm">
                                @error('form.effective_from') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs text-gray-600 dark:text-gray-300">Fine validità (opz.)</label>
                                <input type="date" wire:model.defer="form.effective_to"
                                       class="mt-1 w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm">
                                @error('form.effective_to') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs text-gray-600 dark:text-gray-300">Note (opz.)</label>
                                <textarea rows="2" wire:model.defer="form.notes"
                                          class="mt-1 w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm"></textarea>
                                @error('form.notes') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <button wire:click="save"
                                    class="px-3 py-1.5 text-xs font-semibold rounded-md bg-indigo-600 text-white hover:bg-indigo-500">
                                Salva
                            </button>
                            <button wire:click="resetForm"
                                    class="px-3 py-1.5 text-xs rounded-md bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-100">
                                Pulisci
                            </button>
                        </div>
                    </section>

                    {{-- Storico --}}
                    <section class="rounded-lg border dark:border-gray-700 p-4">
                        <h4 class="text-sm font-semibold mb-3 text-gray-800 dark:text-gray-200">Storico</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr class="text-left">
                                    <th class="px-2 py-2">%</th>
                                    <th class="px-2 py-2">Dal</th>
                                    <th class="px-2 py-2">Al</th>
                                    <th class="px-2 py-2">Azioni</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($fees as $fee)
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="px-2 py-2 text-xs">{{ rtrim(rtrim(number_format($fee->percent, 2, ',', '.'), '0'), ',') }}</td>
                                        <td class="px-2 py-2 text-xs">{{ optional($fee->effective_from)->format('d/m/Y') }}</td>
                                        <td class="px-2 py-2 text-xs">{{ optional($fee->effective_to)->format('d/m/Y') ?? '—' }}</td>
                                        <td class="px-2 py-2">
                                            <button wire:click="edit({{ $fee->id }})" class="text-xs hover:text-yellow-600">
                                                <i class="fas fa-pencil-alt mr-1"></i>Modifica
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-4 text-gray-500">Nessuna fee registrata.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </aside>
        </div>
    @endif
</div>
