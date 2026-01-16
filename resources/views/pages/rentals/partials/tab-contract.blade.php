{{-- resources/views/pages/rentals/partials/tab-contract.blade.php --}}
{{-- Stile coerente con la pagina: header con badge di stato, liste con card leggere e bottoni filled --}}

@php
    $hasGenerated = method_exists($rental,'getMedia') && $rental->getMedia('contract')->isNotEmpty();

    // ✅ compat: se qualcuno ha usato davvero una collection diversa per il firmato
    $signedMedia = collect();
    if (method_exists($rental,'getMedia')) {
        $signedMedia = $rental->getMedia('signatures');
        if ($signedMedia->isEmpty()) {
            $signedMedia = $rental->getMedia('rental-contract-signed');
        }
    }
    $hasSigned   = $signedMedia->isNotEmpty();

    $hasCustomer = !empty($rental->customer_id);

    /**
     * ===== FIRME =====
     * - Cliente: override su Rental -> signature_customer
     * - Noleggiante: override su Rental -> signature_lessor
     * - Default aziendale: Organization -> signature_company (fallback se non c'è override)
     */

    $customerSig = method_exists($rental, 'getFirstMedia')
        ? $rental->getFirstMedia('signature_customer')
        : null;

    $lessorOverrideSig = method_exists($rental, 'getFirstMedia')
        ? $rental->getFirstMedia('signature_lessor')
        : null;

    // Provo a ricavare organization (adatta qui se la relazione ha un nome diverso)
    $orgModel = $rental->organization ?? $rental->org ?? null;

    $lessorDefaultSig = ($orgModel && method_exists($orgModel, 'getFirstMedia'))
        ? $orgModel->getFirstMedia('signature_company')
        : null;

    // Firma noleggiante effettiva: override > default
    $lessorSig = $lessorOverrideSig ?: $lessorDefaultSig;
    $lessorSigSource = $lessorOverrideSig ? 'override' : ($lessorDefaultSig ? 'default' : null);

    // ✅ (1) questa variabile era usata ma non esisteva
    $hasCustomerSignature = (bool) $customerSig;

    // Preview SEMPRE con media.open per evitare "immagine rotta" su dischi non pubblici
    $customerSigUrl = $customerSig ? route('media.open', $customerSig) : null;
    $lessorSigUrl   = $lessorSig ? route('media.open', $lessorSig) : null;

    // ===== PREZZO (override) =====
    $baseAmount     = (float) ($rental->amount ?? 0);
    $overrideAmount = $rental->final_amount_override; // può essere null
    $effectiveAmount = $overrideAmount !== null ? (float) $overrideAmount : $baseAmount;

    $canEditPrice = in_array($rental->status, ['draft','reserved'], true);
@endphp

