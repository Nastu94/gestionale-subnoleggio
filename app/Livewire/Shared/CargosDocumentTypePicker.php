<?php

namespace App\Livewire\Shared;

use App\Models\CargosDocumentType;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * Picker riutilizzabile per tipi documento CARGOS (cargos_document_types)
 *
 * - value = code (string) es. IDENT, PASOR, PATEN...
 * - only = lista codici ammessi (opzionale)
 * - exclude = lista codici esclusi (opzionale)
 */
class CargosDocumentTypePicker extends Component
{
    #[Modelable]
    public ?string $value = null;

    public string $title = 'Tipo documento';
    public ?string $hint = null;

    public bool $disabled = false;

    /** @var array<int, string> */
    public array $only = [];

    /** @var array<int, string> */
    public array $exclude = [];

    public array $options = []; // [['code'=>'...', 'label'=>'...'], ...]

    public function mount(): void
    {
        $q = CargosDocumentType::query()->where('is_active', 1)->orderBy('label');

        if (!empty($this->only)) {
            $q->whereIn('code', $this->only);
        }

        if (!empty($this->exclude)) {
            $q->whereNotIn('code', $this->exclude);
        }

        $this->options = $q->get(['code', 'label'])->toArray();
    }

    public function render()
    {
        return view('livewire.shared.cargos-document-type-picker');
    }
}
