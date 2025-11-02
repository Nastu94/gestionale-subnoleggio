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
    {{-- Registra Pagamento --}}
    <button class="btn btn-secondary btn-block shadow-none
                !bg-secondary !text-secondary-content !border-secondary
                hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-secondary/30
                disabled:opacity-50 disabled:cursor-not-allowed"
            x-bind:disabled="loading || {{ $rental->payment_recorded ? 'true' : 'false' }}"
            x-on:click="$dispatch('open-payment-modal')">
        <span x-show="!loading">Registra Pagamento</span>
        <span x-show="loading" class="loading loading-spinner loading-sm"></span>
    </button>

    {{-- Checkout --}}
    <form method="POST" action="{{ route('rentals.checkout', $rental) }}" data-phase="checked_out" class="space-y-2"
        x-on:submit.prevent="submit($el)">
        @csrf
        {{-- Checkout: primary pieno --}}
        <button
            class="btn btn-primary btn-block shadow-none
                    !bg-primary !text-primary-content !border-primary
                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                    disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="loading || !canCheckout()">
            <span x-show="!loading">Checkout</span>
            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
        </button>
    </form>

    {{-- Passa a In-use --}}
    <form method="POST" action="{{ route('rentals.inuse', $rental) }}" data-phase="in_use" class="space-y-2" x-on:submit.prevent="submit($el)">
        @csrf
        {{-- Passa a In-use: info pieno (via, niente outline) --}}
        <button
            class="btn btn-info btn-block shadow-none
                    !bg-info !text-info-content !border-info
                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-info/30
                    disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="loading || !canInuse()">
            <span x-show="!loading">Passa a In-use</span>
            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
        </button>
    </form>

    {{-- Check-in --}}
    <form method="POST" action="{{ route('rentals.checkin', $rental) }}" data-phase="checked_in" class="space-y-2" x-on:submit.prevent="submit($el)">
        @csrf
        {{-- Check-in: accent pieno --}}
        <button
            class="btn btn-accent btn-block shadow-none
                    !bg-accent !text-accent-content !border-accent
                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-accent/30
                    disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="loading || !canCheckin()">
            <span x-show="!loading">Check-in</span>
            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
        </button>
    </form>

    {{-- Chiudi --}}
    <form method="POST" action="{{ route('rentals.close', $rental) }}" data-phase="closed" class="space-y-2" x-on:submit.prevent="submit($el)">
        @csrf
        {{-- Chiudi: success pieno --}}
        <button
            class="btn btn-success btn-block shadow-none
                    !bg-success !text-success-content !border-success
                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-success/30
                    disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="loading || !canClose()">
            <span x-show="!loading">Chiudi</span>
            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
        </button>
    </form>

    <div class="grid grid-cols-2 gap-2">
        {{-- Cancella --}}
        <form method="POST" action="{{ route('rentals.cancel', $rental) }}" data-phase="cancelled" x-on:submit.prevent="submit($el)">
            @csrf
            {{-- Cancella: error pieno --}}
            <button
                class="btn btn-error btn-block shadow-none
                        !bg-error !text-error-content !border-error
                        hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-error/30
                        disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="loading || !canCancel()">
                <span x-show="!loading">Cancella</span>
                <span x-show="loading" class="loading loading-spinner loading-sm"></span>
            </button>
        </form>

        {{-- No-show --}}
        <form method="POST" action="{{ route('rentals.noshow', $rental) }}" data-phase="no_show" x-on:submit.prevent="submit($el)">
            @csrf
            {{-- No-show: warning pieno --}}
            <button
                class="btn btn-warning btn-block shadow-none
                        !bg-warning !text-warning-content !border-warning
                        hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-warning/30
                        disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="loading || !canNoShow()">
                <span x-show="!loading">No-show</span>
                <span x-show="loading" class="loading loading-spinner loading-sm"></span>
            </button>
        </form>
    </div>
</div>

