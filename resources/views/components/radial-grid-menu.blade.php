{{-- resources/views/components/radial-grid-menu-v16.blade.php --}}
@php
    use Illuminate\Support\Facades\Gate;

    /* ------- 1. Sezioni visibili in base ai permessi ------------------- */
    $visibleSections = collect(config('menu.grid_menu'))
        ->filter(fn($s)=>Gate::any(collect($s['items'])->pluck('permission')->all()))
        ->values();

    /* ------- 2. Offset radiale (max 8) -------------------------------- */
    $offsets = [
        ['x'=> 0,'y'=>-90], ['x'=> 90,'y'=>  0],
        ['x'=> 0,'y'=> 90], ['x'=>-90,'y'=>  0],
        ['x'=> 60,'y'=>-60],['x'=> 60,'y'=> 60],
        ['x'=>-60,'y'=> 60],['x'=>-60,'y'=>-60],
    ];
@endphp

@once
@push('scripts')
<script>
/* ---------- helper: antenato scrollabile + centratura ----------------- */
function scrollParent(el){
    for(let p=el.parentElement;p&&p!==document.body;p=p.parentElement){
        const s=getComputedStyle(p);
        if(/(auto|scroll)/.test(s.overflow+s.overflowY+s.overflowX)) return p;
    }
    return document.scrollingElement||document.documentElement;
}
function centerTile(el){
    const par   = scrollParent(el);
    const rect  = el.getBoundingClientRect();
    const prect = par.getBoundingClientRect();
    const target = par.scrollTop + rect.top - prect.top - (par.clientHeight/2) + (rect.height/2);
    par.scrollTo({top: target, behavior:'smooth'});
}
</script>
@endpush
@endonce

<div
    x-data="menuGrid()"
    x-init="init()"
    class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3
           gap-x-10 overflow-visible transition-all duration-100"
    :style="gridStyle()"
    x-cloak
>
@foreach($visibleSections as $i=>$section)
    <div x-ref="tile{{ $i }}" class="relative flex justify-center">

        {{-- pulsante macro-modulo --}}
        <button
            :data-row="Math.floor({{ $i }} / columns)"
            @click.stop="toggle({{ $i }})"
            class="w-28 h-28 bg-white dark:bg-gray-800 rounded-lg shadow
                   flex flex-col items-center justify-center
                   hover:bg-indigo-50 dark:hover:bg-indigo-900
                   transition-all duration-100">
            <i class="fas {{ $section['icon'] }} text-3xl text-indigo-600 dark:text-indigo-400"></i>
            <span class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200">
                {{ $section['section'] }}
            </span>
        </button>

        {{-- menu radiale --}}
        <template x-if="openKey === {{ $i }}">
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none
                        z-30" x-cloak
                 @click.outside="openKey=null;openRow=null">
                @php
                    $items = collect($section['items'])
                             ->filter(fn($it)=>auth()->user()->can($it['permission']))
                             ->values()->take(8);
                @endphp
                @foreach($items as $k=>$item)
                    @php $o=$offsets[$k]; @endphp
                    <a href="{{ route($item['route']) }}"
                       class="absolute w-14 h-14 bg-white dark:bg-gray-800 rounded-full shadow-lg z-40
                              flex flex-col items-center justify-center pointer-events-auto
                              hover:scale-110 transition"
                       style="left:50%;top:50%;
                              transform:translate(-50%,-50%) translate({{$o['x']}}px,{{$o['y']}}px);">
                        <i class="fas {{ $item['icon'] }} text-lg"></i>
                        <span class="text-xs whitespace-nowrap">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </template>

    </div>
@endforeach
</div>

@push('scripts')
<script>
function getCols(){return window.innerWidth>=768?3:window.innerWidth>=640?2:1}

function menuGrid(){
return{
    openKey:null,openRow:null,columns:getCols(),
    init(){window.addEventListener('resize',()=>this.columns=getCols())},

    totalRows(){return Math.ceil({{ $visibleSections->count() }} / this.columns)},

    gridStyle () {
        const hasOpen = this.openKey !== null;

        const gap = hasOpen ? '8rem' : '2.5rem';
        const top = (hasOpen && this.openRow === 0) ? '6rem' : '0';
        const bot = (hasOpen && this.openRow === this.totalRows() - 1) ? '6rem' : '0';

        return `row-gap:${gap}; padding-top:${top}; padding-bottom:${bot}`;
    },

    toggle(idx){
        const row=Math.floor(idx/this.columns);

        this.openKey=this.openKey===idx?null:idx;
        this.openRow=this.openKey!==null?row:null;

        if(this.openKey!==null){
            /* aspetta il repaint + transizione (300ms) → 350ms total */
            this.$nextTick(()=>{
                setTimeout(()=>{
                    const el=this.$refs['tile'+idx];
                    if(el) centerTile(el);
                },150);
            });
        }
    }
}};
</script>
@endpush
