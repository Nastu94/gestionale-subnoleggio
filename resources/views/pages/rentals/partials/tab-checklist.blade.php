{{-- resources/views/pages/rentals/partials/tab-checklist.blade.php --}}
@php
    $checklist = $rental->checklists->firstWhere('type', $type);
@endphp

<div class="card shadow">
    <div class="card-body space-y-4">
        <div class="flex items-center justify-between">
            <div class="card-title">Checklist {{ strtoupper($type) }}</div>
            @if(!$checklist)
                <a href="{{ route('rental-checklists.create', ['rental'=>$rental->id, 'type'=>$type]) }}" 
                    class="btn btn-primary shadow-none px-2
                        !bg-primary !text-primary-content !border-primary
                        hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                        disabled:opacity-50 disabled:cursor-not-allowed">
                    Crea checklist {{ $type }}
                </a>
            @endif
        </div>

        @if($checklist)
            @php
                // Mappatura stato pulizia → classe badge
                $cleanMap = [
                    'poor'      => ['label' => 'Scarsa',     'class' => 'badge-error'],
                    'fair'      => ['label' => 'Discreta',   'class' => 'badge-warning'],
                    'good'      => ['label' => 'Buona',      'class' => 'badge-success'],
                    'excellent' => ['label' => 'Eccellente', 'class' => 'badge-success'],
                ];
                $cleanKey   = (string) ($checklist->cleanliness ?? '');
                $cleanBadge = $cleanMap[$cleanKey] ?? ['label' => ($cleanKey ?: '—'), 'class' => 'badge-outline'];

                // Media collegati
                $photosCount = method_exists($checklist,'getMedia') ? $checklist->getMedia('photos')->count() : 0;
                $sigMedia    = method_exists($checklist,'getMedia') ? $checklist->getMedia('signatures')->first() : null;

                // JSON opzionale della checklist (chiavi true/valorate)
                $json = $checklist->checklist_json;
                if (is_string($json)) { $json = json_decode($json, true); }
                $json = is_array($json) ? $json : [];
                $jsonItems = collect($json)->filter(function($v){ return $v === true || $v === 1 || (is_string($v) && trim($v) !== ''); });
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
                            <span class="font-medium">{{ $checklist->fuel_percent !== null ? $checklist->fuel_percent.'%' : '—' }}</span>
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
                                <a class="link" href="{{ $sigMedia->getUrl('preview') ?: $sigMedia->getUrl() }}" target="_blank">Apri firma</a>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-lg bg-base-200 p-3">
                        <div class="opacity-70">Firma operatore</div>
                        <div class="text-lg">{{ $checklist->signed_by_operator ? '✅' : '—' }}</div>
                    </div>

                    <div class="rounded-lg bg-base-200 p-3">
                        <div class="opacity-70">Foto</div>
                        <div class="font-medium">{{ $photosCount }} {{ Str::plural('foto', $photosCount) }}</div>
                    </div>
                </div>

                {{-- Dettagli aggiuntivi dal JSON (se presenti) --}}
                @if($jsonItems->isNotEmpty())
                    <div class="rounded-lg border p-3">
                        <div class="opacity-70 text-sm mb-2">Dettagli</div>
                        <ul class="list-disc pl-5 text-sm space-y-1">
                            @foreach($jsonItems as $k => $v)
                                <li>
                                    <span class="font-medium">{{ str_replace('_',' ', $k) }}:</span>
                                    <span>
                                        @if($v === true || $v === 1) sì
                                        @else {{ $v }}
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{-- Foto (layout attuale, lasciato intatto) --}}
            <div class="grid md:grid-cols-3 gap-3 mt-4">
                @forelse($checklist->getMedia('photos') as $m)
                    <div class="rounded-xl overflow-hidden border">
                        <img src="{{ $m->getUrl('thumb') }}" alt="photo" class="w-full h-40 object-cover">
                        <div class="p-2 flex justify-between items-center text-sm">
                            <a class="link" href="{{ $m->getUrl('preview') ?: $m->getUrl() }}" target="_blank">Apri</a>
                            <form method="POST" action="{{ route('media.destroy', $m) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-error btn-xs">Elimina</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="opacity-70 text-sm">Nessuna foto caricata.</div>
                @endforelse
            </div>

            <div class="mt-2">
                {{-- Upload foto -> controller media su checklist (invariato) --}}
                <x-media-uploader
                    label="Carica foto"
                    :action="route('checklists.media.photos.store', $checklist)"
                    accept="image/jpeg,image/png"
                    multiple
                />
            </div>
        @endif
    </div>
</div>
