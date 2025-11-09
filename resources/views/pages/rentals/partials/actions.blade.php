{{-- resources/views/pages/rentals/partials/actions.blade.php --}}
{{-- Azioni sul noleggio + Modale registrazione pagamento --}}
<div
    x-data="rentalActions({
        phase: @js($rental->status),
        flags: {
            hasBasePayment:      @js($rental->has_base_payment),
            hasOveragePayment:   @js($rental->has_distance_overage_payment),
        },
        overageUrl: '{{ route('rentals.distance_overage', $rental) }}'
    })"
    x-init="init()"
    class="space-y-3"
>
    {{-- BADGE chilometri extra da pagare (deriva da overage.amount, non da flag "needs") --}}
    <template x-if="overage.ready && Number(overage.amount) > 0 && !flags.hasOveragePayment">
        <div class="flex items-center justify-between rounded-md border border-warning/30 bg-warning/10 px-3 py-2">
            <div class="text-sm">
                <span class="font-medium">Km extra:</span>
                <span class="opacity-80">devi registrare il pagamento per chiudere</span>
            </div>
            <span class="badge badge-warning font-semibold text-sm" x-text="formatEuro(overage.amount)"></span>
        </div>
    </template>

    {{-- Registra Pagamento (sempre attivo) --}}
    <button
        class="btn btn-secondary btn-block shadow-none
               !bg-secondary !text-secondary-content !border-secondary
               hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-secondary/30
               disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="loading"
        @click="$dispatch('open-payment-modal')"
    >
        <span x-show="!loading">Registra Pagamento</span>
        <span x-show="loading" class="loading loading-spinner loading-sm"></span>
    </button>

    {{-- Checkout --}}
    <form method="POST" action="{{ route('rentals.checkout', $rental) }}" data-phase="checked_out" x-on:submit.prevent="submit($el)">
        @csrf
        <button class="btn btn-primary btn-block shadow-none
                      !bg-primary !text-primary-content !border-primary
                      hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                      disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="loading || !canCheckout()">
            <span x-show="!loading">Checkout</span>
            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
        </button>
    </form>

    {{-- Passa a In-use --}}
    <form method="POST" action="{{ route('rentals.inuse', $rental) }}" data-phase="in_use" x-on:submit.prevent="submit($el)">
        @csrf
        <button class="btn btn-info btn-block shadow-none
                      !bg-info !text-info-content !border-info
                      hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-info/30
                      disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="loading || !canInuse()">
            <span x-show="!loading">Passa a In-use</span>
            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
        </button>
    </form>

    {{-- Check-in --}}
    <form method="POST" action="{{ route('rentals.checkin', $rental) }}" data-phase="checked_in" x-on:submit.prevent="submit($el)">
        @csrf
        <button class="btn btn-accent btn-block shadow-none
                      !bg-accent !text-accent-content !border-accent
                      hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-accent/30
                      disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="loading || !canCheckin()">
            <span x-show="!loading">Check-in</span>
            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
        </button>
    </form>

    {{-- Chiudi --}}
    <form method="POST" action="{{ route('rentals.close', $rental) }}" data-phase="closed" x-on:submit.prevent="submit($el)">
        @csrf
        <button class="btn btn-success btn-block shadow-none
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
            <button class="btn btn-error btn-block shadow-none
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
            <button class="btn btn-warning btn-block shadow-none
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

{{-- Modale pagamento --}}
<div
    x-data="paymentModal(
        '{{ route('rentals.record_payment', $rental) }}',
        {{ (float)($rental->amount ?? 0) }},
        {
            kinds: [
                {val:'base',              label:'Quota base (contratto)'},
                {val:'distance_overage',  label:'Km extra'},
                {val:'damage',            label:'Danni'},
                {val:'surcharge',         label:'Sovrapprezzo'},
                {val:'fine',              label:'Multe'},
                {val:'other',             label:'Altro'},
            ],
        }
    )"
    x-on:open-payment-modal.window="openModal($event.detail)"