<div class="card shadow">
    <div class="card-body space-y-5">
        {{-- Header sezione --}}
        <div class="flex items-center justify-between">
            <div class="card-title">Contratto</div>
            <div class="flex gap-2">
                <span class="badge {{ $hasGenerated ? 'badge-success' : 'badge-outline' }}">Generato</span>
                <span class="badge {{ $hasSigned ? 'badge-success' : 'badge-outline' }}">Firmato</span>
            </div>
        </div>

        {{-- CTA: base (rigenerabile) + firmato --}}
        <div class="flex flex-wrap items-center justify-end gap-2">
            {{-- ✅ CONTRATTO BASE: visibile finché NON esiste firmato --}}
            @if(!$hasSigned)
                @if($hasCustomer)
                    <button
                        class="p-2 btn btn-primary shadow-none
                            !bg-primary !text-primary-content !border-primary
                            hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                            disabled:opacity-50 disabled:cursor-not-allowed"
                        wire:click="generateContract"
                        wire:loading.attr="disabled"
                        wire:target="generateContract"
                        title="{{ $hasGenerated ? 'Rigenera una nuova versione del contratto base (senza firme)' : 'Genera il contratto base (senza firme)' }}"
                    >
                        <span wire:loading.remove wire:target="generateContract">
                            {{ $hasGenerated ? 'Rigenera contratto base (PDF)' : 'Genera contratto base (PDF)' }}
                        </span>
                        <span wire:loading wire:target="generateContract" class="loading loading-spinner loading-sm"></span>
                    </button>
                @else
                    <div class="alert alert-warning shadow-sm">
                        <span class="text-sm">
                            Associa prima un cliente al noleggio per poter generare il contratto.
                        </span>
                    </div>
                @endif
            @endif

            {{-- ✅ CONTRATTO FIRMATO: disponibile quando c’è firma cliente --}}
            @if($hasCustomerSignature)
                <button
                    class="p-2 btn btn-primary shadow-none
                        !bg-primary !text-primary-content !border-primary
                        hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                        disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:click="regenerateContractWithSignatures"
                    wire:loading.attr="disabled"
                    wire:target="regenerateContractWithSignatures"
                    title="Genera una nuova versione del PDF firmato (se manca il base verrà generato automaticamente)"
                >
                    <span wire:loading.remove wire:target="regenerateContractWithSignatures">
                        {{ $hasSigned ? 'Rigenera PDF firmato' : 'Genera PDF firmato' }}
                    </span>
                    <span wire:loading wire:target="regenerateContractWithSignatures" class="loading loading-spinner loading-sm"></span>
                </button>
            @else
                <button
                    class="p-2 btn btn-primary shadow-none opacity-60 cursor-not-allowed
                        !bg-primary !text-primary-content !border-primary"
                    disabled
                    title="Prima acquisisci la firma del cliente (Carica o Firma su schermo)"
                >
                    Genera PDF firmato
                </button>
            @endif
        </div>

        {{-- ===== PREZZO CONTRATTO (override) ===== --}}
        <div class="rounded-xl border p-4 space-y-3">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="font-semibold">Prezzo contratto</div>
                    <div class="text-xs opacity-70">
                        Mostriamo il prezzo previsto dal listino e puoi impostare un override. Nel PDF verrà usato l’override se presente.
                    </div>
                </div>

                <span class="badge {{ $overrideAmount !== null ? 'badge-success' : 'badge-outline' }}">
                    {{ $overrideAmount !== null ? 'Override attivo' : 'Listino' }}
                </span>
            </div>

            <div class="grid md:grid-cols-3 gap-3">
                <div class="rounded-lg border bg-base-100 p-3">
                    <div class="text-xs opacity-70">Prezzo previsto (listino)</div>
                    <div class="text-lg font-semibold">
                        € {{ number_format($baseAmount, 2, ',', '.') }}
                    </div>
                </div>

                <div class="rounded-lg border bg-base-100 p-3">
                    <div class="text-xs opacity-70">Prezzo applicato</div>
                    <div class="text-lg font-semibold">
                        € {{ number_format($effectiveAmount, 2, ',', '.') }}
                    </div>
                </div>

                <div class="rounded-lg border bg-base-100 p-3">
                    <div class="text-xs opacity-70">Override (opzionale)</div>

                    <div class="flex items-center gap-2 mt-2">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model.defer="final_amount_override"
                            class="input input-bordered input-sm w-full"
                            placeholder="Lascia vuoto per listino"
                            @disabled(!$canEditPrice)
                        />

                        <button
                            type="button"
                            class="p-1 btn btn-sm shadow-none
                                !bg-primary !text-primary-content !border-primary
                                hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                                disabled:opacity-50 disabled:cursor-not-allowed"
                            wire:click="saveFinalAmountOverride"
                            wire:loading.attr="disabled"
                            wire:target="saveFinalAmountOverride"
                            @disabled(!$canEditPrice)
                            title="{{ $canEditPrice ? 'Salva override prezzo' : 'Prezzo modificabile solo in bozza/prenotato' }}"
                        >
                            <span wire:loading.remove wire:target="saveFinalAmountOverride">Salva</span>
                            <span wire:loading wire:target="saveFinalAmountOverride" class="loading loading-spinner loading-sm"></span>
                        </button>

                        <button
                            type="button"
                            class="p-1 btn btn-sm shadow-none
                                !bg-error !text-error-content !border-error
                                hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-error/30
                                disabled:opacity-50 disabled:cursor-not-allowed"
                            wire:click="clearFinalAmountOverride"
                            wire:loading.attr="disabled"
                            wire:target="clearFinalAmountOverride"
                            @disabled(!$canEditPrice || $overrideAmount === null)
                            title="{{ $canEditPrice ? 'Rimuovi override e torna al listino' : 'Prezzo modificabile solo in bozza/prenotato' }}"
                        >
                            <span wire:loading.remove wire:target="clearFinalAmountOverride">Rimuovi</span>
                            <span wire:loading wire:target="clearFinalAmountOverride" class="loading loading-spinner loading-sm"></span>
                        </button>
                    </div>

                    @if(!$canEditPrice)
                        <div class="text-xs text-amber-700 mt-2">
                            Prezzo modificabile solo in <strong>bozza</strong> o <strong>prenotato</strong>.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===== Versioni generate (PDF) + Firmati ===== --}}
        <div class="grid md:grid-cols-2 gap-5">
            {{-- Colonna: versioni generate (PDF) --}}
            <div class="space-y-2">
                <div class="font-semibold">Versioni generate (PDF)</div>
                @php
                    $baseMedia = method_exists($rental,'getMedia')
                        ? $rental->getMedia('contract')->sortByDesc('created_at')
                        : collect();
                @endphp

                @forelse($baseMedia as $m)
                    @php $isCurrent = (bool) $m->getCustomProperty('current'); @endphp
                    <div class="flex items-center justify-between rounded-xl border p-3 {{ $isCurrent ? 'ring-1 ring-primary/30' : '' }}">
                        <div class="text-sm">
                            <span class="mr-2">📄</span>
                            <span class="font-medium">{{ $m->file_name }}</span>
                            <span class="opacity-70">· {{ $m->created_at->format('d/m/Y H:i') }}</span>

                            @if($isCurrent)
                                <span class="badge badge-success ml-2">Corrente</span>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ $m->getUrl() }}" target="_blank"
                               class="btn btn-sm shadow-none
                                      !bg-neutral !text-neutral-content !border-neutral
                                      hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30">
                                Apri
                            </a>

                            <form method="POST" action="{{ route('media.destroy', $m) }}"
                                  x-data="ajaxDeleteMedia()"
                                  x-cloak
                                  x-on:submit.prevent="submit($event)"
                                  :class="{ 'opacity-50 pointer-events-none': $store.rental.isClosed }">
                                @csrf @method('DELETE')
                                <button
                                    :disabled="$store.rental.isClosed || loading"
                                    class="btn btn-sm shadow-none px-2
                                        !bg-error !text-error-content !border-error
                                        hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-error/30">
                                    Elimina
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="alert alert-info">
                        Nessun contratto generato.
                    </div>
                @endforelse
            </div>

            {{-- Colonna: firmati --}}
            <div class="space-y-2">
                <div class="font-semibold">Firmati</div>

                @php
                    $signedMedia = method_exists($rental,'getMedia')
                        ? $rental->getMedia('signatures')->sortByDesc('created_at')
                        : collect();
                @endphp

                @forelse($signedMedia as $m)
                    @php $isCurrent = (bool) $m->getCustomProperty('current'); @endphp

                    <div class="flex items-center justify-between rounded-xl border p-3 {{ $isCurrent ? 'ring-1 ring-primary/30' : '' }}">
                        <div class="text-sm">
                            <span class="mr-2">📄</span>
                            <span class="font-medium">{{ $m->file_name }}</span>
                            <span class="opacity-70">· {{ $m->created_at->format('d/m/Y H:i') }}</span>

                            @if($isCurrent)
                                <span class="badge badge-success ml-2">Corrente</span>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('media.open', $m) }}" target="_blank"
                               class="btn btn-sm shadow-none
                                    !bg-neutral !text-neutral-content !border-neutral
                                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30">
                                Apri
                            </a>

                            <form method="POST" action="{{ route('media.destroy', $m) }}"
                                  x-data="ajaxDeleteMedia()"
                                  x-on:submit.prevent="submit($event)"
                                  :class="{ 'opacity-50 pointer-events-none': $store.rental.isClosed }">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm shadow-none px-2
                                               !bg-error !text-error-content !border-error
                                               hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-error/30">
                                    Elimina
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="alert alert-info">
                        Nessun contratto firmato.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ===== FIRME (sezioni identiche) ===== --}}
        <div class="grid lg:grid-cols-2 gap-5">
            {{-- FIRMA CLIENTE --}}
            <div class="rounded-xl border p-4 space-y-3 overflow-hidden">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="font-semibold">Firma cliente</div>
                        <div class="text-xs opacity-70">
                            Firma raccolta su schermo o caricata (PNG/JPG). Verrà usata nel PDF.
                        </div>
                    </div>
                    <span class="badge {{ $customerSig ? 'badge-success' : 'badge-outline' }}">
                        {{ $customerSig ? 'Presente' : 'Assente' }}
                    </span>
                </div>

                <div class="rounded-lg border bg-base-100 p-3 min-h-[160px] flex items-center justify-center overflow-hidden">
                    @if($customerSigUrl)
                        <img src="{{ $customerSigUrl }}" alt="Firma cliente"
                             class="max-h-[140px] w-auto object-contain">
                    @else
                        <div class="text-sm opacity-60">Nessuna firma cliente salvata.</div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST"
                          action="{{ route('rentals.signature.customer.store', $rental) }}"
                          enctype="multipart/form-data"
                          x-data="ajaxSignatureUpload"
                          x-on:submit.prevent="submit($event)"
                          class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="file" name="file" accept="image/png,image/jpeg"
                               class="file-input file-input-sm file-input-bordered w-full max-w-[260px]" required>
                        <button class="p-2 btn btn-sm shadow-none
                                      !bg-neutral !text-neutral-content !border-neutral
                                      hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30"
                                :disabled="loading">
                            <span x-show="!loading">Carica firma</span>
                            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
                        </button>
                    </form>

                    <button type="button"
                            class="p-2 btn btn-sm shadow-none
                                      !bg-neutral !text-neutral-content !border-neutral
                                      hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30"
                            onclick="window.__openSignatureModal('customer')">
                        Firma su schermo
                    </button>

                    @if($customerSig)
                        <form method="POST"
                              action="{{ route('rentals.signature.customer.destroy', $rental) }}"
                              x-data="ajaxSignatureDelete"
                              x-on:submit.prevent="submit($event)"
                              class="inline-flex">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="p-2 btn btn-sm shadow-none
                                        !bg-error !text-error-content !border-error
                                        hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-error/30"
                                    :disabled="loading">
                                <span x-show="!loading">Rimuovi</span>
                                <span x-show="loading" class="loading loading-spinner loading-sm"></span>
                            </button>
                        </form>
                    @endif

                    @if($customerSig)
                        <span class="text-xs opacity-70 ml-1">Firma salvata.</span>
                    @endif
                </div>
            </div>

            {{-- FIRMA NOLEGGIANTE --}}
            <div class="rounded-xl border p-4 space-y-3 overflow-hidden">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="font-semibold">Firma noleggiante</div>
                        <div class="text-xs opacity-70">
                            Usa la firma aziendale di default se presente. Se firmi/carichi qui, crei un override sul noleggio.
                        </div>
                    </div>

                    <span class="badge {{ $lessorSig ? 'badge-success' : 'badge-outline' }}">
                        {{ $lessorSig ? 'Presente' : 'Assente' }}
                    </span>
                </div>

                <div class="rounded-lg border bg-base-100 p-3 min-h-[160px] flex items-center justify-center overflow-hidden">
                    @if($lessorSigUrl)
                        <img src="{{ $lessorSigUrl }}" alt="Firma noleggiante"
                             class="max-h-[140px] w-auto object-contain">
                    @else
                        <div class="text-sm opacity-60">Nessuna firma noleggiante disponibile.</div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST"
                          action="{{ route('rentals.signature.lessor.store', $rental) }}"
                          enctype="multipart/form-data"
                          x-data="ajaxSignatureUpload"
                          x-on:submit.prevent="submit($event)"
                          class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="file" name="file" accept="image/png,image/jpeg"
                               class="file-input file-input-sm file-input-bordered w-full max-w-[260px]" required>
                        <button class="p-2 btn btn-sm shadow-none
                                      !bg-neutral !text-neutral-content !border-neutral
                                      hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30"
                                :disabled="loading">
                            <span x-show="!loading">Carica firma</span>
                            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
                        </button>
                    </form>

                    <button type="button"
                            class="p-2 btn btn-sm shadow-none
                                      !bg-neutral !text-neutral-content !border-neutral
                                      hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30"
                            onclick="window.__openSignatureModal('lessor')">
                        Firma su schermo
                    </button>

                    @if($lessorOverrideSig)
                        <form method="POST"
                              action="{{ route('rentals.signature.lessor.destroy', $rental) }}"
                              x-data="ajaxSignatureDelete"
                              x-on:submit.prevent="submit($event)"
                              class="inline-flex">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="p-2 btn btn-sm shadow-none
                                        !bg-error !text-error-content !border-error
                                        hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-error/30"
                                    :disabled="loading">
                                <span x-show="!loading">Rimuovi override</span>
                                <span x-show="loading" class="loading loading-spinner loading-sm"></span>
                            </button>
                        </form>
                    @endif

                    @if($lessorSig)
                        <span class="text-xs opacity-70 ml-1">Firma salvata.</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===== MODAL FIRMA SU SCHERMO (riusata per customer/lessor) ===== --}}
