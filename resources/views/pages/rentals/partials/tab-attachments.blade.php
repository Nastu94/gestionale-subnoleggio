{{-- resources/views/pages/rentals/partials/tab-attachments.blade.php --}}
<div class="card shadow">
    <div class="card-body space-y-4">
        <div class="card-title">Allegati noleggio</div>

        <ul class="space-y-2">
            @forelse($rental->getMedia('documents') as $m)
                <li class="flex items-center justify-between bg-base-200 rounded p-2">
                    <div class="text-sm">{{ $m->file_name }} · {{ $m->created_at->format('d/m/Y H:i') }}</div>
                    <div class="flex gap-2">
                        <a class="btn btn-xs" href="{{ $m->getUrl() ?: $m->getUrl('preview') }}" target="_blank">Apri</a>
                        <form method="POST" action="{{ route('media.destroy', $m) }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-error btn-xs">Elimina</button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="opacity-70 text-sm">Nessun documento.</li>
            @endforelse
        </ul>

        <x-media-uploader
            label="Carica documento"
            :action="route('rentals.media.documents.store', $rental)"
            accept="application/pdf,image/*"
            multiple
        />
    </div>
</div>
