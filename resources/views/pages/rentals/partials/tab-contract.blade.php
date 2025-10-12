<div class="card shadow">
    <div class="card-body">
        <div class="flex items-center justify-between">
            <div class="card-title">Contratto</div>
            <div class="flex gap-2">
                <span class="badge {{ $rental->getMedia('contract')->isNotEmpty() ? 'badge-success':'badge-outline' }}">Generato</span>
                <span class="badge {{ $rental->getMedia('signatures')->isNotEmpty() ? 'badge-success':'badge-outline' }}">Firmato</span>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <div class="font-semibold mb-2">Versioni generate (PDF)</div>
                <ul class="space-y-2">
                    @forelse($rental->getMedia('contract') as $m)
                        <li class="flex items-center justify-between bg-base-200 rounded p-2">
                            <div class="text-sm">{{ $m->name }} · {{ $m->created_at->format('d/m/Y H:i') }}</div>
                            <div class="flex gap-2">
                                <a class="btn btn-xs" href="{{ $m->getUrl() }}" target="_blank">Apri</a>
                                <form method="POST" action="{{ route('media.destroy', $m) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-error btn-xs">Elimina</button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="opacity-70 text-sm">Nessun contratto generato.</li>
                    @endforelse
                </ul>
            </div>
            <div>
                <div class="font-semibold mb-2">Firmati</div>
                <ul class="space-y-2">
                    @forelse($rental->getMedia('signatures') as $m)
                        <li class="flex items-center justify-between bg-base-200 rounded p-2">
                            <div class="text-sm">{{ $m->file_name }} · {{ $m->created_at->format('d/m/Y H:i') }}</div>
                            <div class="flex gap-2">
                                <a class="btn btn-xs" href="{{ $m->getUrl('preview') ?: $m->getUrl() }}" target="_blank">Apri</a>
                                <form method="POST" action="{{ route('media.destroy', $m) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-error btn-xs">Elimina</button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="opacity-70 text-sm">Nessun contratto firmato.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