>
    <template x-teleport="body">
        <div x-show="open" x-transition.opacity class="fixed inset-0 z-[95] flex items-center justify-center bg-black/50 px-4"
             role="dialog" aria-modal="true" aria-labelledby="payment-modal-title" @keydown.escape.prevent.stop="close()">
            <div class="absolute inset-0" @click="close()"></div>

            <div x-show="open" x-transition.scale class="relative bg-base-100 rounded-lg shadow-xl w-full max-w-md p-6">
                <h2 id="payment-modal-title" class="text-lg font-semibold mb-4">Registra Pagamento</h2>

                <form x-on:submit.prevent="submit()">
                    @csrf
                    <div class="space-y-4">
                        {{-- Tipo --}}
                        <div>
                            <label class="label"><span class="label-text">Tipo</span></label>
                            <select x-model="kind" name="kind"
                                    class="mt-1 w-full rounded-md border-gray-300 shadow-sm appearance-none pr-8 focus:border-indigo-500 focus:ring-indigo-500" required
                                    @change="onKindChange()">
                                <option value="" disabled>Seleziona tipo</option>
                                <template x-for="k in kinds" :key="k.val">
                                    <option :value="k.val" x-text="k.label"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Importo --}}
                        <div>
                            <label class="label"><span class="label-text">Importo</span></label>
                            <input type="number" step="0.01" min="0" x-model.number="amount" name="amount" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm" required>
                            <p class="text-xs text-gray-500 mt-1" x-show="kind === 'distance_overage' && distanceOverageDue > 0">
                                Valore precompilato dai km extra.
                            </p>
                        </div>

                        {{-- Metodo --}}
                        <div>
                            <label class="label"><span class="label-text">Metodo di Pagamento</span></label>
                            <select x-model="payment_method" name="payment_method" class="mt-1 w-full rounded-md border-gray-300 shadow-sm pr-8 focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="" disabled>Seleziona metodo</option>
                                <option value="cash">Contanti</option>
                                <option value="pos">Carta di Credito</option>
                                <option value="bank_transfer">Bonifico Bancario</option>
                                <option value="other">Altro</option>
                            </select>
                        </div>

                        {{-- Note / Riferimento --}}
                        <div>
                            <label class="label"><span class="label-text">Note</span></label>
                            <textarea x-model.trim="payment_notes" name="payment_notes" rows="3" class="block w-full rounded-md border px-3 py-2 text-sm"></textarea>
                        </div>
                        <div>
                            <label class="label"><span class="label-text">Riferimento</span></label>
                            <input type="text" x-model.trim="payment_reference" name="payment_reference" class="block w-full rounded-md border px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" class="btn btn-neutral px-2" @click="close()" :disabled="loading">Annulla</button>
                        <button type="submit" class="btn btn-primary px-2" :disabled="loading">
                            <span x-show="!loading">Registra</span>
                            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
                        </button>
                    </div>
                </form>

                <button type="button" class="absolute right-2 top-2 btn btn-ghost btn-xs" @click="close()">✕</button>
            </div>
        </div>
    </template>
</div>

