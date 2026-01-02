{{-- resources/views/pages/rentals/partials/tab-damages.blade.php --}}
<div class="card shadow" x-data="damageGallery()">
    <div class="card-body space-y-4">
        <div class="card-title">Danni</div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($rental->damages as $dmg)
                <div class="rounded-xl border p-3 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="font-semibold">#{{ $dmg->id }} · {{ $dmg->phase }}</div>
                        <span class="badge">{{ $dmg->area_label }} - {{ $dmg->severity_label }}</span>
                    </div>

                    <div class="text-sm opacity-80">{{ $dmg->description }}</div>

                    <div class="grid grid-cols-3 gap-2">
                        @forelse($dmg->getMedia('photos') as $m)
                            <div class="space-y-1">
                                <img
                                    src="{{ $m->getUrl('thumb') }}"
                                    alt="Danno #{{ $dmg->id }}"
                                    class="rounded-md w-full h-20 object-cover cursor-zoom-in hover:opacity-90 transition"
                                    @click="openPreview('{{ $m->getUrl() }}', 'Danno #{{ $dmg->id }}')"
                                />

                                <form method="POST" 
                                        action="{{ route('media.destroy', $m) }}"
                                        x-data="ajaxDeleteMedia()"
                                        x-on:submit.prevent="submit($event)"
                                        :class="{ 'opacity-50 pointer-events-none': $store.checklist?.locked || $store.rental.isClosed }">
                                    @csrf @method('DELETE')
                                    <button
                                        x-cloak
                                        :disabled="$store.rental.isClosed || loading"
                                        class="btn btn-error btn-xs w-full shadow-none
                                               !bg-error !text-error-content !border-error
                                               hover:brightness-95 focus-visible:outline-none
                                               focus-visible:ring focus-visible:ring-error/30">
                                        Elimina
                                    </button>
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

    {{-- Lightbox / Preview (teleport nel body) --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-transition.opacity
            class="fixed inset-0 z-[95] flex items-center justify-center bg-black/70 p-4"
            role="dialog" aria-modal="true"
            @keydown.escape.prevent.stop="close()"
        >
            <div class="absolute inset-0" @click="close()"></div>

            <div class="relative max-h-[90vh] max-w-[90vw]">
                <img :src="src" :alt="alt" class="rounded-lg shadow-2xl max-h-[90vh] max-w-[90vw] object-contain" />
                <button type="button"
                        class="btn btn-ghost btn-sm absolute -top-2 -right-2"
                        @click="close()">✕</button>
            </div>
        </div>
    </template>
</div>