<dialog id="signatureModal" class="modal" x-data="signaturePad">
    <form method="dialog" class="modal-box w-11/12 max-w-2xl">
        <h3 class="font-bold text-lg" x-ref="title">Firma</h3>
        <p class="text-sm opacity-70 mt-1">
            Disegna la firma e salva. Verrà caricata come PNG.
        </p>

        <div class="mt-4 border rounded-lg overflow-hidden">
            <canvas x-ref="canvas" style="width:100%; height:220px; touch-action:none;"></canvas>
        </div>

        <div class="mt-4 flex items-center justify-end gap-2">
            <button type="button"
                @click="clear()"
                class="p-2 btn btn-sm shadow-none
                    !bg-neutral !text-neutral-content !border-neutral
                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30">
                Pulisci
            </button>

            <button type="button"
                @click="save()"
                class="p-2 btn btn-sm shadow-none
                    !bg-primary !text-primary-content !border-primary
                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30">
                Salva
            </button>

            <button type="button"
                @click="close()"
                class="p-2 btn btn-sm shadow-none
                    !bg-base-200 !text-base-content !border-base-200
                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-base-content/20">
                Chiudi
            </button>
        </div>
    </form>
</dialog>


@script
<script>
(() => {
    // Evita doppie registrazioni su re-render Livewire
    if (window.__sigAllRegistered) return;
    window.__sigAllRegistered = true;

    // Stub globale: anche se clicchi prima, non esplode.
    // Accoda la richiesta e la riapre quando Alpine è pronto.
    window.__pendingSigTarget = null;
    window.__openSignatureModal = function (target) {
        window.__pendingSigTarget = (target === 'lessor') ? 'lessor' : 'customer';
        document.dispatchEvent(new CustomEvent('sig-open', { detail: { target: window.__pendingSigTarget } }));
    };

    const register = () => {
        const Alpine = window.Alpine;
        if (!Alpine) return;

        // ===== Upload =====
        Alpine.data('ajaxSignatureUpload', () => ({
            loading: false,
            async submit(e) {
                if (this.loading) return;
                this.loading = true;

                try {
                    const form = e.target;
                    const url  = form.getAttribute('action');
                    const fd   = new FormData(form);

                    const fileInput = form.querySelector('input[type="file"]');
                    if (fileInput && fileInput.files.length === 0) return;

                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf ?? '',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: fd,
                    });

                    if (!res.ok) {
                        let msg = `Upload fallito (${res.status})`;
                        try { const data = await res.json(); msg = data?.message || msg; } catch (_) {}
                        throw new Error(msg);
                    }

                    window.Livewire ? window.Livewire.dispatch('signature-updated') : window.location.reload();
                } catch (err) {
                    console.error(err);
                    alert(err?.message ?? 'Errore durante il caricamento della firma.');
                } finally {
                    this.loading = false;
                }
            }
        }));

        // ===== Delete =====
        Alpine.data('ajaxSignatureDelete', () => ({
            loading: false,
            async submit(e) {
                if (this.loading) return;
                this.loading = true;

                try {
                    const form = e.target;
                    const url  = form.getAttribute('action');

                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                    const res = await fetch(url, {
                        method: 'POST', // @method('DELETE') nel form
                        headers: {
                            'X-CSRF-TOKEN': csrf ?? '',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: new FormData(form),
                    });

                    if (!res.ok) {
                        let msg = `Delete fallito (${res.status})`;
                        try { const data = await res.json(); msg = data?.message || msg; } catch (_) {}
                        throw new Error(msg);
                    }

                    window.Livewire ? window.Livewire.dispatch('signature-updated') : window.location.reload();
                } catch (err) {
                    console.error(err);
                    alert(err?.message ?? 'Errore durante la rimozione della firma.');
                } finally {
                    this.loading = false;
                }
            }
        }));

        // ===== Signature pad (modal) =====
        Alpine.data('signaturePad', () => ({
            target: 'customer',
            ctx: null,
            drawing: false,
            last: null,
            listenersBound: false,

            csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token()),
            URLS: {
                customer: @json(route('rentals.signature.customer.store', $rental)),
                lessor:   @json(route('rentals.signature.lessor.store', $rental)),
            },

            init() {
                // ascolta l'evento globale (anche se viene sparato prima, recuperiamo da pending)
                if (!window.__sigOpenListenerAdded) {
                    window.__sigOpenListenerAdded = true;

                    document.addEventListener('sig-open', (e) => {
                        const t = e?.detail?.target;
                        this.open(t);
                    });
                }

                // se qualcuno ha cliccato prima dell’init
                if (window.__pendingSigTarget) {
                    const t = window.__pendingSigTarget;
                    window.__pendingSigTarget = null;
                    this.open(t);
                }
            },

            open(target) {
                this.target = (target === 'lessor') ? 'lessor' : 'customer';

                // titolo
                if (this.$refs.title) {
                    this.$refs.title.textContent = this.target === 'lessor'
                        ? 'Firma noleggiante'
                        : 'Firma cliente';
                }

                const modal = document.getElementById('signatureModal');
                if (!modal) return;

                modal.showModal();

                // aspetta layout reale del dialog, poi inizializza canvas
                requestAnimationFrame(() => requestAnimationFrame(() => this.resizeCanvas(true)));
            },

            close() {
                document.getElementById('signatureModal')?.close();
            },

            resizeCanvas(bind = false) {
                const canvas = this.$refs.canvas;
                if (!canvas) return;

                const rect = canvas.getBoundingClientRect();
                if (!rect.width || !rect.height) return;

                const ratio = window.devicePixelRatio || 1;
                canvas.width  = Math.floor(rect.width * ratio);
                canvas.height = Math.floor(rect.height * ratio);

                const ctx = canvas.getContext('2d');
                ctx.setTransform(1,0,0,1,0,0);
                ctx.scale(ratio, ratio);

                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111';
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, rect.width, rect.height);

                this.ctx = ctx;
                this.drawing = false;
                this.last = null;

                if (bind && !this.listenersBound) this.bindListeners();
            },

            bindListeners() {
                const canvas = this.$refs.canvas;
                if (!canvas || !this.ctx) return;

                this.listenersBound = true;

                const getPoint = (e) => {
                    const r = canvas.getBoundingClientRect();
                    const x = (e.clientX ?? (e.touches && e.touches[0].clientX)) - r.left;
                    const y = (e.clientY ?? (e.touches && e.touches[0].clientY)) - r.top;
                    return { x, y };
                };

                const start = (e) => {
                    this.drawing = true;
                    this.last = getPoint(e);
                    canvas.setPointerCapture?.(e.pointerId);
                };

                const move = (e) => {
                    if (!this.drawing) return;
                    e.preventDefault();

                    const p = getPoint(e);
                    this.ctx.beginPath();
                    this.ctx.moveTo(this.last.x, this.last.y);
                    this.ctx.lineTo(p.x, p.y);
                    this.ctx.stroke();
                    this.last = p;
                };

                const end = () => {
                    this.drawing = false;
                    this.last = null;
                };

                canvas.addEventListener('pointerdown', start);
                canvas.addEventListener('pointermove', move, { passive: false });
                canvas.addEventListener('pointerup', end);
                canvas.addEventListener('pointercancel', end);
            },

            clear() {
                requestAnimationFrame(() => this.resizeCanvas(false));
            },

            save() {
                const canvas = this.$refs.canvas;
                if (!canvas) return;

                canvas.toBlob(async (blob) => {
                    if (!blob) return;

                    try {
                        const fd = new FormData();
                        fd.append('file', new File([blob], 'signature.png', { type: 'image/png' }));

                        const url = this.URLS[this.target];

                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrf },
                            body: fd
                        });

                        if (!res.ok) {
                            let msg = `Errore salvataggio firma (${res.status})`;
                            try { const data = await res.json(); msg = data?.message || msg; } catch (_) {}
                            throw new Error(msg);
                        }

                        this.close();
                        window.Livewire ? window.Livewire.dispatch('signature-updated') : window.location.reload();
                    } catch (err) {
                        console.error(err);
                        alert(err?.message ?? 'Errore salvataggio firma.');
                    }
                }, 'image/png');
            }
        }));
    };

    // registra subito e anche in fallback
    register();
    document.addEventListener('alpine:init', register, { once: true });
})();
</script>
@endscript
