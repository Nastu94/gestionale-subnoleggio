@props([
    // Durata di visualizzazione ms
    'duration' => 3000,
])

{{-- Toast system: ascolta eventi browser "toast" (Livewire $this->dispatch('toast', ...)) --}}
<div
    x-data="{
        items: [],
        uuid() {
            // Usa randomUUID se disponibile; altrimenti fallback RFC4122 v4-like
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }
            const rnd = (n = 1) => {
                if (window.crypto && window.crypto.getRandomValues) {
                    return window.crypto.getRandomValues(new Uint8Array(n));
                }
                // fallback non-crypto (accettabile per un id UI del toast)
                return Array.from({ length: n }, () => Math.floor(Math.random() * 256));
            };
            const bytes = rnd(16);
            // Versione 4 + varianti corrette
            bytes[6] = (bytes[6] & 0x0f) | 0x40;
            bytes[8] = (bytes[8] & 0x3f) | 0x80;
            const toHex = b => b.toString(16).padStart(2, '0');
            const hex = Array.from(bytes, toHex).join('');
            return `${hex.substr(0,8)}-${hex.substr(8,4)}-${hex.substr(12,4)}-${hex.substr(16,4)}-${hex.substr(20)}`;
        },
        add(evt) {
            const d = evt?.detail;
            let payload = {};
            if (typeof d === 'string') {
                payload = { message: d, type: 'info' };
            } else if (Array.isArray(d)) {
                const first = d[0] ?? {};
                payload = (typeof first === 'object' && first !== null)
                    ? first
                    : { message: String(first ?? ''), type: 'info' };
            } else if (typeof d === 'object' && d !== null) {
                payload = d;
            } else {
                payload = { message: '', type: 'info' };
            }

            const id = this.uuid(); // 👈 usa il fallback
            const type = payload.type ?? 'info';
            const message = payload.message ?? '';
            const timeout = payload.duration ?? 3000;
            if (!message) return;

            this.items.push({ id, type, message });
            setTimeout(() => this.remove(id), timeout);
        },
        remove(id) { this.items = this.items.filter(i => i.id !== id) },
        icon(t) { return t === 'success' ? '✓' : (t === 'error' ? '✕' : (t === 'warning' ? '!' : 'ℹ')) },
        classes(t) {
            return { success:'bg-emerald-600', error:'bg-rose-600', warning:'bg-amber-600', info:'bg-slate-700' }[t] || 'bg-slate-700';
        }
    }"
    x-on:toast.window="add($event)"
    class="fixed inset-0 z-[90] pointer-events-none"
    aria-live="polite" aria-atomic="true"
>
    <div class="absolute right-4 top-4 flex w-full max-w-sm flex-col gap-2">
        <template x-for="t in items" :key="t.id">
            <div class="pointer-events-auto text-white rounded shadow-lg"
                 :class="classes(t.type)">
                <div class="flex items-start gap-2 p-3">
                    <span class="mt-0.5 text-sm" x-text="icon(t.type)"></span>
                    <div class="text-sm" x-text="t.message"></div>
                    <button type="button"
                            class="ml-auto rounded bg-white/20 px-2 text-xs"
                            x-on:click="remove(t.id)">
                        Chiudi
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>
