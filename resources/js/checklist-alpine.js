// resources/js/checklist-alpine.js

document.addEventListener('alpine:init', () => {
  /**
   * x-data="uploadSignedChecklist({ url, locked })"
   * Gestisce upload PDF/JPG/PNG firmato + lock.
   */
  Alpine.data('uploadSignedChecklist', (cfg) => ({
    // Manteniamo i nomi usati nel markup
    state: { locked: !!cfg.locked, loading: false },

    async send(input) {
      if (this.state.locked || this.state.loading) return;

      const file = input?.files?.[0];
      if (!file) return;

      const okTypes = ['application/pdf', 'image/jpeg', 'image/png'];
      if (!okTypes.includes(file.type)) {
        window.dispatchEvent(new CustomEvent('toast', { detail:{ type:'error', message:'Formato non valido (pdf/jpg/png).' }}));
        input.value = '';
        return;
      }

      const form = new FormData();
      form.append('file', file);
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (csrf) form.append('_token', csrf);

      this.state.loading = true;
      try {
        const res  = await fetch(cfg.url, { method:'POST', body: form, headers:{ 'Accept':'application/json' } });
        const data = await res.clone().json().catch(() => ({}));

        if (!res.ok || data.ok === false) {
          const msg = data.message || 'Upload firmato non riuscito.';
          window.dispatchEvent(new CustomEvent('toast', { detail:{ type:'error', message: msg }}));
          return;
        }

        this.state.locked = true;
        window.dispatchEvent(new CustomEvent('toast', { detail:{ type:'success', message:'Checklist firmata caricata. Checklist bloccata.' }}));

        try { window.Livewire?.emit?.('refresh'); } catch {}
      } catch (_) {
        window.dispatchEvent(new CustomEvent('toast', { detail:{ type:'error', message:'Errore di rete.' }}));
      } finally {
        this.state.loading = false;
        input.value = '';
      }
    },
  }));

  /**
   * x-data="ajaxDeleteMedia()"
   * Cancella media rispettando @method('DELETE').
   */
  Alpine.data('ajaxDeleteMedia', () => ({
    loading: false,

    async submit(e) {
      if (this.loading) return;
      const form = e.target;

      if (!window.confirm('Eliminare questa foto? L’operazione non è reversibile.')) return;

      this.loading = true;
      try {
        const res = await fetch(form.action, {
          method: 'POST', // il form contiene @method('DELETE')
          headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
          body: new FormData(form),
          credentials: 'same-origin',
        });

        let data = null;
        try { data = await res.clone().json(); } catch(_) {}

        if (!res.ok || (data && data.ok === false)) {
          const msg = (data && (data.message || data.error))
            || (res.status === 419 ? 'Sessione scaduta. Ricarica la pagina.'
            :  res.status === 403 ? 'Permesso negato.'
            : 'Eliminazione non riuscita.');
          window.dispatchEvent(new CustomEvent('toast', { detail:{ type:'error', message: msg }}));
          return;
        }

        window.dispatchEvent(new CustomEvent('toast', { detail:{ type:'success', message:'Foto eliminata.' }}));
        try { window.Livewire?.emit?.('refresh'); } catch(_) {}
      } catch (_) {
        window.dispatchEvent(new CustomEvent('toast', { detail:{ type:'error', message:'Errore di rete.' }}));
      } finally {
        this.loading = false;
      }
    },
  }));

  /**
   * x-data="checklistUpload()"
   * Upload foto con select "kind".
   * L’endpoint lo prendo dal markup: data-photos-store="..."
   */
  Alpine.data('checklistUpload', () => ({
    sending: false,

    async send(form) {
      if (this.sending) return;
      this.sending = true;

      try {
        const url = form?.dataset?.photosStore;
        const res = await fetch(url, {
          method: 'POST',
          body: new FormData(form),
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
        });

        let data = null;
        try { data = await res.clone().json(); } catch(_) {}

        if (!res.ok || (data && data.ok === false)) {
          const msg = (data && (data.message || data.error)) || 'Upload non riuscito.';
          window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: msg }}));
          return;
        }

        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: 'Foto caricata.' }}));
        try { window.Livewire?.emit?.('refresh'); } catch(_) {}
        form.reset();
      } catch (_) {
        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Errore di rete.' }}));
      } finally {
        this.sending = false;
      }
    },
  }));
});
