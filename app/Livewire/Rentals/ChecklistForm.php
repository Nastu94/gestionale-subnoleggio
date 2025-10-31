<?php

namespace App\Livewire\Rentals;

use App\Models\Rental;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Form Livewire per creare/modificare la checklist.
 * In questo step aggiungiamo:
 * - Sezione Danni con blocchi dinamici
 * - Upload media temporanei (odometro, livello carburante, esterni, danni)
 */
class ChecklistForm extends Component
{
    use AuthorizesRequests;
    use WithFileUploads; // Necessario per gli upload Livewire (tmp)

    /** Istanza del noleggio corrente */
    public Rental $rental;

    /** Tipo checklist: 'pickup' | 'return' (derivato da query) */
    public ?string $type = null;

    /** Campi scalar aderenti alla tabella `rental_checklists` */
    public ?int $mileage = null;
    public ?int $fuel_percent = 0;
    public ?string $cleanliness = null;
    public bool $signed_by_customer = false;
    public bool $signed_by_operator = false;

    /** Placeholder (non usato in questo step) */
    public ?string $signature_media_uuid = null;

    /** JSON dinamico della checklist (schema v1 definito nello step precedente) */
    public array $checklist = [];

    /** Km attuali del veicolo (per validazione chilometraggio) */
    public ?int $current_vehicle_mileage = null;

    /**
     * Danni inseriti dall'utente (UI → salveremo poi su `rental_damages`).
     * Ogni item:
     * - area: string (es. "paraurti posteriore dx")
     * - description: string
     * - severity: minor|moderate|major
     * - preexisting: bool (true = danno preesistente)
     * - photos_files: array di TemporaryUploadedFile (Livewire tmp)
     */
    public array $damage_items = [];

    /**
     * Foto checklist (Livewire tmp). In questo step **non** persistiamo:
     * - odometer_file: foto del contachilometri
     * - fuel_gauge_file: foto dell'indicatore carburante
     * - exterior_files: array foto carrozzeria
     */
    public array $checklist_photos = [
        'odometer_file'   => null,
        'fuel_gauge_file' => null,
        'exterior_files'  => [],
    ];

    /** Regole di validazione */
    protected function rules(): array
    {
        return [
            // --- campi checklist table ---
            'type'                => ['required', 'in:pickup,return'],
            'mileage'             => ['nullable', 'integer', 'min:' . ($this->current_vehicle_mileage ?? 0), 'max:2000000'],
            'fuel_percent'        => ['nullable', 'integer', 'min:0', 'max:100'],
            'cleanliness'         => ['nullable', 'in:poor,fair,good,excellent'],
            'signed_by_customer'  => ['boolean'],
            'signed_by_operator'  => ['boolean'],
            'signature_media_uuid'=> ['nullable', 'string', 'max:64'],

            // --- JSON schema v1 (chiavi principali) ---
            'checklist'                                         => ['array'],
            'checklist.schema_version'                          => ['required', 'in:1.0'],
            'checklist.keys.count'                              => ['nullable', 'integer', 'min:0', 'max:5'],
            'checklist.keys.spare_key_present'                  => ['boolean'],
            'checklist.documents.registration_present'          => ['boolean'],
            'checklist.documents.insurance_card_present'        => ['boolean'],
            'checklist.documents.roadside_assistance_present'   => ['boolean'],
            'checklist.equipment.triangle'                      => ['boolean'],
            'checklist.equipment.reflective_vest'               => ['boolean'],
            'checklist.equipment.first_aid_kit'                 => ['boolean'],
            'checklist.equipment.spare_wheel'                   => ['boolean'],
            'checklist.equipment.tyre_repair_kit'               => ['boolean'],
            'checklist.equipment.jack'                          => ['boolean'],
            'checklist.tyres.season'                            => ['nullable', 'in:summer,winter,all-season'],
            'checklist.tyres.pressure_ok'                       => ['boolean'],
            'checklist.tyres.tread_ok'                          => ['boolean'],
            'checklist.electronics.lights_ok'                   => ['boolean'],
            'checklist.electronics.turn_indicators_ok'          => ['boolean'],
            'checklist.electronics.brake_lights_ok'             => ['boolean'],
            'checklist.electronics.horn_ok'                     => ['boolean'],
            'checklist.windows.windshield_chips'                => ['boolean'],
            'checklist.windows.wipers_ok'                       => ['boolean'],
            'checklist.interior.mats_present'                   => ['boolean'],
            'checklist.interior.cigarette_burns'                => ['boolean'],
            'checklist.fuel_cap_present'                        => ['boolean'],
            'checklist.warning_lights'                          => ['array'],
            'checklist.warning_lights.*'                        => ['string', 'max:50'],
            'checklist.notes'                                   => ['nullable', 'string', 'max:500'],

            // --- Danni dinamici ---
            'damage_items'                                      => ['nullable','array'],
            'damage_items.*.area'                               => ['required','string','max:100'],
            'damage_items.*.description'                        => ['nullable','string','max:500'],
            'damage_items.*.severity'                           => ['required','in:minor,moderate,major'],
            'damage_items.*.preexisting'                        => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'mileage.min'              => __('Il chilometraggio non può essere inferiore ai km attuali del veicolo (:min km).'),
            'damage_items.*.area.required'     => __('Specificare l’area del danno.'),
            'damage_items.*.severity.in'       => __('La gravità deve essere: minor, moderate o major.'),
            'damage_items.*.photos_files.*.image' => __('Le foto del danno devono essere immagini.'),
        ];
    }

