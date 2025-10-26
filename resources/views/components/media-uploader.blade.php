@props([
    'label'    => 'Upload',
    'action'   => '#',     // URL POST (route) — esattamente quello che già passi col :action
    'accept'   => '',      // es. "application/pdf,image/*"
    'multiple' => false,   // se presente, invia i file uno per uno
])

{{-- 
    Component: <x-media-uploader>
    - Invia i file via fetch() (AJAX) a $action, con header Accept: application/json
    - Mostra toast globali usando l’evento "toast" già definito nel layout
    - Esegue il refresh di Livewire ($wire.$refresh() + fallback emit('refresh'))
    - Supporta un campo opzionale "collection" passato nello SLOT (es. <select name="collection">…</select>)
--}}

<form x-data="mediaUploader('{{ $action }}', {{ $multiple ? 'true' : 'false' }})"
      x-on:submit.prevent
      class="space-y-2" enctype="multipart/form-data">

    @csrf

    <label class="label">
        <span class="label-text font-medium">{{ $label }}</span>
    </label>

    {{-- SLOT opzionale: qui puoi inserire campi extra (es. <select name="collection">) --}}
    @if (trim($slot))
        <div class="mb-1">
            {{ $slot }}
        </div>
    @endif

    <div class="flex items-center gap-2">
        <input type="file"
               x-ref="file"
               name="file" {{-- il controller legge "file" --}}
               accept="{{ $accept }}"
               @change="upload()"
               @if($multiple) multiple @endif
               class="file-input file-input-bordered w-full" />

        <button type="button"
                class="btn"
                @click="$refs.file.click()"
                :disabled="loading">
            <span x-show="!loading">Carica</span>
            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
        </button>
    </div>

    <p class="text-xs opacity-70">Max 20MB.</p>
</form>

{{-- Registrazione una sola volta dell’helper Alpine --}}
<script>
document.addEventListener('alpine:init', () => {
    if (window.__mediaUploaderDefined) return;
    window.__mediaUploaderDefined = true;

    Alpine.data('mediaUploader', (url, isMultiple = false) => ({
        loading: false,

        async upload() {
            if (this.loading) return;

            const form  = this.$root;
            const input = this.$refs.file;
            const files = Array.from(input?.files || []);
            if (!files.length) return;

            // Conferma soft opzionale? (qui no: click singolo basta)
            this.loading = true;

            const token =
                form.querySelector('input[name=_token]')?.value ||
                document.querySelector('meta[name=csrf-token]')?.content;

            // Lettura opzionale del campo "collection" (se presente nello slot)
            const collectionEl = form.querySelector('[name="collection"]');
            const collection   = collectionEl ? collectionEl.value : null;

            let ok = 0, fail = 0, lastError = null;

            // Carichiamo uno per volta (server si aspetta 'file' singolo)
            for (const file of files) {
                const fd = new FormData();
                fd.append('file', file, file.name);
                if (collection) fd.append('collection', collection);

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: fd,
                        credentials: 'same-origin',
                    });

                    // Tenta di leggere JSON; se non è JSON, non crashare
                    let data = null;
                    try { data = await res.clone().json(); } catch (_) {}

                    if (!res.ok || (data && data.ok === false)) {
                        fail++;
                        lastError =
                            (data && (data.message || data.error)) ||
                            (res.status === 419 ? 'Sessione scaduta.' :
                             res.status === 403 ? 'Permesso negato.' :
                             res.status === 422 ? 'File non valido.' :
                             `Errore ${res.status}.`);
                    } else {
                        ok++;
                    }
                } catch (e) {
                    fail++;
                    lastError = 'Errore di rete.';
                }
            }

            // Toast globale
            if (ok > 0 && fail === 0) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'success', message: ok === 1 ? 'File caricato.' : `Caricati ${ok} file.` }
                }));
            } else if (ok > 0 && fail > 0) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'warning', message: `Caricati ${ok} file, ${fail} falliti.` }
                }));
            } else {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'error', message: lastError || 'Upload non riuscito.' }
                }));
            }

            // Reset input + refresh Livewire
            input.value = '';
            try { this.$wire?.$refresh(); } catch(_) {}
            try { window.Livewire?.emit?.('refresh'); } catch(_) {}

            this.loading = false;
        },
    }));
});
</script>
