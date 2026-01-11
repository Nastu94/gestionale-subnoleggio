{{-- resources/views/pages/rentals/partials/tab-attachments.blade.php --}}
@php
    // Etichette "umane" per le collection più usate
    $labelMap = [
        'documents'      => 'Documenti vari',
        'id_card'        => 'Documento identità',
        'driver_license' => 'Patente',
        'privacy'        => 'Consenso privacy',
        'signature_customer' => 'Firma cliente',
        'signature_lessor'   => 'Firma Noleggiatore',
        'other'          => 'Altro',
        // 'contract'     => 'Contratti generati',
        // 'signatures'   => 'Contratti firmati',
    ];

    // Escludi eventuali collection già coperte da altre tab (contratto / firme / foto)
    $exclude = ['contract', 'signatures', 'photos'];

    // Prendi tutti i media del Rental e raggruppa per collection (al netto degli esclusi)
    $all = ($rental->relationLoaded('media') ? $rental->media : $rental->load('media')->media)
        ->filter(fn($m) => !in_array($m->collection_name, $exclude));

    $groups = $all->groupBy('collection_name')->sortKeys();
@endphp
<div class="card shadow">
    <div class="card-body space-y-4">
        <div class="card-title">Allegati noleggio</div>

        @forelse($groups as $collection => $items)
            <div class="rounded-xl border overflow-hidden">
                {{-- Header gruppo --}}
                <div class="px-3 py-2 flex items-center justify-between bg-base-200">
                    <div class="font-semibold">
                        {{ $labelMap[$collection] ?? ucfirst(str_replace('_',' ', $collection)) }}
                    </div>
                    <span class="badge badge-ghost">{{ $items->count() }}</span>
                </div>

                {{-- Lista file --}}
                <ul class="divide-y">
                    @foreach($items as $m)
                        @php
                            $isPdf   = str_contains($m->mime_type ?? '', 'pdf');
                            $isImage = str_contains($m->mime_type ?? '', 'image');
                            $icon    = $isPdf ? '📄' : ($isImage ? '🖼️' : '📎');
                        @endphp

                        <li class="p-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-xl leading-none">{{ $icon }}</span>
                                <div class="min-w-0">
                                    <div class="font-medium truncate">{{ $m->file_name }}</div>
                                    <div class="text-xs opacity-70">
                                        {{ $m->mime_type ?? 'file' }} · {{ $m->created_at?->format('d/m/Y H:i') }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 gap-2">
                                {{-- Apri --}}
                                <a href="{{ $m->getUrl() }}" target="_blank"
                                   class="btn btn-sm shadow-none
                                          !bg-neutral !text-neutral-content !border-neutral
                                          hover:brightness-95 focus-visible:outline-none
                                          focus-visible:ring focus-visible:ring-neutral/30">
                                    Apri
                                </a>

                                {{-- Elimina (stile coerente) --}}
                                <form method="POST"
                                        action="{{ route('media.destroy', $m) }}"
                                        x-data="ajaxDeleteMedia()"
                                        x-cloak
                                        x-on:submit.prevent="submit($event)"
                                        :class="{ 'opacity-50 pointer-events-none': $store.checklist?.locked || $store.rental.isClosed }">
                                    @csrf @method('DELETE')
                                    <button
                                        :disabled="$store.rental.isClosed || loading"
                                        class="btn btn-error btn-sm shadow-none
                                               !bg-error !text-error-content !border-error
                                               hover:brightness-95 focus-visible:outline-none
                                               focus-visible:ring focus-visible:ring-error/30">
                                        Elimina
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="opacity-70">Nessun allegato caricato.</div>
        @endforelse

        <x-media-uploader
            label="Carica documento"
            :action="route('rentals.media.documents.store', $rental)"
            accept="application/pdf,image/*"
        >
            {{-- SLOT: selezione tipo documento → salvato come collection_name in media --}}
            <select name="collection"
                    class="mt-1 w-full rounded-md border-gray-300 shadow-sm appearance-none pr-8 focus:border-indigo-500 focus:ring-indigo-500">
                <option value="documents">Documenti vari (precontrattuali)</option>
                <option value="id_card">Documento identità</option>
                <option value="driver_license">Patente</option>
                <option value="privacy">Consenso privacy</option>
                <option value="other">Altro</option>
            </select>
        </x-media-uploader>
    </div>
</div>