    public function mount(Rental $rental, ?string $type = null): void
    {
        $this->authorize('checklist.update', $rental);
        $this->rental = $rental;
        $this->type = in_array($type, ['pickup', 'return'], true) ? $type : 'pickup';

        // Km attuali del veicolo per validazione del chilometraggio
        $vehicle = $rental->vehicle ?? null;
        $this->current_vehicle_mileage =
            $vehicle->mileage_current ?? null;

        if ($this->fuel_percent === null) {
            $this->fuel_percent = 0;
        }

        // Default JSON v1 se assente (come definito nello step precedente)
        if (empty($this->checklist)) {
            $this->checklist = [
                'schema_version' => '1.0',
                'keys' => ['count' => 1, 'spare_key_present' => false],
                'documents' => [
                    'registration_present' => true,
                    'insurance_card_present' => true,
                    'roadside_assistance_present' => false,
                ],
                'equipment' => [
                    'triangle' => false, 'reflective_vest' => false, 'first_aid_kit' => false,
                    'spare_wheel' => false, 'tyre_repair_kit' => false, 'jack' => false,
                ],
                'tyres' => ['season' => null, 'pressure_ok' => true, 'tread_ok' => true],
                'electronics' => [
                    'lights_ok' => true, 'turn_indicators_ok' => true,
                    'brake_lights_ok' => true, 'horn_ok' => true,
                ],
                'windows' => ['windshield_chips' => false, 'wipers_ok' => true],
                'interior' => ['mats_present' => true, 'cigarette_burns' => false],
                'fuel_cap_present' => true,
                'warning_lights' => [],
                'notes' => null,
                'photos' => [
                    // In questo step usiamo i file tmp; gli UUID arriveranno nello step di salvataggio
                    'odometer_media_uuid' => null,
                    'fuel_gauge_media_uuid' => null,
                    'exterior_media_uuids' => [],
                ],
            ];
        }
    }

    /** Aggiunge un nuovo blocco danno (vuoto) alla collezione UI */
    public function addDamage(): void
    {
        $this->damage_items[] = [
            'area' => '',
            'description' => '',
            'severity' => 'minor',   // default prudente
            'preexisting' => false,
            'photos_files' => [],    // array di TemporaryUploadedFile
        ];
    }

    /** Rimuove il blocco danno all'indice $index */
    public function removeDamage(int $index): void
    {
        if (isset($this->damage_items[$index])) {
            unset($this->damage_items[$index]);
            // Reindicizza gli indici per non rompere il binding
            $this->damage_items = array_values($this->damage_items);
        }
    }

    /** Solo validazione per questo step */
    public function submit(): void
    {
        if($validated = $this->validate()){
            $this->dispatch('toast', type: 'success', message: 'Checklist validata con successo. Procedi al salvataggio.');
        } else {
            $this->dispatch('toast', type: 'error', message: 'Ci sono errori nel modulo. Controlla i campi evidenziati.');
        }
    }

    public function render()
    {
        return view('livewire.rentals.checklist-form');
    }
}
