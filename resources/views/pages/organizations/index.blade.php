{{-- Organizations ▸ Index (Gestione Renter) --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Renter (Organizzazioni)') }}
            </h2>
        </div>

        {{-- Flash messages --}}
        @if (session('success'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 8000)" x-show="show"
                 x-transition.opacity.duration.400ms
                 class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mt-2"
                 role="alert">
                <i class="fas fa-check-circle mr-2"></i>
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 8000)" x-show="show"
                 x-transition.opacity.duration.400ms
                 class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-2"
                 role="alert">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif
    </x-slot>

    {{-- Stato Alpine per il modale; riceve eventi dal componente Livewire --}}
    <div class="py-6"
        x-data="{
            showModal:false, mode:'create',
            // id = organization_id; se presente in 'create' ⇒ significa 'aggiungi utente al renter esistente'
            form:{ id:null, name:'', user_id:null, user_name:'', user_email:'', user_password:'', user_password_confirmation:'' },

            openCreate(){
                this.mode='create';
                this.form={ id:null, name:'', user_id:null, user_name:'', user_email:'', user_password:'', user_password_confirmation:'' };
                this.showModal=true;
            },
            openEdit(org, user){
                this.mode='edit';
                this.form.id   = org.id;
                this.form.name = org.name;
                this.form.user_id    = user?.id ?? null;
                this.form.user_name  = user?.name ?? '';
                this.form.user_email = user?.email ?? '';
                this.form.user_password = '';
                this.form.user_password_confirmation = '';
                this.showModal=true;
            },
            // nuovo: crea SOLO utente su renter esistente
            openAddUser(org){
                this.mode='create';
                this.form.id   = org.id;     // id del renter esistente
                this.form.name = org.name;   // solo display
                this.form.user_id = null;
                this.form.user_name = '';
                this.form.user_email = '';
                this.form.user_password = '';
                this.form.user_password_confirmation = '';
                this.showModal = true;
            },
        }"
        @open-org-create.window="openCreate()"
        @open-org-edit.window="openEdit($event.detail.org, $event.detail.user)"
        @open-org-add-user.window="openAddUser($event.detail.org)"
        @keydown.escape.window="showModal=false">

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Componente Livewire della tabella --}}
            <livewire:organizations.table />

            {{-- Modale Create/Edit (riutilizzato) --}}
            <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black bg-opacity-75"></div>
                <div class="relative z-10 w-full max-w-xl">
                    <x-organization-create-modal />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
