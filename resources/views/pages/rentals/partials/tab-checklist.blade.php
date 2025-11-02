{{-- resources/views/pages/rentals/partials/tab-checklist.blade.php --}}
@php
    /** @var \App\Models\RentalChecklist|null $checklist */
    $checklist = $rental->checklists->firstWhere('type', $type);

    $isLocked = $checklist?->isLocked() ?? false;
@endphp

<div class="card shadow" x-data x-init="Alpine.store('checklist', { locked: {{ Js::from($isLocked) }} })">
    <div class="card-body space-y-4">
        <div class="flex items-center justify-between">
            <div class="card-title">Checklist {{ strtoupper($type) }}</div>

            {{-- Se NON esiste ancora una checklist → mostra soltanto "Crea" --}}
            @unless($checklist)
                <a href="{{ route('rental-checklists.create', ['rental'=>$rental->id, 'type'=>$type]) }}"
                   class="btn btn-primary shadow-none px-2
                          !bg-primary !text-primary-content !border-primary
                          hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30">
                    Crea checklist {{ $type }}
                </a>
            @endunless
        </div>

        {{-- Se ESISTE la checklist, mostra azioni + dettagli --}}
        @if($checklist)
            @php
                // Stato e media collegati in modo sicuro
                $isLocked = $checklist->isLocked();

                // Media firmato (preferibile). Se la relation è vuota, fallback alla collection firmata.
                $signed  = $checklist->signedPdf ?? null;
                if (!$signed && method_exists($checklist, 'getMedia')) {
                    try {
                        $signed = $checklist->getMedia($checklist->signedCollectionName())->first();
                    } catch (\Throwable $e) {
                        $signed = null;
                    }
                }

                // Ultimo PDF non firmato (relation dedicata)
                $lastPdf = $checklist->lastPdf ?? null;

                // URL per "Apri PDF": prima il firmato, poi l'ultimo generato
                $openUrl = $signed?->getUrl() ?: $lastPdf?->getUrl();
            @endphp

            {{-- Azioni --}}
            <div class="rounded-xl border p-3 mb-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold">Azioni</span>
                        @if($isLocked)
                            <span class="badge badge-warning">BLOCCATA</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2"
                         x-data="uploadSignedChecklist({
                            url: {{ Js::from(route('rental-media.checklist-signed.store', $checklist)) }},
                            locked: {{ Js::from($isLocked) }},
                            rentalId: {{ Js::from($rental->id) }},
                         })">

                        {{-- Carica firmato (pdf/jpg/png) --}}
                        <label class="btn btn-outline shadow-none cursor-pointer"
                               x-show="!state.locked"
                               :class="{ 'btn-disabled opacity-50': state.locked }">
                            <input type="file" class="hidden"
                                   x-ref="file"
                                   accept="application/pdf,image/jpeg,image/png"
                                   @change="send($refs.file)"
                                   :disabled="state.locked">
                            Carica checklist firmata
                        </label>

                        {{-- Apri PDF (preferisce firmato) --}}
                        <a href="{{ $openUrl ?: '#' }}" @if($openUrl) target="_blank" rel="noopener" @endif
                           class="btn btn-primary shadow-none
                                  !bg-primary !text-primary-content !border-primary
                                  hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                                  {{ $openUrl ? '' : 'btn-disabled opacity-50 cursor-not-allowed' }}">
                            Apri PDF
                        </a>

                        {{-- Apri/Modifica checklist (se lockata → sola lettura) --}}
                        <a href="{{ route('rental-checklists.create', ['rental'=>$rental->id, 'type'=>$type]) }}"
                           class="btn shadow-none
                                  {{ $isLocked
                                      ? '!bg-slate-200 !text-slate-800 !border-slate-300 hover:brightness-95'
                                      : '!bg-base-300 !text-base-content !border-base-300 hover:brightness-95' }}">
                            {{ $isLocked ? 'Apri checklist (bloccata)' : 'Modifica checklist' }}
                        </a>

                        {{-- Nuova checklist sostitutiva (solo se lockata) --}}
                        @if($isLocked)
                            <a href="{{ route('rental-checklists.create', [
                                    'rental'   => $rental->id,
                                    'type'     => $type,
                                    'replaces' => $checklist->id,
                                ]) }}"
                               class="btn btn-accent shadow-none
                                      !bg-accent !text-accent-content !border-accent
                                      hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-accent/30">
                                Nuova checklist (sostitutiva)
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            @php
                // Mappatura stato pulizia → badge
                $cleanMap = [
                    'poor'      => ['label' => 'Scarsa',     'class' => 'badge-error'],
                    'fair'      => ['label' => 'Discreta',   'class' => 'badge-warning'],
                    'good'      => ['label' => 'Buona',      'class' => 'badge-success'],
                    'excellent' => ['label' => 'Eccellente', 'class' => 'badge-success'],
                ];
                $cleanKey   = (string) ($checklist->cleanliness ?? '');
                $cleanBadge = $cleanMap[$cleanKey] ?? ['label' => ($cleanKey ?: '—'), 'class' => 'badge-outline'];

                // Media collegati (safe)
                $photosCount = method_exists($checklist,'getMedia') ? $checklist->getMedia('photos')->count() : 0;
                $sigMedia    = method_exists($checklist,'getMedia') ? $checklist->getMedia('signatures')->first() : null;

                // JSON opzionale
                $raw = $checklist->checklist_json;
                if (is_string($raw)) { $decoded = json_decode($raw, true); $raw = is_array($decoded) ? $decoded : []; }
                elseif (!is_array($raw)) { $raw = []; }

                $groups = [
                    'vehicle' => [
                        'label' => 'Veicolo',
                        'map' => [
                            'horn_ok'       => 'Clacson funzionante',
                            'tires_ok'      => 'Pneumatici in ordine',
                            'brakes_ok'     => 'Freni funzionanti',
                            'lights_ok'     => 'Luci funzionanti',
                            'windshield_ok' => 'Parabrezza in ordine',
                        ],
                    ],
                    'documents' => [
                        'label' => 'Documenti',
                        'map' => [
                            'id_card'        => 'Documento identità',
                            'contract_copy'  => 'Copia contratto',
                            'driver_license' => 'Patente di guida',
                        ],
                    ],
                    'equipment' => [
                        'label' => 'Dotazioni',
                        'map' => [
                            'jack'         => 'Cric',
                            'vest'         => 'Gilet alta visibilità',
                            'triangle'     => 'Triangolo',
                            'spare_wheel'  => 'Ruotino / Ruota di scorta',
                        ],
                    ],
                ];

                $normalized = [];
                foreach ($groups as $key => $cfg) {
                    $src = $raw[$key] ?? [];
                    if (!is_array($src)) $src = [];
                    $items = [];
                    foreach ($cfg['map'] as $k => $human) {
                        if (!array_key_exists($k, $src)) continue;
                        $v = $src[$k];
                        if (is_bool($v) || $v === 0 || $v === 1) {
                            $ok = (bool) $v;
                            $items[] = ['label'=>$human, 'value'=>$ok ? 'sì':'no', 'ok'=>$ok];
                        } elseif (is_string($v) && trim($v) !== '') {
                            $items[] = ['label'=>$human, 'value'=>trim($v), 'ok'=>null];
                        }
                    }
                    if ($items) $normalized[] = ['title'=>$cfg['label'], 'items'=>$items];
                }

                $notes = (isset($raw['notes']) && is_string($raw['notes']) && trim($raw['notes']) !== '')
                    ? trim($raw['notes']) : null;
            @endphp

            {{-- Riepilogo dati checklist --}}
            <div class="rounded-xl border p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="font-semibold">Dati checklist</div>
                    <div class="text-xs opacity-70">
                        Compilata il {{ optional($checklist->created_at)->format('d/m/Y H:i') ?? '—' }}
                    </div>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                    <div class="rounded-lg bg-base-200 p-3">
                        <div class="opacity-70">Chilometraggio</div>
                        <div class="font-medium">
                            {{ $checklist->mileage !== null ? number_format($checklist->mileage, 0, ',', '.') . ' km' : '—' }}
                        </div>
                    </div>

                    <div class="rounded-lg bg-base-200 p-3">
                        <div class="opacity-70">Carburante</div>
                        <div class="flex items-center gap-3">
                            <progress class="progress progress-primary w-40"
                                      value="{{ (int) ($checklist->fuel_percent ?? 0) }}" max="100"></progress>
                            <span class="font-medium">
                                {{ $checklist->fuel_percent !== null ? $checklist->fuel_percent.'%' : '—' }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-lg bg-base-200 p-3">
                        <div class="opacity-70">Pulizia</div>
                        <div>
                            <span class="badge {{ $cleanBadge['class'] }}">{{ $cleanBadge['label'] }}</span>
                        </div>
                    </div>

                    <div class="rounded-lg bg-base-200 p-3">
                        <div class="opacity-70">Firma cliente</div>
                        <div class="flex items-center gap-2">
                            <span class="text-lg">{{ $checklist->signed_by_customer ? '✅' : '—' }}</span>
                            @if($sigMedia)
                                <a class="link" href="{{ $sigMedia->getUrl() ?: $sigMedia->getUrl('preview') }}" target="_blank">Apri firma</a>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-lg bg-base-200 p-3">
                        <div class="opacity-70">Firma operatore</div>
                        <div class="text-lg">{{ $checklist->signed_by_operator ? '✅' : '—' }}</div>
                    </div>

                    <div class="rounded-lg bg-base-200 p-3">
                        <div class="opacity-70">Foto</div>
                        <div class="font-medium">{{ $photosCount }} {{ \Illuminate\Support\Str::plural('foto', $photosCount) }}</div>
                    </div>
                </div>

                @if(!empty($normalized) || $notes)
                    <div class="rounded-lg border p-3 space-y-3">
                        <div class="opacity-70 text-sm">Dettagli checklist</div>

                        @if(!empty($normalized))
                            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($normalized as $group)
                                    <div class="rounded-md bg-base-200 p-3">
                                        <div class="font-medium mb-2">{{ $group['title'] }}</div>
                                        <ul class="space-y-1 text-sm">
                                            @foreach($group['items'] as $it)
                                                <li class="flex items-center gap-2">
                                                    @if($it['ok'] === true)
                                                        <span class="text-lg text-success leading-none">✓</span>
                                                    @elseif($it['ok'] === false)
                                                        <span class="text-lg text-error leading-none">✕</span>
                                                    @else
                                                        <span class="text-lg opacity-60 leading-none">•</span>
                                                    @endif
                                                    <span class="font-medium">{{ $it['label'] }}</span>
                                                    <span class="opacity-70">— {{ $it['value'] }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($notes)
                            <div class="rounded-md bg-base-200 p-3">
                                <div class="opacity-70 text-sm mb-1">Note</div>
                                <div class="text-sm">{{ $notes }}</div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            @php
                // Sezioni foto
                $photoGroups = [
                    'odometer' => 'Contachilometri',
                    'fuel'     => 'Carburante',
                    'exterior' => 'Esterni',
                ];

                $mediaPhotos = method_exists($checklist,'getMedia') ? $checklist->getMedia('photos') : collect();
                $byKind = collect($mediaPhotos)->groupBy(function($m) {
                    $k = $m->getCustomProperty('kind');
                    if ($k) return $k;
                    $name = (string)($m->name ?? '');
                    if (str_starts_with($name, 'checklist-')) {
                        return \Illuminate\Support\Str::after($name, 'checklist-');
                    }
                    return 'exterior';
                });
            @endphp

            <div class="space-y-6 mt-4">
                @php $anyPhoto = false; @endphp

                @foreach($photoGroups as $kind => $title)
                    @php
                        /** @var \Illuminate\Support\Collection $items */
                        $items = $byKind->get($kind, collect());
                        $anyPhoto = $anyPhoto || $items->isNotEmpty();
                    @endphp

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold">{{ $title }}</h4>
                            <span class="badge">{{ $items->count() }} {{ \Illuminate\Support\Str::plural('foto', $items->count()) }}</span>
                        </div>

                        @if($items->isNotEmpty())
                            <div class="grid md:grid-cols-3 gap-3">
                                @foreach($items as $m)
                                    <div class="rounded-xl overflow-hidden border">
                                        <img src="{{ $m->getUrl('thumb') }}" alt="photo" class="w-full h-40 object-cover">
                                        <div class="p-2 flex items-center justify-between gap-2">
                                            <a href="{{ $m->getUrl('preview') ?: $m->getUrl() }}"
                                               target="_blank" rel="noopener"
                                               class="btn btn-primary btn-sm w-1/2 shadow-none
                                                      !bg-primary !text-primary-content !border-primary
                                                      hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30">
                                                Apri
                                            </a>
                                            <form method="POST"
                                                  action="{{ route('media.destroy', $m) }}"
                                                  class="w-1/2"
                                                  x-data="ajaxDeleteMedia()"
                                                  x-on:submit.prevent="submit($event)"
                                                  :class="{ 'opacity-50 pointer-events-none': $store.checklist?.locked }">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-error btn-sm w-full shadow-none
                                                               !bg-error !text-error-content !border-error
                                                               hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-error/30"
                                                        :disabled="$store.checklist?.locked">
                                                    <span x-show="!loading">Elimina</span>
                                                    <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="opacity-70 text-sm">Nessuna foto in questa sezione.</div>
                        @endif
                    </div>
                @endforeach

                @if(!$anyPhoto)
                    <div class="opacity-70 text-sm">Nessuna foto caricata.</div>
                @endif
            </div>

            {{-- Upload foto con select "Tipo" (odometer/fuel/exterior) --}}
            <div class="mt-4 rounded-xl border p-4" x-show="!state.locked"
                 x-data="{ state: $store.checklist }"
                 x-cloak
                 x-transition>
                <form x-data="checklistUpload()" x-on:submit.prevent="send($el)"
                      class="grid sm:grid-cols-3 gap-3 items-end"
                      data-photos-store="{{ route('checklists.media.photos.store', $checklist) }}">
                    @csrf

                    <div>
                        <label class="label"><span class="label-text">Tipo foto</span></label>
                        <select name="kind" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800" required>
                            <option value="odometer">Contachilometri</option>
                            <option value="fuel">Carburante</option>
                            <option value="exterior">Esterni</option>
                        </select>
                    </div>

                    <div>
                        <label class="label"><span class="label-text">File immagine</span></label>
                        <input type="file" name="file" accept="image/jpeg,image/png"
                               class="file-input file-input-bordered w-full" required />
                    </div>

                    <div class="flex sm:justify-end">
                        <button type="submit"
                                class="btn btn-primary shadow-none w-1/2
                                       !bg-primary !text-primary-content !border-primary
                                       hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30"
                                x-bind:disabled="sending">
                            <span x-show="!sending">Carica</span>
                            <span x-show="sending" class="loading loading-spinner loading-sm"></span>
                        </button>
                    </div>
                </form>
            </div>

        @endif
    </div>
</div>
