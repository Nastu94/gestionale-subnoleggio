<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Modifica Renter (Organization + User principale).
 */
class OrganizationUpdateRequest extends FormRequest
{
    /** Solo admin */
    public function authorize(): bool
    {
        return Gate::allows('manage.renters');
    }

    /** Regole di validazione. Tutti i campi sono opzionali, ma se presenti devono essere validi.
     * NB: non usiamo 'sometimes' perché non funziona bene con i campi 'array'.
     */
    public function rules(): array
    {
        /** @var Organization|null $org */
        $org = $this->route('organization');

        // NB: user_id può non essere inviato; in quel caso il controller sceglie un utente dell’org.
        $userId = $this->input('user_id');

        return [
            // ORGANIZATION
            'name' => [
                'required','sometimes','string','min:2','max:150',
                Rule::unique('organizations','name')
                    ->where(fn($q) => $q->where('type','renter'))
                    ->ignore($org?->id),
                Rule::in([$org?->name]),
            ],

            // USER (tutti opzionali; aggiorniamo solo quelli presenti)
            'user_id'    => ['required','integer','exists:users,id'],
            'user_name'  => ['required','string','min:2','max:150'],
            'user_email' => [
                'required','email','max:255',
                Rule::unique('users','email')->ignore($userId),
            ],
            'user_password' => ['required','confirmed', Password::defaults()],
        ];
    }

    /** 
     * Messaggi di errore personalizzati.
     * NB: i messaggi per i campi opzionali (nullable) si attivano solo se il campo è presente.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Il nome del renter è obbligatorio.',
            'name.unique'   => 'Esiste già un renter con questo nome.',
            'name.in' => 'Il nome del renter non è modificabile.',

            'user_email.email'  => 'Formato email non valido.',
            'user_email.unique' => 'Esiste già un utente con questa email.',
            'user_password.confirmed' => 'Le password non corrispondono.',
        ];
    }
}
