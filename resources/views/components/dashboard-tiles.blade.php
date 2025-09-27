{{-- Dashboard Tiles – widget rapidi con badge conteggi
     Prop: $badges = array associativo [chiave_config => valore_intero]
--}}
@props(['badges' => []])

@php
    use Illuminate\Support\Facades\Gate;

    // Filtra le tiles in base ai permessi dell'utente
    $tiles = collect(config('menu.dashboard_tiles'))
        ->filter(fn($t) => Gate::allows($t['permission']))
        ->values();
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
@foreach($tiles as $tile)
    @php
        // Ricava il numero dal mapping passato dal controller
        $count = (int) ($badges[$tile['badge_count']] ?? 0);
    @endphp

    <a href="{{ route($tile['route']) }}"
       class="flex items-center justify-between p-4 rounded-xl bg-white dark:bg-gray-800 shadow hover:shadow-md transition">
        <div class="flex items-center gap-3">
            <i class="fas {{ $tile['icon'] }} text-2xl text-indigo-600 dark:text-indigo-400"></i>
            <span class="font-semibold text-gray-800 dark:text-gray-100">
                {{ __($tile['label']) }}
            </span>
        </div>
        <span class="inline-flex items-center justify-center min-w-10 h-8 px-2 rounded-full
                     bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200 text-sm font-bold">
            {{ $count }}
        </span>
    </a>
@endforeach
</div>
