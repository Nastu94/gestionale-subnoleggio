<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creazione Renter + User OPPURE Solo User su Renter esistente.
 */
class OrganizationStoreRequest extends FormRequest
{
    /** Solo admin */
    public function authorize(): bool
    {
        return Gate::allows('manage.renters');
    }

    public function rules(): array
    {
        return [
            // Se creo un nuovo renter, 'name' è obbligatorio;
            // se allego user a renter esistente, è obbligatorio 'organization_id'.
            'name' => [
                'required_without:organization_id', 'nullable', 'string', 'min:2', 'max:150',
                Rule::unique('organizations', 'name')
                    ->where(fn($q) => $q->where('type', 'renter')),
            ],
            'organization_id' => [
                'required_without:name', 'nullable', 'integer',
                // L'id deve esistere ed essere di tipo 'renter'
                Rule::exists('organizations', 'id')->where(fn($q) => $q->where('type', 'renter')),
            ],

            // USER (account principale / nuovo account)
            'user_name'  => ['required','string','min:2','max:150'],
            'user_email' => ['required','email','max:255','unique:users,email'],
            'user_password' => ['required','confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required_without'           => 'Inserisci il nome del renter oppure seleziona un renter esistente.',
            'organization_id.required_without'=> 'Seleziona un renter esistente oppure inserisci il nome del nuovo renter.',
            'name.unique'                     => 'Esiste già un renter con questo nome.',

            'user_name.required'              => 'Il nome utente è obbligatorio.',
            'user_email.required'             => 'L’email è obbligatoria.',
            'user_email.email'                => 'Formato email non valido.',
            'user_email.unique'               => 'Esiste già un utente con questa email.',
            'user_password.required'          => 'La password è obbligatoria.',
            'user_password.confirmed'         => 'Le password non corrispondono.',
        ];
    }
}
