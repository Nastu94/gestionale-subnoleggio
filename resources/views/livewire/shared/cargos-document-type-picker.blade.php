{{-- resources/views/livewire/shared/cargos-document-type-picker.blade.php --}}
<div class="space-y-4">
    <h3 class="text-sm font-semibold mb-1 text-gray-900 dark:text-gray-100">{{ $title }}</h3>
    @if($hint)
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ $hint }}</p>
    @endif

    <div>
        <label class="text-xs text-gray-600 dark:text-gray-300">Tipo documento (CARGOS)</label>

        <select
            wire:model.defer="value"
            @disabled($disabled)
            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 w-full"
        >
            <option value="">—</option>
            @foreach($options as $opt)
                <option value="{{ $opt['code'] }}">{{ $opt['label'] }} ({{ $opt['code'] }})</option>
            @endforeach
        </select>
    </div>
</div>
