// resources/js/checklist-alpine.js
document.addEventListener('alpine:init', () => {
  // Store condiviso per stato checklist
  if (!Alpine.store('checklist')) {
    Alpine.store('checklist', { locked: false });
  }

  // -- Upload firmato --
  Alpine.data('uploadSignedChecklist', (cfg) => ({
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
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (csrf) form.append('_token', csrf);
      form.append('file', file);

      this.state.loading = true;
      try {
        const res  = await fetch(cfg.url, { method:'POST', body: form, headers:{ 'Accept':'application/json' } });
        const data = await res.clone().json().catch(() => ({}));

        if (!res.ok || data.ok === false) {
          const msg = data.message || 'Upload firmato non riuscito.';
          window.dispatchEvent(new CustomEvent('toast', { detail:{ type:'error', message: msg }}));
          return;
        }

        // Lock immediato su tutta la UI
        this.state.locked = true;
        try { Alpine.store('checklist').locked = true; } catch {}
        window.dispatchEvent(new CustomEvent('toast', { detail:{ type:'success', message:'Checklist firmata caricata. Checklist bloccata.' }}));

        // Notifica Livewire per ricaricare i dati lato server
        try { window.Livewire?.dispatch?.('checklist-signed-uploaded', { rentalId: cfg.rentalId ?? null }); } catch {}
      } catch {
        window.dispatchEvent(new CustomEvent('toast', { detail:{ type:'error', message:'Errore di rete.' }}));
      } finally {
        this.state.loading = false;
        input.value = '';
      }
    },
  }));

  // -- Delete media --
  Alpine.data('ajaxDeleteMedia', () => ({
    loading: false,

    async submit(e) {
      // Blocca se checklist lockata
      if (Alpine.store('checklist')?.locked) return;

      if (this.loading) return;
      const form = e.target;

      if (!window.confirm('Eliminare questa foto? L’operazione non è reversibile.')) return;

      this.loading = true;
      try {
        const res = await fetch(form.action, {
          method: 'POST', // @method('DELETE') nel form
          headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
          body: new FormData(form),
          credentials: 'same-origin',
        });

        let data = null;
        try { data = await res.clone().json(); } catch {}

        if (!res.ok || (data && data.ok === false)) {
          const msg = (data && (data.message || data.error))
            || (res.status === 419 ? 'Sessione scaduta. Ricarica la pagina.'
            :  res.status === 403 ? 'Permesso negato.'
            :  res.status === 423 ? 'Checklist bloccata: non puoi eliminare foto.'
            : 'Eliminazione non riuscita.');
          window.dispatchEvent(new CustomEvent('toast', { detail:{ type:'error', message: msg }}));
          return;
        }

        window.dispatchEvent(new CustomEvent('toast', { detail:{ type:'success', message:'Foto eliminata.' }}));
        try { window.Livewire?.dispatch?.('checklist-media-updated'); } catch {}
      } catch {
        window.dispatchEvent(new CustomEvent('toast', { detail:{ type:'error', message:'Errore di rete.' }}));
      } finally {
        this.loading = false;
      }
    },
  }));

  // -- Upload foto --
  Alpine.data('checklistUpload', () => ({
    sending: false,

    async send(form) {
      if (this.sending) return;
      if (Alpine.store('checklist')?.locked) return; // blocca se lockata
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
        try { data = await res.clone().json(); } catch {}

        if (!res.ok || (data && data.ok === false)) {
          const msg = (data && (data.message || data.error)) || 'Upload non riuscito.';
          window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: msg }}));
          return;
        }

        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: 'Foto caricata.' }}));
        try { window.Livewire?.dispatch?.('checklist-media-updated'); } catch {}
        form.reset();
      } catch {
        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Errore di rete.' }}));
      } finally {
        this.sending = false;
      }
    },
  }));
});
