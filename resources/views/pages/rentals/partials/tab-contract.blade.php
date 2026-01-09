{{-- resources/views/pages/rentals/partials/tab-contract.blade.php --}}
{{-- Stile coerente con la pagina: header con badge di stato, liste con card leggere e bottoni filled --}}
@php
    $hasGenerated = method_exists($rental,'getMedia') && $rental->getMedia('contract')->isNotEmpty();
    $hasSigned    = method_exists($rental,'getMedia') && $rental->getMedia('signatures')->isNotEmpty();
    $hasCustomer  = !empty($rental->customer_id);

    // ====== FIRME GRAFICHE ======
    $customerSigMedia = method_exists($rental,'getFirstMedia') ? $rental->getFirstMedia('signature_customer') : null;
    $customerSigUrl   = $customerSigMedia ? route('media.open', $customerSigMedia) : null;

    // Firma noleggiante: precedence ORG default -> Rental override (come richiesto)
    $orgDefaultSigMedia = null;
    if (method_exists($rental,'organization') && $rental->organization) {
        $orgDefaultSigMedia = $rental->organization->getFirstMedia('signature_company');
    }
    $orgDefaultSigUrl = $orgDefaultSigMedia ? route('media.open', $orgDefaultSigMedia) : null;

    $lessorOverrideMedia = method_exists($rental,'getFirstMedia') ? $rental->getFirstMedia('signature_lessor') : null;
    $lessorOverrideUrl   = $lessorOverrideMedia ? route('media.open', $lessorOverrideMedia) : null;

    // Attiva (default prima, poi override)
    $lessorActiveUrl   = $orgDefaultSigUrl ?: $lessorOverrideUrl;
    $lessorActiveLabel = $orgDefaultSigUrl ? 'Default aziendale' : ($lessorOverrideUrl ? 'Override noleggio' : null);
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

        {{-- CTA: mostra solo se NON c'è un contratto generato --}}
        @if(!$hasGenerated)
            <div class="flex justify-end">
                @if($hasCustomer)
                    {{-- ✅ Mostra il pulsante solo con cliente presente --}}
                    <button
                        class="btn btn-primary shadow-none
                            !bg-primary !text-primary-content !border-primary
                            hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                            disabled:opacity-50 disabled:cursor-not-allowed"
                        wire:click="generateContract"
                        wire:loading.attr="disabled"
                        wire:target="generateContract"
                        title="Genera una nuova versione del contratto (PDF)"
                    >
                        <span wire:loading.remove wire:target="generateContract">Genera contratto (PDF)</span>
                        <span wire:loading wire:target="generateContract" class="loading loading-spinner loading-sm"></span>
                    </button>
                @else
                    {{-- 🚫 Nessun cliente: niente pulsante (evitiamo contratti “orfani”) --}}
                    <div class="alert alert-warning shadow-sm">
                        <span class="text-sm">
                            Associa prima un cliente al noleggio per poter generare il contratto.
                        </span>
                    </div>
                @endif
            </div>
        @endif

        <div class="grid md:grid-cols-2 gap-5">
            {{-- Colonna: versioni generate (PDF) --}}
            <div class="space-y-2">
                <div class="font-semibold">Versioni generate (PDF)</div>

                @forelse($rental->getMedia('contract') as $m)
                    <div class="flex items-center justify-between rounded-xl border p-3">
                        <div class="text-sm">
                            <span class="mr-2">📄</span>
                            <span class="font-medium">{{ $m->name }}</span>
                            <span class="opacity-70">· {{ $m->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="flex gap-2">
                            {{-- Apri (filled neutral) --}}
                            <a href="{{ $m->getUrl() }}" target="_blank"
                               class="btn btn-sm shadow-none
                                      !bg-neutral !text-neutral-content !border-neutral
                                      hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30">
                                Apri
                            </a>
                            {{-- Elimina (per ora submit classico; l’AJAX lo faremo nella sezione Allegati) --}}
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

                @forelse($rental->getMedia('signatures') as $m)
                    <div class="flex items-center justify-between rounded-xl border p-3">
                        <div class="text-sm">
                            <span class="mr-2">✍️</span>
                            <span class="font-medium">{{ $m->file_name }}</span>
                            <span class="opacity-70">· {{ $m->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="flex gap-2">
                            {{-- Apri (usa preview se disponibile) --}}
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

        {{-- ===================== --}}
        {{-- ✅ NUOVO: FIRME GRAFICHE --}}
        {{-- ===================== --}}
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="font-semibold">Firme grafiche (per contratto)</div>
                <div class="text-xs opacity-70">
                    Carica JPG/PNG (max 4MB)
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-5">
                {{-- Firma Cliente --}}
                <div class="rounded-xl border p-4 space-y-3"
                     x-data="signatureBox({
                        uploadUrl: '{{ route('rentals.signature.customer.store', $rental) }}',
                        deleteUrl: '{{ route('rentals.signature.customer.destroy', $rental) }}',
                        initialUrl: @js($customerSigUrl),
                        disabledExpr: () => (window.Alpine?.store('rental')?.isClosed ?? false)
                     })">

                    <div class="flex items-center justify-between">
                        <div class="font-medium">Firma cliente</div>
                        <span class="badge" :class="hasFile ? 'badge-success' : 'badge-outline'">
                            <span x-text="hasFile ? 'Presente' : 'Assente'"></span>
                        </span>
                    </div>

                    <template x-if="hasFile">
                        <div class="rounded-lg border p-2 bg-base-100">
                            <img :src="fileUrlWithBust" alt="Firma cliente" style="max-height:90px; width:auto;">
                        </div>
                    </template>

                    <template x-if="!hasFile">
                        <div class="alert alert-info">
                            Nessuna firma cliente caricata.
                        </div>
                    </template>

                    <div class="flex gap-2 items-center">
                        <input x-ref="file" type="file" class="hidden" accept="image/png,image/jpeg" @change="upload()">

                        <button type="button"
                                class="btn btn-sm shadow-none
                                    !bg-primary !text-primary-content !border-primary
                                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30"
                                :disabled="loading || disabledExpr()"
                                @click="$refs.file.click()">
                            <span x-show="!loading">Carica firma</span>
                            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
                        </button>

                        {{-- ✅ NUOVO: firma su schermo --}}
                        <button type="button"
                                class="btn btn-sm shadow-none
                                    !bg-neutral !text-neutral-content !border-neutral
                                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30"
                                :disabled="loading || disabledExpr()"
                                @click="openPad()">
                            Firma su schermo
                        </button>

                        <button type="button"
                                class="btn btn-sm shadow-none px-3
                                    !bg-error !text-error-content !border-error
                                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-error/30"
                                :disabled="loading || !hasFile || disabledExpr()"
                                @click="remove()">
                            Rimuovi
                        </button>

                        <span class="text-xs opacity-70" x-text="message"></span>
                    </div>

                    {{-- ✅ Modal firma su schermo --}}
                    <div class="modal" :class="{ 'modal-open': padOpen }" x-cloak>
                        <div class="modal-box max-w-2xl">
                            <h3 class="font-bold text-lg">Firma cliente</h3>
                            <p class="text-sm opacity-70 mt-1">
                                Firma nel riquadro. Poi salva.
                            </p>

                            <div class="mt-4 rounded-xl border p-2 bg-base-100">
                                <canvas x-ref="pad"
                                        class="w-full"
                                        style="height:220px; touch-action:none; display:block;"></canvas>
                            </div>

                            <div class="mt-4 flex items-center justify-between">
                                <button type="button"
                                        class="btn btn-sm shadow-none"
                                        @click="clearPad()"
                                        :disabled="padBusy">
                                    Pulisci
                                </button>

                                <div class="flex gap-2">
                                    <button type="button"
                                            class="btn btn-sm shadow-none"
                                            @click="closePad()"
                                            :disabled="padBusy">
                                        Annulla
                                    </button>

                                    <button type="button"
                                            class="btn btn-sm shadow-none
                                                !bg-primary !text-primary-content !border-primary
                                                hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30"
                                            @click="savePad()"
                                            :disabled="padBusy">
                                        <span x-show="!padBusy">Salva firma</span>
                                        <span x-show="padBusy" class="loading loading-spinner loading-sm"></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="modal-backdrop" @click="closePad()"></div>
                    </div>
                </div>

                {{-- Firma Noleggiante --}}
                <div class="rounded-xl border p-4 space-y-3"
                     x-data="signatureBox({
                        uploadUrl: '{{ route('rentals.signature.lessor.store', $rental) }}',
                        deleteUrl: '{{ route('rentals.signature.lessor.destroy', $rental) }}',
                        initialUrl: @js($lessorOverrideUrl),   // qui gestiamo SOLO override
                        disabledExpr: () => (window.Alpine?.store('rental')?.isClosed ?? false)
                     })">

                    <div class="flex items-center justify-between">
                        <div class="font-medium">Firma noleggiante</div>

                        {{-- Badge “attiva” (default o override) --}}
                        <div class="flex gap-2 items-center">
                            @if($lessorActiveUrl)
                                <span class="badge badge-success">{{ $lessorActiveLabel }}</span>
                            @else
                                <span class="badge badge-outline">Assente</span>
                            @endif
                        </div>
                    </div>

                    {{-- Anteprima firma attiva (default->override) --}}
                    @if($lessorActiveUrl)
                        <div class="rounded-lg border p-2 bg-base-100">
                            <img src="{{ $lessorActiveUrl }}" alt="Firma noleggiante" style="max-height:90px; width:auto;">
                            <div class="text-xs opacity-70 mt-1">
                                Firma attiva: <strong>{{ $lessorActiveLabel }}</strong>
                            </div>
                            @if($orgDefaultSigUrl && $lessorOverrideUrl)
                                <div class="text-xs opacity-70">
                                    Nota: l’override è salvato ma verrà usato solo se manca la firma aziendale di default (precedenza attuale).
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="alert alert-info">
                            Nessuna firma noleggiante disponibile (né default aziendale né override sul noleggio).
                        </div>
                    @endif

                    {{-- Gestione override (upload/delete) --}}
                    <div class="flex gap-2 items-center">
                        <input x-ref="file" type="file" class="hidden" accept="image/png,image/jpeg" @change="upload()">

                        <button type="button"
                                class="btn btn-sm shadow-none
                                       !bg-primary !text-primary-content !border-primary
                                       hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30"
                                :disabled="loading || disabledExpr()"
                                @click="$refs.file.click()">
                            <span x-show="!loading">Carica override</span>
                            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
                        </button>

                        <button type="button"
                                class="btn btn-sm shadow-none px-3
                                       !bg-error !text-error-content !border-error
                                       hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-error/30"
                                :disabled="loading || !hasFile || disabledExpr()"
                                @click="remove()">
                            Rimuovi override
                        </button>

                        <span class="text-xs opacity-70" x-text="message"></span>
                    </div>

                    <div class="text-xs opacity-70">
                        L’override viene salvato sul noleggio. La firma aziendale di default si imposta nel profilo (step successivo).
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@once
<script>
    function signatureBox({ uploadUrl, deleteUrl, initialUrl, disabledExpr }) {
        return {
            uploadUrl,
            deleteUrl,
            fileUrl: initialUrl || null,
            bust: Date.now(),
            loading: false,
            message: '',
            disabledExpr: disabledExpr || (() => false),

            // ====== PAD FIRMA ======
            padOpen: false,
            padBusy: false,
            _padCtx: null,
            _drawing: false,
            _last: null,

            get hasFile() { return !!this.fileUrl; },
            get fileUrlWithBust() {
                if (!this.fileUrl) return '';
                const sep = this.fileUrl.includes('?') ? '&' : '?';
                return this.fileUrl + sep + 't=' + this.bust;
            },

            async upload() {
                if (this.disabledExpr()) return;
                const input = this.$refs.file;
                if (!input?.files?.length) return;

                this.loading = true;
                this.message = '';

                try {
                    const fd = new FormData();
                    fd.append('file', input.files[0]);

                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                    const res = await fetch(this.uploadUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                        body: fd,
                    });

                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) throw new Error(data.message || 'Upload non riuscito.');

                    this.fileUrl = data.url || this.fileUrl;
                    this.bust = Date.now();
                    this.message = 'Caricata.';
                } catch (e) {
                    this.message = e?.message || 'Errore upload.';
                } finally {
                    this.loading = false;
                    this.$refs.file.value = '';
                }
            },

            async remove() {
                if (this.disabledExpr()) return;
                if (!this.fileUrl) return;

                this.loading = true;
                this.message = '';

                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                    const res = await fetch(this.deleteUrl, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    });

                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) throw new Error(data.message || 'Rimozione non riuscita.');

                    this.fileUrl = null;
                    this.bust = Date.now();
                    this.message = 'Rimossa.';
                } catch (e) {
                    this.message = e?.message || 'Errore rimozione.';
                } finally {
                    this.loading = false;
                }
            },

            // ===========================
            // ✅ FIRMA SU SCHERMO (CANVAS)
            // ===========================
            openPad() {
                if (this.disabledExpr()) return;
                this.padOpen = true;
                this.$nextTick(() => this.initPad());
            },
            closePad() {
                if (this.padBusy) return;
                this.padOpen = false;
            },
            clearPad() {
                const c = this.$refs.pad;
                if (!c || !this._padCtx) return;
                this._padCtx.clearRect(0, 0, c.width, c.height);
                // sfondo bianco (utile per jpg/pdf)
                this._padCtx.fillStyle = '#fff';
                this._padCtx.fillRect(0, 0, c.width, c.height);
            },
            initPad() {
                const c = this.$refs.pad;
                if (!c) return;

                // Canvas hi-dpi
                const ratio = window.devicePixelRatio || 1;
                const rect = c.getBoundingClientRect();
                c.width  = Math.floor(rect.width * ratio);
                c.height = Math.floor(rect.height * ratio);

                const ctx = c.getContext('2d');
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0); // disegno in CSS pixels
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.lineWidth = 2;

                this._padCtx = ctx;
                this._drawing = false;
                this._last = null;

                // fondo bianco
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, c.width, c.height);

                // pointer events (mouse + touch)
                c.onpointerdown = (e) => {
                    e.preventDefault();
                    this._drawing = true;
                    this._last = this._pointFromEvent(c, e);
                };
                c.onpointermove = (e) => {
                    if (!this._drawing) return;
                    e.preventDefault();
                    const p = this._pointFromEvent(c, e);
                    this._stroke(this._last, p);
                    this._last = p;
                };
                const end = (e) => {
                    if (!this._drawing) return;
                    e.preventDefault();
                    this._drawing = false;
                    this._last = null;
                };
                c.onpointerup = end;
                c.onpointercancel = end;
                c.onpointerleave = end;
            },
            _pointFromEvent(canvas, e) {
                const r = canvas.getBoundingClientRect();
                return { x: e.clientX - r.left, y: e.clientY - r.top };
            },
            _stroke(a, b) {
                if (!this._padCtx || !a || !b) return;
                this._padCtx.strokeStyle = '#111';
                this._padCtx.beginPath();
                this._padCtx.moveTo(a.x, a.y);
                this._padCtx.lineTo(b.x, b.y);
                this._padCtx.stroke();
            },
            async savePad() {
                if (this.disabledExpr()) return;
                const c = this.$refs.pad;
                if (!c) return;

                this.padBusy = true;
                this.message = '';

                try {
                    const blob = await new Promise((resolve) => c.toBlob(resolve, 'image/png'));
                    if (!blob) throw new Error('Impossibile generare immagine firma.');

                    const fd = new FormData();
                    fd.append('file', blob, 'firma-cliente.png');

                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                    const res = await fetch(this.uploadUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                        body: fd,
                    });

                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) throw new Error(data.message || 'Salvataggio firma non riuscito.');

                    this.fileUrl = data.url || this.fileUrl;
                    this.bust = Date.now();
                    this.message = 'Firma salvata.';
                    this.padOpen = false;
                } catch (e) {
                    this.message = e?.message || 'Errore salvataggio firma.';
                } finally {
                    this.padBusy = false;
                }
            },
        }
    }
</script>
@endonce

