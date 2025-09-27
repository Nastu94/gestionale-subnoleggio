{{-- Component: Modale Create/Edit Renter (Organization)
     Path: resources/views/components/organization-create-modal.blade.php

     - Usa Alpine state del genitore (showModal, mode, form, validate()).
     - Create  → POST  /organizations
     - Edit    → PUT   /organizations/{id}
--}}
<div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden w-full max-w-xl p-6 z-10">
    {{-- Header modale --}}
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            <span x-text="mode === 'create' ? 'Nuovo renter' : 'Modifica renter'"></span>
        </h3>
        <button type="button"
                @click="showModal = false"
                class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- Form --}}
    <form
        x-bind:action="mode === 'create' ? '{{ route('organizations.store') }}' : '{{ url('organizations') }}/' + form.id"
        method="POST"
        @submit.prevent="if(validate()) $el.submit()"
    >
        @csrf
        <template x-if="mode === 'edit'">
            <input type="hidden" name="_method" value="PUT" />
        </template>

        {{-- Se vuoi fissare il tipo a 'renter' lato form: --}}
        <input type="hidden" name="type" value="renter">

        <div class="space-y-4">
            {{-- Nome --}}
            <div>
                <label for="org_name" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Nome renter</label>
                <input id="org_name"
                       name="name"
                       x-model="form.name"
                       type="text"
                       required
                       class="mt-1 block w-full px-3 py-2 border rounded-md
                              bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" />
                <p x-text="errors.name" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Qui in futuro potrai aggiungere altri campi: email, p.iva, telefono, note, ecc. --}}
        </div>

        <div class="mt-6 flex justify-end space-x-2">
            <button type="button"
                    @click="showModal = false"
                    class="px-4 py-1.5 text-xs font-medium rounded-md
                           bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-100
                           hover:bg-gray-300 dark:hover:bg-gray-500">
                Annulla
            </button>
            <button type="submit"
                    class="px-4 py-1.5 text-xs font-medium rounded-md
                           bg-indigo-600 text-white hover:bg-indigo-500">
                <span x-text="mode === 'create' ? 'Salva' : 'Aggiorna'"></span>
            </button>
        </div>
    </form>
</div>
