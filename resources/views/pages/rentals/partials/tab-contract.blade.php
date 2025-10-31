{{-- resources/views/pages/rentals/partials/tab-contract.blade.php --}}
{{-- Stile coerente con la pagina: header con badge di stato, liste con card leggere e bottoni filled --}}
@php
    $hasGenerated = method_exists($rental,'getMedia') && $rental->getMedia('contract')->isNotEmpty();
    $hasSigned    = method_exists($rental,'getMedia') && $rental->getMedia('signatures')->isNotEmpty();
@endphp

<div class="card shadow">
    <div class="card-body space-y-5">
        {{-- Header sezione --}}
        <div class="flex items-center justify-between">
            <div class="card-title">Contratto</div>
            <div class="flex gap-2">
                <span class="badge {{ $hasGenerated ? 'badge-success' : 'badge-outline' }}">Generato</span>
                <span class="badge {{ $hasSigned ? 'badge-success' : 'badge-outline' }}">Firmato</span>
            </div>
        </div>

        {{-- CTA: mostra solo se NON c'è un contratto generato --}}
        @if(!$hasGenerated)
            <div class="flex justify-end">
                <button
                    class="btn btn-primary shadow-none
                        !bg-primary !text-primary-content !border-primary
                        hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                        disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:click="generateContract"
                    wire:loading.attr="disabled"
                    wire:target="generateContract"
                    title="Genera una nuova versione del contratto (PDF)"
                >
                    <span wire:loading.remove wire:target="generateContract">Genera contratto (PDF)</span>
                    <span wire:loading wire:target="generateContract" class="loading loading-spinner loading-sm"></span>
                </button>
            </div>
        @endif

        <div class="grid md:grid-cols-2 gap-5">
            {{-- Colonna: versioni generate (PDF) --}}
            <div class="space-y-2">
                <div class="font-semibold">Versioni generate (PDF)</div>

                @forelse($rental->getMedia('contract') as $m)
                    <div class="flex items-center justify-between rounded-xl border p-3">
                        <div class="text-sm">
                            <span class="mr-2">📄</span>
                            <span class="font-medium">{{ $m->name }}</span>
                            <span class="opacity-70">· {{ $m->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="flex gap-2">
                            {{-- Apri (filled neutral) --}}
                            <a href="{{ $m->getUrl() }}" target="_blank"
                               class="btn btn-sm shadow-none
                                      !bg-neutral !text-neutral-content !border-neutral
                                      hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30">
                                Apri
                            </a>
                            {{-- Elimina (per ora submit classico; l’AJAX lo faremo nella sezione Allegati) --}}
                            <form method="POST" action="{{ route('media.destroy', $m) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm shadow-none px-2
                                               !bg-error !text-error-content !border-error
                                               hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-error/30">
                                    Elimina
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="alert alert-info">
                        Nessun contratto generato.
                    </div>
                @endforelse
            </div>

            {{-- Colonna: firmati --}}
            <div class="space-y-2">
                <div class="font-semibold">Firmati</div>

                @forelse($rental->getMedia('signatures') as $m)
                    <div class="flex items-center justify-between rounded-xl border p-3">
                        <div class="text-sm">
                            <span class="mr-2">✍️</span>
                            <span class="font-medium">{{ $m->file_name }}</span>
                            <span class="opacity-70">· {{ $m->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="flex gap-2">
                            {{-- Apri (usa preview se disponibile) --}}
                            <a href="{{ $m->getUrl('preview') ?: $m->getUrl() }}" target="_blank"
                               class="btn btn-sm shadow-none
                                      !bg-neutral !text-neutral-content !border-neutral
                                      hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30">
                                Apri
                            </a>
                            <form method="POST" action="{{ route('media.destroy', $m) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm shadow-none px-2
                                               !bg-error !text-error-content !border-error
                                               hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-error/30">
                                    Elimina
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="alert alert-info">
                        Nessun contratto firmato.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
