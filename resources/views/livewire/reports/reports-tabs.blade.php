{{-- resourcers/views/livewire/reports/reports-tabs.blade.php --}}
<div class="space-y-6">
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap gap-2">
            @foreach ($tabs as $tab)
                <button
                    type="button"
                    wire:key="report-tab-{{ $tab['key'] }}"
                    wire:click="setActiveTab('{{ $tab['key'] }}')"
                    @class([
                        'rounded px-3 py-1 ring-1 ring-slate-300',
                        'bg-slate-800 text-white' => $activeTab === $tab['key'],
                        'bg-white text-slate-700' => $activeTab !== $tab['key'],
                    ])
                >
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        @if ($activeTab === 'run-saved-preset')
            <livewire:reports.run-saved-preset />
        @elseif ($activeTab === 'create-report-preset')
            <livewire:reports.create-report-preset />
        @elseif ($activeTab === 'edit-report-preset')
            <livewire:reports.edit-report-preset />
        @elseif ($activeTab === 'run-ad-hoc-report')
            <livewire:reports.run-ad-hoc-report />
        @endif
    </div>
</div>