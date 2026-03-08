<?php

namespace App\Livewire\Reports;

use Livewire\Component;

/**
 * Componente padre per la sezione report.
 *
 * Responsabilità:
 * - gestire esclusivamente la navigazione tra le tab;
 * - demandare la logica operativa ai componenti Livewire figli;
 * - mantenere la pagina reports ordinata e modulare.
 */
class ReportsTabs extends Component
{
    /**
     * Tab attualmente attiva.
     */
    public string $activeTab = 'run-saved-preset';

    /**
     * Elenco tab disponibili.
     *
     * @var array<int, array<string, string>>
     */
    public array $tabs = [
        [
            'key' => 'run-saved-preset',
            'label' => 'Lancia preset salvato',
        ],
        [
            'key' => 'create-report-preset',
            'label' => 'Crea preset',
        ],
        [
            'key' => 'edit-report-preset',
            'label' => 'Modifica preset',
        ],
        [
            'key' => 'run-ad-hoc-report',
            'label' => 'Statistica senza salvataggio',
        ],
    ];

    /**
     * Imposta la tab attiva.
     */
    public function setActiveTab(string $tab): void
    {
        $availableTabs = collect($this->tabs)
            ->pluck('key')
            ->all();

        if (! in_array($tab, $availableTabs, true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    /**
     * Renderizza la view del componente.
     */
    public function render()
    {
        return view('livewire.reports.reports-tabs');
    }
}