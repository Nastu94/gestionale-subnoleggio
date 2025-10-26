{{-- resources/views/pages/rentals/partials/tab-damages.blade.php --}}
<div class="card shadow">
    <div class="card-body space-y-4">
        <div class="card-title">Danni</div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($rental->damages as $dmg)
                <div class="rounded-xl border p-3 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="font-semibold">#{{ $dmg->id }} · {{ $dmg->phase }}</div>
                        <span class="badge">{{ $dmg->severity }}</span>
                    </div>
                    <div class="text-sm opacity-80">{{ $dmg->description }}</div>

                    <div class="grid grid-cols-3 gap-2">
                        @forelse($dmg->getMedia('photos') as $m)
                            <div>
                                <img src="{{ $m->getUrl('thumb') }}" class="rounded-md w-full h-20 object-cover" />
                                <form method="POST" action="{{ route('media.destroy', $m) }}" class="mt-1">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-error btn-xs w-full">Elimina</button>
                                </form>
                            </div>
                        @empty
                            <div class="col-span-3 opacity-70 text-sm">Nessuna foto.</div>
                        @endforelse
                    </div>

                    <x-media-uploader
                        label="Aggiungi foto danno"
                        :action="route('damages.media.photos.store', $dmg)"
                        accept="image/jpeg,image/png"
                        multiple
                    />
                </div>
            @empty
                <div class="opacity-70">Nessun danno registrato.</div>
            @endforelse
        </div>
    </div>
</div>
