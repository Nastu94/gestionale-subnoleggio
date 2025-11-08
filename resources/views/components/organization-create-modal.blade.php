{{-- resources/views/components/organization-create-modal.blade.php --}}

{{-- Modale Create/Edit Renter + Utente principale --}}
<div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden w-full max-w-xl p-6 z-10">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            <span x-text="mode === 'create' ? 'Nuovo renter' : 'Modifica renter'"></span>
        </h3>
        <button type="button" @click="showModal = false"
                class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <form
        x-bind:action="mode === 'create' ? '{{ route('organizations.store') }}' : '{{ url('organizations') }}/' + form.id"
        method="POST"
        @submit.prevent="/* validazione client minima + submit */ $el.submit()"
    >
        @csrf
        <template x-if="mode === 'edit'">
            <input type="hidden" name="_method" value="PUT" />
        </template>

        {{-- organization_id per update --}}
        <template x-if="mode === 'edit' && form.user_id">
            <input type="hidden" name="user_id" x-bind:value="form.user_id" />
        </template>

        {{-- Forziamo type renter lato controller; nessun input 'type' necessario --}}

        <div class="space-y-5">
            {{-- RENTER --}}
            <fieldset class="space-y-2">
                <legend class="text-xs font-semibold text-gray-700 dark:text-gray-300">Renter</legend>

                {{-- Caso B: Creo solo user su renter esistente (mode=create & ho form.id) --}}
                <template x-if="mode === 'create' && form.id">
                    <div>
                        {{-- Importante: essendo l'input di display disabilitato, inviamo un hidden con l'id --}}
                        <input type="hidden" name="organization_id" x-bind:value="form.id" />
                        <label class="block text-xs text-gray-600 dark:text-gray-300">Renter selezionato</label>
                        <input type="text" x-model="form.name" disabled
                            class="mt-1 block w-full px-3 py-2 border rounded-md
                                    bg-gray-100 dark:bg-gray-700/60 text-sm text-gray-700 dark:text-gray-300" />
                        <p class="text-[11px] text-gray-500 mt-1">Verrà creato un nuovo utente per questo renter.</p>
                    </div>
                </template>

                {{-- Caso A: Creo nuovo renter + user (mode=create senza form.id) oppure edit renter --}}
                <template x-if="!(mode === 'create' && form.id)">
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300">Nome renter</label>
                        <input
                            name="name"
                            x-model="form.name"
                            type="text"
                            :required="mode === 'create'"     {{-- in create è obbligatorio --}}
                            :readonly="mode === 'edit'"        {{-- in edit è SOLA LETTURA --}}
                            class="mt-1 block w-full px-3 py-2 border rounded-md
                                bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                            :class="mode === 'edit' ? 'bg-gray-100 dark:bg-gray-700/60 cursor-not-allowed' : ''"
                            aria-readonly="true"
                        />
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </template>
            </fieldset>

            {{-- USER PRINCIPALE --}}
            <fieldset class="space-y-2">
                <legend class="text-xs font-semibold text-gray-700 dark:text-gray-300">Utente principale</legend>

                <label class="block text-xs text-gray-600 dark:text-gray-300">Nome</label>
                <input name="user_name" x-model="form.user_name" type="text" :required="mode==='create'"
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" />
                @error('user_name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror

                <label class="block text-xs text-gray-600 dark:text-gray-300 mt-3">Email</label>
                <input name="user_email" x-model="form.user_email" type="email" :required="mode==='create'"
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" />
                @error('user_email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300">Password</label>
                        <input name="user_password" x-model="form.user_password" type="password" :required="mode==='create'"
                            class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" />
                        @error('user_password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300">Conferma Password</label>
                        <input name="user_password_confirmation" x-model="form.user_password_confirmation" type="password" :required="mode==='create'"
                            class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" />
                    </div>
                </div>
            </fieldset>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <button type="button" @click="showModal = false"
                class="px-4 py-1.5 text-xs font-medium rounded-md bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-500">
                Annulla
            </button>
            <button type="submit"
                class="px-4 py-1.5 text-xs font-medium rounded-md bg-indigo-600 text-white hover:bg-indigo-500">
                <span x-text="mode === 'create' ? 'Salva' : 'Aggiorna'"></span>
            </button>
        </div>
    </form>
</div>
