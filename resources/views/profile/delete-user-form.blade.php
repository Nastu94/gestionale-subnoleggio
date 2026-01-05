<x-action-section>
    <x-slot name="title">
        Elimina account
    </x-slot>

    <x-slot name="description">
        Elimina definitivamente il tuo account.
    </x-slot>

    <x-slot name="content">
        <div class="max-w-xl text-sm text-gray-600">
            Una volta eliminato il tuo account, tutte le risorse e i dati verranno cancellati definitivamente.
            Prima di procedere, scarica eventuali dati o informazioni che desideri conservare.
        </div>

        <div class="mt-5">
            <x-danger-button wire:click="confirmUserDeletion" wire:loading.attr="disabled">
                Elimina account
            </x-danger-button>
        </div>

        <!-- Modale di conferma eliminazione utente -->
        <x-dialog-modal wire:model.live="confirmingUserDeletion">
            <x-slot name="title">
                Elimina account
            </x-slot>

            <x-slot name="content">
                Sei sicuro di voler eliminare il tuo account? Una volta eliminato, tutte le risorse e i dati verranno
                cancellati definitivamente. Inserisci la tua password per confermare che desideri eliminare definitivamente
                il tuo account.

                <div class="mt-4" x-data="{}" x-on:confirming-delete-user.window="setTimeout(() => $refs.password.focus(), 250)">
                    <x-input type="password"
                             class="mt-1 block w-3/4"
                             autocomplete="current-password"
                             placeholder="Password"
                             x-ref="password"
                             wire:model="password"
                             wire:keydown.enter="deleteUser" />

                    <x-input-error for="password" class="mt-2" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('confirmingUserDeletion')" wire:loading.attr="disabled">
                    Annulla
                </x-secondary-button>

                <x-danger-button class="ms-3" wire:click="deleteUser" wire:loading.attr="disabled">
                    Elimina account
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
    </x-slot>
</x-action-section>
