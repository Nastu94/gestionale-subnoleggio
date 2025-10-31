{{-- resources/views/livewire/rentals/checklist-form.blade.php --}}
<div class="max-w-4xl mx-auto px-4 py-6">
    {{-- Form: in questo step effettua solo validazione (submit() Livewire) --}}
    <form wire:submit.prevent="submit" class="space-y-8">

        {{-- Tipo checklist (non editabile) --}}
        <div>
            <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Tipo checklist') }}
            </span>
            <span class="inline-flex items-center mt-1 rounded-md px-2 py-0.5 text-xs font-semibold
                         bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-100">
                {{ $type }}
            </span>
            @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Chilometraggio (con controllo >= km attuali veicolo) --}}
        <div>
            <label for="mileage" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Chilometraggio') }}
            </label>
            <input
                type="number"
                id="mileage"
                wire:model.lazy="mileage"
                min="0" step="1"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800"
            />
            <div class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                {{ __('Km attuali veicolo:') }}
                <strong>
                    {{ $current_vehicle_mileage !== null ? number_format($current_vehicle_mileage, 0, ',', '.') : '—' }} km
                </strong>
            </div>
            @error('mileage') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Carburante (%) slider 0..100 con aggiornamento live --}}
        <div>
            <label for="fuel_percent" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Carburante (%)') }}
            </label>
            <input
                type="range"
                id="fuel_percent"
                wire:model.live="fuel_percent"
                min="0" max="100" step="1"
                class="mt-2 w-full"
            />
            <div class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                {{ __('Livello:') }} <strong>{{ $fuel_percent ?? 0 }}%</strong>
            </div>
            @error('fuel_percent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Pulizia --}}
        <div>
            <label for="cleanliness" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Pulizia') }}
            </label>
            <select id="cleanliness" wire:model="cleanliness"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                <option value="">{{ __('Seleziona...') }}</option>
                <option value="poor">Scarsa</option>
                <option value="fair">Giusta</option>
                <option value="good">Buona</option>
                <option value="excellent">Eccellente</option>
            </select>
            @error('cleanliness') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Firme (flag) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label class="inline-flex items-center">
                <input type="checkbox" wire:model="signed_by_customer"
                       class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Firmata dal cliente') }}</span>
            </label>
            <label class="inline-flex items-center">
                <input type="checkbox" wire:model="signed_by_operator"
                       class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Firmata dall’operatore') }}</span>
            </label>
        </div>

        {{-- Foto contachilometri (singolo) - AJAX via controller --}}
        @php
            // Queste variabili devono essere fornite dal componente (dopo salvataggio):
            // $checklistId = ID della RentalChecklist
            $checklistId = $checklistId ?? null;
        @endphp

        <div class="mt-6"
             x-data="checklistOdometerUploader({
                checklistId: {{ $checklistId ? (int) $checklistId : 'null' }},
                uploadUrlBase: '{{ url('/checklists') }}',
                destroyUrlBase: '{{ url('/media') }}',
                csrf: '{{ csrf_token() }}',
                onUploaded(uuid){ $wire.set('checklist.photos.odometer_media_uuid', uuid) },
             })"
             wire:ignore>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Foto contachilometri') }}
            </label>

            <div class="mt-1 flex items-center gap-3">
                <input type="file" accept="image/jpeg,image/png" x-ref="file" class="block w-full text-sm" />
                <button type="button"
                        :disabled="!state.checklistId || state.loading || !$refs.file.files.length"
                        @click="upload()"
                        class="btn btn-primary shadow-none px-3
                               !bg-primary !text-primary-content !border-primary
                               hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                               disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ __('Carica') }}
                </button>
            </div>

            <template x-if="!state.checklistId">
                <p class="mt-2 text-xs text-amber-600">
                    {{ __('Per caricare la foto, salva prima la checklist (serve l’ID).') }}
                </p>
            </template>

            {{-- Mini-tabella (max 1 riga) --}}
            <div class="mt-3 overflow-x-auto" x-show="rows.length">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-600 dark:text-gray-300">
                            <th class="py-1 pr-4">{{ __('Nome file') }}</th>
                            <th class="py-1 pr-4">{{ __('Dimensione') }}</th>
                            <th class="py-1 pr-4">{{ __('Azioni') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-800 dark:text-gray-200">
                        <template x-for="row in rows" :key="row.media_id">
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="py-1 pr-4" x-text="row.name"></td>
                                <td class="py-1 pr-4" x-text="formatSize(row.size)"></td>
                                <td class="py-1 pr-4">
                                    <a :href="row.url" target="_blank"
                                       class="inline-flex items-center px-2 py-1 text-xs rounded-md border border-gray-300 hover:bg-gray-50
                                              dark:border-gray-600 dark:hover:bg-gray-700">
                                        {{ __('Apri') }}
                                    </a>
                                    <button type="button" @click="remove(row)"
                                            class="ml-2 inline-flex items-center px-2 py-1 text-xs rounded-md
                                                   border border-red-300 text-red-700 hover:bg-red-50
                                                   dark:border-red-600 dark:text-red-300 dark:hover:bg-red-900/20">
                                        {{ __('Elimina') }}
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Foto indicatore carburante (singolo) - AJAX via controller --}}
        <div class="mt-6"
             x-data="checklistFuelGaugeUploader({
                checklistId: {{ $checklistId ? (int) $checklistId : 'null' }},
                uploadUrlBase: '{{ url('/checklists') }}',
                destroyUrlBase: '{{ url('/media') }}',
                csrf: '{{ csrf_token() }}',
                onUploaded(uuid){ $wire.set('checklist.photos.fuel_gauge_media_uuid', uuid) },
             })"
             wire:ignore>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Foto indicatore carburante') }}
            </label>

            <div class="mt-1 flex items-center gap-3">
                <input type="file" accept="image/jpeg,image/png" x-ref="file" class="block w-full text-sm" />
                <button type="button"
                        :disabled="!state.checklistId || state.loading || !$refs.file.files.length"
                        @click="upload()"
                        class="btn btn-primary shadow-none px-3
                               !bg-primary !text-primary-content !border-primary
                               hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                               disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ __('Carica') }}
                </button>
            </div>

            <template x-if="!state.checklistId">
                <p class="mt-2 text-xs text-amber-600">
                    {{ __('Per caricare la foto, salva prima la checklist (serve l’ID).') }}
                </p>
            </template>

            <div class="mt-3 overflow-x-auto" x-show="rows.length">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-600 dark:text-gray-300">
                            <th class="py-1 pr-4">{{ __('Nome file') }}</th>
                            <th class="py-1 pr-4">{{ __('Dimensione') }}</th>
                            <th class="py-1 pr-4">{{ __('Azioni') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-800 dark:text-gray-200">
                        <template x-for="row in rows" :key="row.media_id">
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="py-1 pr-4" x-text="row.name"></td>
                                <td class="py-1 pr-4" x-text="formatSize(row.size)"></td>
                                <td class="py-1 pr-4">
                                    <a :href="row.url" target="_blank"
                                       class="inline-flex items-center px-2 py-1 text-xs rounded-md border border-gray-300 hover:bg-gray-50
                                              dark:border-gray-600 dark:hover:bg-gray-700">
                                        {{ __('Apri') }}
                                    </a>
                                    <button type="button" @click="remove(row)"
                                            class="ml-2 inline-flex items-center px-2 py-1 text-xs rounded-md
                                                   border border-red-300 text-red-700 hover:bg-red-50
                                                   dark:border-red-600 dark:text-red-300 dark:hover:bg-red-900/20">
                                        {{ __('Elimina') }}
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Foto esterni (multipli) - AJAX via controller --}}
        <div class="mt-6"
             x-data="checklistExteriorsUploader({
                checklistId: {{ $checklistId ? (int) $checklistId : 'null' }},
                uploadUrlBase: '{{ url('/checklists') }}',
                destroyUrlBase: '{{ url('/media') }}',
                csrf: '{{ csrf_token() }}',
                initialUuids: @js($checklist['photos']['exterior_media_uuids'] ?? []),
                onSynced(uuids){ $wire.set('checklist.photos.exterior_media_uuids', uuids) },
             })"
             wire:ignore>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Foto esterni (multiple)') }}
            </label>

            <div class="mt-1 flex items-center gap-3">
                <input type="file" accept="image/jpeg,image/png" multiple x-ref="files" class="block w-full text-sm" />
                <button type="button"
                        :disabled="!state.checklistId || state.loading || !$refs.files.files.length"
                        @click="uploadMultiple()"
                        class="btn btn-primary shadow-none px-3
                               !bg-primary !text-primary-content !border-primary
                               hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                               disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ __('Carica') }}
                </button>
            </div>

            <template x-if="!state.checklistId">
                <p class="mt-2 text-xs text-amber-600">
                    {{ __('Per caricare le foto, salva prima la checklist (serve l’ID).') }}
                </p>
            </template>

            <div class="mt-3 overflow-x-auto" x-show="rows.length">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-600 dark:text-gray-300">
                            <th class="py-1 pr-4">{{ __('Nome file') }}</th>
                            <th class="py-1 pr-4">{{ __('Dimensione') }}</th>
                            <th class="py-1 pr-4">{{ __('Azioni') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-800 dark:text-gray-200">
                        <template x-for="row in rows" :key="row.media_id">
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="py-1 pr-4" x-text="row.name"></td>
                                <td class="py-1 pr-4" x-text="formatSize(row.size)"></td>
                                <td class="py-1 pr-4">
                                    <a :href="row.url" target="_blank"
                                       class="inline-flex items-center px-2 py-1 text-xs rounded-md border border-gray-300 hover:bg-gray-50
                                              dark:border-gray-600 dark:hover:bg-gray-700">
                                        {{ __('Apri') }}
                                    </a>
                                    <button type="button" @click="remove(row)"
                                            class="ml-2 inline-flex items-center px-2 py-1 text-xs rounded-md
                                                   border border-red-300 text-red-700 hover:bg-red-50
                                                   dark:border-red-600 dark:text-red-300 dark:hover:bg-red-900/20">
                                        {{ __('Elimina') }}
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ __('Danni') }}</h3>
                <button type="button"
                        wire:click="addDamage"
                        class="btn btn-primary shadow-none px-3
                               !bg-primary !text-primary-content !border-primary
                               hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30">
                    +
                </button>
            </div>

            <div class="mt-4 space-y-6">
                @foreach($damage_items as $index => $damage)
                    @php
                        // $damageIds è un array [index => damageId], valorizzato dopo il salvataggio.
                        $damageId = isset($damageIds) && is_array($damageIds) && array_key_exists($index, $damageIds)
                            ? $damageIds[$index]
                            : null;
                    @endphp

                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ __('Danno #') }}{{ $index + 1 }}
                            </span>
                            <button type="button"
                                    wire:click="removeDamage({{ $index }})"
                                    class="inline-flex items-center px-2 py-1 text-xs rounded-md
                                           border border-red-300 text-red-700 hover:bg-red-50
                                           dark:border-red-600 dark:text-red-300 dark:hover:bg-red-900/20">
                                {{ __('Rimuovi') }}
                            </button>
                        </div>

                        <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Area --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Area') }}
                                </label>
                                <input type="text"
                                       wire:model.lazy="damage_items.{{ $index }}.area"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                                @error("damage_items.$index.area") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Gravità --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Gravità') }}
                                </label>
                                <select wire:model="damage_items.{{ $index }}.severity"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                    <option value="minor">Minimo</option>
                                    <option value="moderate">Moderato</option>
                                    <option value="major">Grave</option>
                                </select>
                                @error("damage_items.$index.severity") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Descrizione --}}
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Descrizione') }}
                                </label>
                                <textarea rows="2"
                                          wire:model.lazy="damage_items.{{ $index }}.description"
                                          class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800"></textarea>
                                @error("damage_items.$index.description") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Preesistente --}}
                            <div>
                                <label class="inline-flex items-center mt-2">
                                    <input type="checkbox"
                                           wire:model="damage_items.{{ $index }}.preexisting"
                                           class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        {{ __('Danno preesistente') }}
                                    </span>
                                </label>
                                @error("damage_items.$index.preexisting") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Uploader AJAX foto danno (multipli) --}}
                            <div class="md:col-span-2 mt-2"
                                 x-data="damagePhotosUploader({
                                    damageId: {{ $damageId ? (int) $damageId : 'null' }},
                                    uploadUrlBase: '{{ url('/damages') }}',
                                    destroyUrlBase: '{{ url('/media') }}',
                                    csrf: '{{ csrf_token() }}',
                                 })"
                                 wire:ignore>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Foto del danno (multiple)') }}
                                </label>
                                <div class="mt-1 flex items-center gap-3">
                                    <input type="file" accept="image/jpeg,image/png" multiple x-ref="files" class="block w-full text-sm" />
                                    <button type="button"
                                            :disabled="!state.damageId || state.loading || !$refs.files.files.length"
                                            @click="uploadMultiple()"
                                            class="btn btn-primary shadow-none px-3
                                                   !bg-primary !text-primary-content !border-primary
                                                   hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                                                   disabled:opacity-50 disabled:cursor-not-allowed">
                                        {{ __('Carica') }}
                                    </button>
                                </div>

                                <template x-if="!state.damageId">
                                    <p class="mt-2 text-xs text-amber-600">
                                        {{ __('Per caricare le foto del danno, salva prima il danno (serve l’ID).') }}
                                    </p>
                                </template>

                                <div class="mt-2 overflow-x-auto" x-show="rows.length">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="text-left text-gray-600 dark:text-gray-300">
                                                <th class="py-1 pr-4">{{ __('Nome file') }}</th>
                                                <th class="py-1 pr-4">{{ __('Dimensione') }}</th>
                                                <th class="py-1 pr-4">{{ __('Azioni') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-gray-800 dark:text-gray-200">
                                            <template x-for="row in rows" :key="row.media_id">
                                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                                    <td class="py-1 pr-4" x-text="row.name"></td>
                                                    <td class="py-1 pr-4" x-text="formatSize(row.size)"></td>
                                                    <td class="py-1 pr-4">
                                                        <a :href="row.url" target="_blank"
                                                           class="inline-flex items-center px-2 py-1 text-xs rounded-md border border-gray-300 hover:bg-gray-50
                                                                  dark:border-gray-600 dark:hover:bg-gray-700">
                                                            {{ __('Apri') }}
                                                        </a>
                                                        <button type="button" @click="remove(row)"
                                                                class="ml-2 inline-flex items-center px-2 py-1 text-xs rounded-md
                                                                       border border-red-300 text-red-700 hover:bg-red-50
                                                                       dark:border-red-600 dark:text-red-300 dark:hover:bg-red-900/20">
                                                            {{ __('Elimina') }}
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Submit (solo validazione in questo step) --}}
        <div class="pt-4">
            <button type="submit" class="btn btn-primary shadow-none px-4
                       !bg-primary !text-primary-content !border-primary
                       hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                       disabled:opacity-50 disabled:cursor-not-allowed">
                {{ __('Conferma dati (senza salvare)') }}
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('alpine:init', () => {
    // --- Util: formatta dimensione ---
    const fmt = (bytes) => (bytes == null ? '—' : `${Math.round(bytes / 1024)} KB`);

    // === ODOMETRO (singolo file) ===
    Alpine.data('checklistOdometerUploader', (opts) => ({
        state: { checklistId: opts.checklistId, loading: false },
        rows: [],
        uploadUrl() { return `${opts.uploadUrlBase}/${this.state.checklistId}/media/photo`; }, // POST /checklists/{id}/media/photo
        destroyUrl(id) { return `${opts.destroyUrlBase}/${id}`; },                                // DELETE /media/{id}
        async upload() {
            if (!this.state.checklistId) return;
            const file = this.$refs.file.files[0]; if (!file) return;

            const fd = new FormData(); fd.append('file', file);
            this.state.loading = true;
            try {
                const res = await fetch(this.uploadUrl(), { method:'POST', headers:{ 'X-CSRF-TOKEN': opts.csrf }, body: fd });
                if (!res.ok) throw new Error('Upload fallito');
                const data = await res.json();
                this.rows = [{ media_id:data.media_id, uuid:data.uuid, url:data.url, name:data.name, size:data.size }];
                if (opts.onUploaded && data.uuid) opts.onUploaded(data.uuid);
                this.$refs.file.value = '';
            } catch(e){ console.error(e); alert('Errore durante il caricamento.'); }
            finally { this.state.loading = false; }
        },
        async remove(row) {
            if (!confirm('Eliminare questo file?')) return;
            const res = await fetch(this.destroyUrl(row.media_id), { method:'DELETE', headers:{ 'X-CSRF-TOKEN': opts.csrf } });
            if (!res.ok) { alert('Errore durante l’eliminazione.'); return; }
            this.rows = [];
            opts.onUploaded && opts.onUploaded(null);
        },
        formatSize: fmt,
    }));

    // === INDICATORE CARBURANTE (singolo file) ===
    Alpine.data('checklistFuelGaugeUploader', (opts) => ({
        state: { checklistId: opts.checklistId, loading: false },
        rows: [],
        uploadUrl() { return `${opts.uploadUrlBase}/${this.state.checklistId}/media/photo`; },
        destroyUrl(id) { return `${opts.destroyUrlBase}/${id}`; },
        async upload() {
            if (!this.state.checklistId) return;
            const file = this.$refs.file.files[0]; if (!file) return;

            const fd = new FormData(); fd.append('file', file);
            this.state.loading = true;
            try {
                const res = await fetch(this.uploadUrl(), { method:'POST', headers:{ 'X-CSRF-TOKEN': opts.csrf }, body: fd });
                if (!res.ok) throw new Error('Upload fallito');
                const data = await res.json();
                this.rows = [{ media_id:data.media_id, uuid:data.uuid, url:data.url, name:data.name, size:data.size }];
                if (opts.onUploaded && data.uuid) opts.onUploaded(data.uuid);
                this.$refs.file.value = '';
            } catch(e){ console.error(e); alert('Errore durante il caricamento.'); }
            finally { this.state.loading = false; }
        },
        async remove(row) {
            if (!confirm('Eliminare questo file?')) return;
            const res = await fetch(this.destroyUrl(row.media_id), { method:'DELETE', headers:{ 'X-CSRF-TOKEN': opts.csrf } });
            if (!res.ok) { alert('Errore durante l’eliminazione.'); return; }
            this.rows = [];
            opts.onUploaded && opts.onUploaded(null);
        },
        formatSize: fmt,
    }));

    // === ESTERNI (multipli) ===
    Alpine.data('checklistExteriorsUploader', (opts) => ({
        state: { checklistId: opts.checklistId, loading: false },
        rows: [],
        uuids: Array.isArray(opts.initialUuids) ? [...opts.initialUuids] : [],
        uploadUrl() { return `${opts.uploadUrlBase}/${this.state.checklistId}/media/photo`; },
        destroyUrl(id) { return `${opts.destroyUrlBase}/${id}`; },
        async uploadMultiple() {
            if (!this.state.checklistId) return;
            const files = this.$refs.files.files; if (!files.length) return;

            this.state.loading = true;
            try {
                for (const file of files) {
                    const fd = new FormData(); fd.append('file', file);
                    const res = await fetch(this.uploadUrl(), { method:'POST', headers:{ 'X-CSRF-TOKEN': opts.csrf }, body: fd });
                    if (!res.ok) throw new Error('Upload fallito');
                    const data = await res.json();
                    this.rows.push({ media_id:data.media_id, uuid:data.uuid, url:data.url, name:data.name, size:data.size });
                    if (data.uuid) this.uuids.push(data.uuid);
                }
                // sincronizza la lista UUID su Livewire JSON
                opts.onSynced && opts.onSynced(this.uuids);
                this.$refs.files.value = '';
            } catch(e){ console.error(e); alert('Errore durante il caricamento.'); }
            finally { this.state.loading = false; }
        },
        async remove(row) {
            if (!confirm('Eliminare questo file?')) return;
            const res = await fetch(this.destroyUrl(row.media_id), { method:'DELETE', headers:{ 'X-CSRF-TOKEN': opts.csrf } });
            if (!res.ok) { alert('Errore durante l’eliminazione.'); return; }
            this.rows = this.rows.filter(r => r.media_id !== row.media_id);
            this.uuids = this.uuids.filter(u => u !== row.uuid);
            opts.onSynced && opts.onSynced(this.uuids);
        },
        formatSize: fmt,
    }));

    // === FOTO DANNO (multipli per blocco) ===
    Alpine.data('damagePhotosUploader', (opts) => ({
        state: { damageId: opts.damageId, loading: false },
        rows: [],
        uploadUrl() { return `${opts.uploadUrlBase}/${this.state.damageId}/media/photo`; }, // POST /damages/{id}/media/photo
        destroyUrl(id) { return `${opts.destroyUrlBase}/${id}`; },                            // DELETE /media/{id}
        async uploadMultiple() {
            if (!this.state.damageId) return;
            const files = this.$refs.files.files; if (!files.length) return;

            this.state.loading = true;
            try {
                for (const file of files) {
                    const fd = new FormData(); fd.append('file', file);
                    const res = await fetch(this.uploadUrl(), { method:'POST', headers:{ 'X-CSRF-TOKEN': opts.csrf }, body: fd });
                    if (!res.ok) throw new Error('Upload fallito');
                    const data = await res.json();
                    this.rows.push({ media_id:data.media_id, uuid:data.uuid, url:data.url, name:data.name, size:data.size });
                }
                this.$refs.files.value = '';
            } catch(e){ console.error(e); alert('Errore durante il caricamento.'); }
            finally { this.state.loading = false; }
        },
        async remove(row) {
            if (!confirm('Eliminare questo file?')) return;
            const res = await fetch(this.destroyUrl(row.media_id), { method:'DELETE', headers:{ 'X-CSRF-TOKEN': opts.csrf } });
            if (!res.ok) { alert('Errore durante l’eliminazione.'); return; }
            this.rows = this.rows.filter(r => r.media_id !== row.media_id);
        },
        formatSize: fmt,
    }));
});
</script>