{{-- Modale per registrare il pagamento (teleport nel <body> per evitare z-index/stacking issues) --}}
<div
    x-data="paymentModal('{{ route('rentals.record_payment', $rental) }}', {{ (float)($rental->amount ?? 0) }})"
    x-on:open-payment-modal.window="openModal($event.detail)"
>
    <template x-teleport="body">
        <div
            x-show="open"
            x-transition.opacity
            class="fixed inset-0 z-[95] flex items-center justify-center bg-black/50 px-4"
            role="dialog" aria-modal="true" aria-labelledby="payment-modal-title"
            @keydown.escape.prevent.stop="close()"
        >
            {{-- backdrop cliccabile per chiudere --}}
            <div class="absolute inset-0" @click="close()"></div>

            {{-- contenuto --}}
            <div
                x-show="open"
                x-transition.scale
                class="relative bg-base-100 rounded-lg shadow-xl w-full max-w-md p-6"
            >
                <h2 id="payment-modal-title" class="text-lg font-semibold mb-4">Registra Pagamento</h2>

                {{-- Submit AJAX: niente navigazione --}}
                <form x-on:submit.prevent="submit()">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="label"><span class="label-text">Importo Pagato</span></label>
                            <input type="number" step="0.01" min="0"
                                   x-model.number="amount"
                                   name="amount"
                                   class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                                    focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400
                                    dark:bg-gray-800 dark:border-gray-700" required>
                        </div>

                        <div>
                            <label class="label"><span class="label-text">Metodo di Pagamento</span></label>
                            <select 
                                x-model="payment_method" name="payment_method" 
                                class="mt-1 w-full rounded-md border-gray-300 shadow-sm appearance-none pr-8 focus:border-indigo-500 focus:ring-indigo-500" 
                                required>
                                <option value="" disabled>Seleziona metodo</option>
                                <option value="cash">Contanti</option>
                                <option value="pos">Carta di Credito</option>
                                <option value="bank_transfer">Bonifico Bancario</option>
                                <option value="other">Altro</option>
                            </select>
                        </div>

                        <div>
                            <label class="label"><span class="label-text">Note Pagamento</span></label>
                            <textarea x-model.trim="payment_notes" name="payment_notes" rows="3"
                                      class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                                       focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400
                                       dark:bg-gray-800 dark:border-gray-700"></textarea>
                        </div>

                        <div>
                            <label class="label"><span class="label-text">Riferimento Pagamento</span></label>
                            <input type="text" x-model.trim="payment_reference" name="payment_reference"
                                   class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                                    focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400
                                    dark:bg-gray-800 dark:border-gray-700">
                        </div>
                    </div>

                    {{-- Pulsanti modale --}}
                    <div class="mt-6 flex justify-end gap-3">
                        {{-- Annulla: neutral pieno --}}
                        <button type="button"
                                class="btn btn-neutral shadow-none px-2
                                    !bg-neutral !text-neutral-content !border-neutral
                                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30
                                    disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="close()"
                                :disabled="loading">
                            Annulla
                        </button>

                        {{-- Registra: primary pieno --}}
                        <button type="submit"
                                class="btn btn-primary shadow-none px-2
                                    !bg-primary !text-primary-content !border-primary
                                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                                    disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="loading">
                            <span x-show="!loading">Registra</span>
                            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
                        </button>
                    </div>
                </form>

                {{-- X chiudi in alto a destra --}}
                <button type="button" class="absolute right-2 top-2 btn btn-ghost btn-xs" @click="close()">✕</button>
            </div>
        </div>
    </template>
</div>

