{{-- resources/views/pages/rentals/partials/actions.blade.php --}}
{{-- 
    Azioni stato via fetch() + toast globale:
    - Intercettiamo il submit con Alpine (x-on:submit.prevent).
    - POST verso le tue rotte; i controller continuano a restituire JSON.
    - All'esito lanciamo window.dispatchEvent(new CustomEvent('toast', ...))
      che il tuo layout già intercetta per mostrare le notifiche.
    - Poi invochiamo $wire.$refresh() per aggiornare il componente Livewire.
--}}
<div x-data="rentalActions()" class="space-y-3">

    {{-- Checkout --}}
    <form method="POST" action="{{ route('rentals.checkout', $rental) }}" data-phase="checked_out" class="space-y-2" x-on:submit.prevent="submit($el)">
        @csrf
        {{-- Checkout: primary pieno --}}
        <button class="btn btn-primary btn-block shadow-none
                    !bg-primary !text-primary-content !border-primary
                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                    disabled:opacity-50 disabled:cursor-not-allowed"
                @disabled(!in_array($rental->status,['draft','reserved']))
                x-bind:disabled="loading">
            <span x-show="!loading">Checkout</span>
            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
        </button>
    </form>

    {{-- Passa a In-use --}}
    <form method="POST" action="{{ route('rentals.inuse', $rental) }}" data-phase="in_use" class="space-y-2" x-on:submit.prevent="submit($el)">
        @csrf
        {{-- Passa a In-use: info pieno (via, niente outline) --}}
        <button class="btn btn-info btn-block shadow-none
                    !bg-info !text-info-content !border-info
                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-info/30
                    disabled:opacity-50 disabled:cursor-not-allowed"
                @disabled($rental->status!=='checked_out')
                x-bind:disabled="loading">
            <span x-show="!loading">Passa a In-use</span>
            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
        </button>
    </form>

    {{-- Check-in --}}
    <form method="POST" action="{{ route('rentals.checkin', $rental) }}" data-phase="checked_in" class="space-y-2" x-on:submit.prevent="submit($el)">
        @csrf
        {{-- Check-in: accent pieno --}}
        <button class="btn btn-accent btn-block shadow-none
                    !bg-accent !text-accent-content !border-accent
                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-accent/30
                    disabled:opacity-50 disabled:cursor-not-allowed"
                @disabled(!in_array($rental->status,['checked_out','in_use']))
                x-bind:disabled="loading">
            <span x-show="!loading">Check-in</span>
            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
        </button>
    </form>

    {{-- Chiudi --}}
    <form method="POST" action="{{ route('rentals.close', $rental) }}" data-phase="closed" class="space-y-2" x-on:submit.prevent="submit($el)">
        @csrf
        {{-- Chiudi: success pieno --}}
        <button class="btn btn-success btn-block shadow-none
                    !bg-success !text-success-content !border-success
                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-success/30
                    disabled:opacity-50 disabled:cursor-not-allowed"
                @disabled($rental->status!=='checked_in')
                x-bind:disabled="loading">
            <span x-show="!loading">Chiudi</span>
            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
        </button>
    </form>

    <div class="grid grid-cols-2 gap-2">
        {{-- Cancella --}}
        <form method="POST" action="{{ route('rentals.cancel', $rental) }}" data-phase="cancelled" x-on:submit.prevent="submit($el)">
            @csrf
            {{-- Cancella: error pieno --}}
            <button class="btn btn-error btn-block shadow-none
                        !bg-error !text-error-content !border-error
                        hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-error/30
                        disabled:opacity-50 disabled:cursor-not-allowed"
                    @disabled(!in_array($rental->status,['draft','reserved']))
                    x-bind:disabled="loading">
                <span x-show="!loading">Cancella</span>
                <span x-show="loading" class="loading loading-spinner loading-sm"></span>
            </button>
        </form>

        {{-- No-show --}}
        <form method="POST" action="{{ route('rentals.noshow', $rental) }}" data-phase="no_show" x-on:submit.prevent="submit($el)">
            @csrf
            {{-- No-show: warning pieno --}}
            <button class="btn btn-warning btn-block shadow-none
                        !bg-warning !text-warning-content !border-warning
                        hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-warning/30
                        disabled:opacity-50 disabled:cursor-not-allowed"
                    @disabled($rental->status!=='reserved')
                    x-bind:disabled="loading">
                <span x-show="!loading">No-show</span>
                <span x-show="loading" class="loading loading-spinner loading-sm"></span>
            </button>
        </form>
    </div>
</div>

{{-- Alpine helper: fetch POST + toast globale + refresh Livewire --}}
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('rentalActions', () => ({
        loading: false,

        // Mappa "fase" → etichetta leggibile (non cambiamo i tuoi stati)
        phaseLabels: {
            checked_out: 'Check-out (veicolo consegnato)',
            in_use:      'In uso',
            checked_in:  'Check-in (veicolo rientrato)',
            closed:      'Chiusura contratto',
            cancelled:   'Annullamento',
            canceled:    'Annullamento', // eventuale variante
            no_show:     'No-show',
        },

        // Ritorna l’etichetta umana; fallback: sostituisce underscore con spazio
        phaseLabel(key) { return this.phaseLabels[key] ?? key.replace(/_/g, ' '); },

        /**
         * submit(formEl)
         * - Invia il form via fetch() come POST AJAX.
         * - Imposta Accept: application/json per ottenere JSON dai controller.
         * - Mostra toast globali usando l'evento window "toast" che il layout ascolta.
         * - Aggiorna il componente Livewire (v3 e fallback v2).
         */
        async submit(formEl) {
            if (this.loading) return;
            // Conferma con testo umano
            const phase   = formEl.dataset.phase || null;
            const human   = phase ? this.phaseLabel(phase) : null;
            const message = human
                ? `Vuoi passare alla fase ${human}?`
                : 'Confermi l’operazione?';
            if (!window.confirm(message)) return;
            this.loading = true;

            try {
                const token =
                    formEl.querySelector('input[name=_token]')?.value ||
                    document.querySelector('meta[name=csrf-token]')?.content;

                const res = await fetch(formEl.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: new FormData(formEl),
                    credentials: 'same-origin',
                });

                let data = null;
                try { data = await res.clone().json(); } catch (_) { /* risposta non JSON */ }

                // Esito negativo (HTTP non ok o payload {ok:false})
                if (!res.ok || (data && data.ok === false)) {
                    const message =
                        (data && (data.message || data.error)) ||
                        (res.status === 419 ? 'Sessione scaduta. Ricarica la pagina.' :
                         res.status === 403 ? 'Permesso negato.' :
                         res.status === 422 ? 'Dati non validi o prerequisiti mancanti.' :
                         'Operazione non riuscita.');
                    window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message } }));
                    return;
                }

                // Esito positivo
                const statusText = data?.status ? `: ${data.status}` : '';
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'success', message: `Stato aggiornato${statusText}` }
                }));

                // Refresh Livewire (v3) + fallback (v2)
                try { this.$wire?.$refresh(); } catch(_) {}
                try { window.Livewire?.emit?.('refresh'); } catch(_) {}

            } catch (e) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'error', message: 'Errore di rete o risposta non valida.' }
                }));
            } finally {
                this.loading = false;
            }
        },
    }));
});
</script>
