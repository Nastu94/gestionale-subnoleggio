@php
    $checklist = $rental->checklists->firstWhere('type', $type);
@endphp

<div class="card shadow">
    <div class="card-body space-y-4">
        <div class="flex items-center justify-between">
            <div class="card-title">Checklist {{ strtoupper($type) }}</div>
            @if(!$checklist)
                <a href="{{ route('rental-checklists.create', ['rental'=>$rental->id, 'type'=>$type]) }}" class="btn btn-primary btn-sm">
                    Crea checklist {{ $type }}
                </a>
            @endif
        </div>

        @if($checklist)
            <div class="grid md:grid-cols-3 gap-3">
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
                {{-- Upload foto -> controller media su checklist --}}
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