{{-- Alpine helpers --}}
<script>
document.addEventListener('alpine:init', () => {

  // ===== ACTIONS =====
  Alpine.data('rentalActions', (initial) => ({
    loading: false,
    phase: initial.phase,
    flags: {
      hasBasePayment:    !!(initial.flags?.hasBasePayment),
      hasOveragePayment: !!(initial.flags?.hasOveragePayment),
    },
    overageUrl: initial.overageUrl || null,
    overage: { ready:false, amount:0, cents:0 },

    // Label fasi
    phaseLabels: {
      checked_out:'Check-out', in_use:'In use', checked_in:'Check-in',
      closed:'Chiuso', cancelled:'Annullato', canceled:'Annullato',
      no_show:'No-show', draft:'Bozza', reserved:'Prenotato',
    },
    phaseLabel(k){ return this.phaseLabels[k] ?? k.replace(/_/g,' ') },

    // Abilitazioni
    canCheckout(){ return ['draft','reserved'].includes(this.phase) && !!this.flags.hasBasePayment },
    canInuse(){    return this.phase === 'checked_out' },
    canCheckin(){  return ['checked_out','in_use'].includes(this.phase) },
canClose(){
  if (this.phase !== 'checked_in') return false;
  const needOverage = this.overage.ready && Number(this.overage.amount) > 0;
  return !needOverage || !!this.flags.hasOveragePayment;
},
    canCancel(){ return ['draft','reserved'].includes(this.phase) },
    canNoShow(){ return this.phase === 'reserved' },

    async init(){
      if (this.overageUrl) await this.fetchOverage();

      // Aggiorna flag dalla risposta del backend (es. dopo salvataggio pagamento)
      window.addEventListener('rental-flags-updated', (e) => {
        const f = e.detail || {};
        if ('has_base_payment' in f)               this.flags.hasBasePayment    = !!f.has_base_payment;
        if ('has_distance_overage_payment' in f)   this.flags.hasOveragePayment = !!f.has_distance_overage_payment;
      });
    },

    async submit(formEl){
      if (this.loading) return;
      const phase = formEl.dataset.phase || null;
      const msg = phase ? `Vuoi passare alla fase ${this.phaseLabel(phase)}?` : 'Confermi l’operazione?';
      if (!confirm(msg)) return;

      this.loading = true;
      try {
        const token = formEl.querySelector('input[name=_token]')?.value
                   || document.querySelector('meta[name=csrf-token]')?.content;

        const res = await fetch(formEl.action, {
          method:'POST',
          headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json', 'X-CSRF-TOKEN': token },
          body: new FormData(formEl),
          credentials:'same-origin'
        });

        let data=null; try{ data=await res.clone().json(); }catch(_){}

        if (!res.ok || data?.ok===false) {
          const msg = (data && (data.message||data.error)) || 'Operazione non riuscita.';
          window.dispatchEvent(new CustomEvent('toast', { detail:{type:'error', message:msg} }));
          return;
        }

        if (data?.status) this.phase = data.status;
        if (data?.flags) {
          if ('has_base_payment' in data.flags)
            this.flags.hasBasePayment = !!data.flags.has_base_payment;
          if ('has_distance_overage_payment' in data.flags)
            this.flags.hasOveragePayment = !!data.flags.has_distance_overage_payment;
        }

        // ricalcola l’overage per aggiornare badge/bottoni
        if (this.overageUrl) await this.fetchOverage();

        window.dispatchEvent(new CustomEvent('toast', {
          detail:{type:'success', message:`Stato aggiornato${data?.status?`: ${data.status}`:''}`}
        }));
        try{ this.$wire?.$refresh(); }catch(_){}
        try{ window.Livewire?.emit?.('refresh'); }catch(_){}
      } finally {
        this.loading = false;
      }
    },

async fetchOverage(){
  try {
    const res  = await fetch(this.overageUrl, { headers:{ 'Accept':'application/json' } });
    const data = await res.json();

    if (!data.ok || !data.has_data) {
      this.overage = { ready:true, amount:0, cents:0 };
      window.__distanceOverageDue = 0;
    } else {
      const amt = Number(data.amount || 0);
      this.overage = { ready:true, amount: amt, cents: Number(data.cents || 0) };
      window.__distanceOverageDue = amt;
    }

    // Fonte backend per "già pagato"
    if (typeof data.has_payment !== 'undefined') {
      this.flags.hasOveragePayment = !!data.has_payment;
      window.__hasDistanceOveragePayment = this.flags.hasOveragePayment;
    }
  } catch(_) {
    this.overage = { ready:true, amount:0, cents:0 };
    window.__distanceOverageDue = 0;
  }
},

    formatEuro(v){ return Number(v??0).toLocaleString('it-IT',{style:'currency',currency:'EUR'}) },
  }));

  // ===== MODALE PAGAMENTO =====
  // Supporta: paymentModal(url)  oppure  paymentModal(url, defaultAmount, {kinds:[...]} )
  Alpine.data('paymentModal', (arg1, arg2 = 0, arg3 = null) => {
    const isString     = typeof arg1 === 'string';
    const actionUrl    = isString ? arg1 : (arg1?.actionUrl ?? '');
    const defaultAmount= isString ? Number(arg2 ?? 0) : Number(arg1?.defaultAmount ?? 0);

    const defaultKinds = [
      {val:'base',             label:'Quota base (contratto)'},
      {val:'distance_overage', label:'Km extra'},
      {val:'damage',           label:'Danni'},
      {val:'surcharge',        label:'Sovrapprezzo'},
      {val:'fine',             label:'Multe'},
      {val:'other',            label:'Altro'},
    ];
    const kindsFromArgs = Array.isArray(arg3?.kinds) ? arg3.kinds
                        : (Array.isArray(arg1?.kinds) ? arg1.kinds : null);

    return {
      open:false,
      loading:false,
      amount: defaultAmount,
      kind:'',
      payment_method:'',
      payment_notes:'',
      payment_reference:'',
      description:'', // retro-compat

      kinds: kindsFromArgs || defaultKinds,
      distanceOverageDue: 0,

      openModal(detail = null){
        const due = (detail && typeof detail.distanceOverage !== 'undefined')
          ? Number(detail.distanceOverage || 0)
          : Number(window.__distanceOverageDue || 0);

        this.distanceOverageDue = due;
        this.open = true;

        const alreadyPaid = !!window.__hasDistanceOveragePayment;
        if (due > 0 && !alreadyPaid) {
          this.kind = 'distance_overage';
          this.amount = due.toFixed(2);
        } else if (!this.kind) {
          this.kind = 'base';
          // non forziamo amount: lasciamo l’ammontare di default o vuoto
        }
      },

      close(){ this.open=false; this.loading=false; },

      onKindChange(){
        if (this.kind === 'distance_overage') {
          const due = Number(window.__distanceOverageDue || this.distanceOverageDue || 0);
          if (due > 0) this.amount = due.toFixed(2);
        }
      },

      async submit(){
        if (this.loading) return;
        this.loading = true;
        try{
          const fd = new FormData();
          fd.append('kind', this.kind);
          fd.append('amount', this.amount);
          fd.append('payment_method', this.payment_method);
          const notes = this.payment_notes || this.description || '';
          if (notes) fd.append('payment_notes', notes);
          if (this.payment_reference) fd.append('payment_reference', this.payment_reference);

          const token = document.querySelector('meta[name=csrf-token]')?.content;
          const res = await fetch(actionUrl, {
            method:'POST',
            headers:{ 'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN':token },
            body: fd,
            credentials:'same-origin'
          });

          let data=null; try{ data=await res.clone().json(); }catch(_){}

          if (!res.ok || data?.ok===false) {
            const msg = (data && (data.message||data.error)) || 'Operazione non riuscita.';
            window.dispatchEvent(new CustomEvent('toast', { detail:{type:'error', message:msg} }));
            this.loading=false; return;
          }

          window.dispatchEvent(new CustomEvent('toast', { detail:{type:'success', message:'Pagamento registrato.'} }));

          // Aggiorna i flag in pagina (sicuro anche senza refresh)
          if (data?.flags) window.dispatchEvent(new CustomEvent('rental-flags-updated', { detail: data.flags }));
          if (this.kind === 'distance_overage') {
            window.dispatchEvent(new CustomEvent('rental-flags-updated', {
              detail: { has_distance_overage_payment: true }
            }));
          }

          try{ this.$wire?.$refresh(); }catch(_){}
          try{ window.Livewire?.emit?.('refresh'); }catch(_){}

          // reset soft
          this.close();
          this.kind=''; this.payment_method=''; this.payment_notes=''; this.payment_reference='';
          this.description=''; this.amount = defaultAmount;

        } finally { this.loading=false; }
      },
    };
  });

});
</script>