{{-- Alpine helper: fetch POST + toast globale + refresh Livewire --}}
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('rentalActions', () => ({
        loading: false,

        // stato corrente (iniettato dal server in modo sicuro)
        phase: @js($rental->status),

        // etichette “umane” (invariato)
        phaseLabels: {
            checked_out: 'Check-out (veicolo consegnato)',
            in_use:      'In uso',
            checked_in:  'Check-in (veicolo rientrato)',
            closed:      'Chiusura contratto',
            cancelled:   'Annullamento',
            canceled:    'Annullamento',
            no_show:     'No-show',
        },
        phaseLabel(key) { return this.phaseLabels[key] ?? key.replace(/_/g, ' ') },

        // === helper per abilitare/disabilitare i pulsanti ===
        canCheckout() { return ['draft','reserved'].includes(this.phase) },
        canInuse()    { return this.phase === 'checked_out' },
        canCheckin()  { return ['checked_out','in_use'].includes(this.phase) },
        canClose()    { return this.phase === 'checked_in' },
        canCancel()   { return ['draft','reserved'].includes(this.phase) },
        canNoShow()   { return this.phase === 'reserved' },

        async submit(formEl) {
            if (this.loading) return;
            const phase   = formEl.dataset.phase || null;
            const human   = phase ? this.phaseLabel(phase) : null;
            const message = human ? `Vuoi passare alla fase ${human}?` : 'Confermi l’operazione?';
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
                try { data = await res.clone().json(); } catch (_) {}

                if (!res.ok || (data && data.ok === false)) {
                    const msg =
                        (data && (data.message || data.error)) ||
                        (res.status === 419 ? 'Sessione scaduta. Ricarica la pagina.' :
                        res.status === 403 ? 'Permesso negato.' :
                        res.status === 422 ? 'Dati non validi o prerequisiti mancanti.' :
                        'Operazione non riuscita.');
                    window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: msg }}));
                    return;
                }

                // aggiorna lo stato locale così i :disabled reagiscono subito
                if (data?.status) this.phase = data.status;

                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'success', message: `Stato aggiornato${data?.status ? `: ${data.status}` : ''}` }
                }));
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

    /**
     * paymentModal(actionUrl, defaultAmount)
     * - Teleporta il modale nel <body> per evitare stacking/overflow dei contenitori
     * - Submit via fetch() (AJAX) → toast globali → refresh Livewire → close()
     */
    Alpine.data('paymentModal', (actionUrl, defaultAmount = 0) => ({
        open: false,
        loading: false,
        amount: defaultAmount,
        payment_method: '',
        payment_reference: '',
        payment_notes: '',

        openModal(detail = null) {
            // se dall'evento passiamo un importo suggerito: $dispatch('open-payment-modal', { amount: 123.45 })
            if (detail?.amount != null) this.amount = Number(detail.amount);
            if (detail?.payment_notes != null) this.payment_notes = detail.payment_notes;
            this.open = true;
        },
        close() {
            this.open = false;
            // reset soft per sicurezza alla prossima apertura
            this.loading = false;
        },

        async submit() {
            if (this.loading) return;
            this.loading = true;

            try {
                const fd = new FormData();
                fd.append('amount', this.amount);
                fd.append('payment_method', this.payment_method);
                fd.append('payment_reference', this.payment_reference);
                fd.append('payment_notes', this.payment_notes);

                const token = document.querySelector('meta[name=csrf-token]')?.content;

                const res = await fetch(actionUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: fd,
                    credentials: 'same-origin',
                });

                let data = null;
                try { data = await res.clone().json(); } catch (_) {}

                if (!res.ok || (data && data.ok === false)) {
                    const msg =
                        (data && (data.message || data.error)) ||
                        (res.status === 419 ? 'Sessione scaduta. Ricarica la pagina.' :
                         res.status === 403 ? 'Permesso negato.' :
                         res.status === 422 ? 'Dati non validi.' :
                         'Operazione non riuscita.');
                    window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: msg }}));
                    this.loading = false;
                    return;
                }

                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'success', message: 'Pagamento registrato.' }
                }));

                // Aggiorna Livewire e chiudi
                try { this.$wire?.$refresh(); } catch(_) {}
                try { window.Livewire?.emit?.('refresh'); } catch(_) {}

                this.close();

                // reset campi (alla prossima apertura riparte da defaultAmount)
                this.amount = defaultAmount;
                this.payment_method = '';
                this.payment_reference = '';
                this.payment_notes = '';

            } catch (e) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'error', message: 'Errore di rete o risposta non valida.' }
                }));
                this.loading = false;
            }
        },
    }));
});


</script>
