{{-- resources/views/pages/vehicles/partials/photos.blade.php --}}

<div class="space-y-4">
    {{-- Form upload (solo admin con permesso vehicles.update|vehicles.create) --}}
    @canany(['vehicles.update','vehicles.create'])
        <form method="POST"
              action="{{ route('vehicles.photos.store', $vehicle) }}"
              enctype="multipart/form-data"
              class="flex flex-wrap items-center gap-3 rounded-md border border-gray-200 dark:border-gray-700 p-3 bg-white dark:bg-gray-800">
            @csrf

            <div>
                <input type="file" name="photo" accept="image/*" required
                       class="text-sm file:mr-2 file:rounded file:border-0 file:bg-slate-800 file:px-3 file:py-1.5 file:text-white hover:file:bg-slate-900" />
            </div>

            <button type="submit"
                    class="inline-flex h-9 items-center rounded-md bg-slate-800 px-3 text-sm text-white hover:bg-slate-900">
                Carica foto
            </button>

            @error('photo')
                <div class="text-sm text-red-600">{{ $message }}</div>
            @enderror
        </form>
    @endcanany

    {{-- Galleria --}}
    @php
        $photos = $vehicle->getMedia('vehicle_photos');
    @endphp

    @if ($photos->isEmpty())
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Nessuna foto caricata per questo veicolo.
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach ($photos as $media)
                <div class="group relative overflow-hidden rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <a href="{{ $media->getUrl('preview') }}" target="_blank" class="block">
                        <img src="{{ $media->getUrl('thumb') }}" class="w-full h-full object-contain" alt="">
                    </a>

                    <div class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 p-2 bg-black/50 opacity-0 group-hover:opacity-100 transition">
                        <a href="{{ $media->getUrl() }}" target="_blank"
                           class="text-xs text-white underline">Originale</a>

                        @canany(['vehicles.update','vehicles.create'])
                            <form method="POST"
                                  action="{{ route('vehicles.photos.destroy', [$vehicle, $media]) }}"
                                  onsubmit="return confirm('Eliminare definitivamente questa foto?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-xs text-red-200 hover:text-red-100 underline">
                                    Elimina
                                </button>
                            </form>
                        @endcanany
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
