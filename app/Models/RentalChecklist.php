<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit; 
use Illuminate\Support\Str;

// Activity Log (Spatie)
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Contracts\Activity;

/**
 * Modello: RentalChecklist
 * - Una per tipo: pickup | return (vincolo UNIQUE a DB).
 * - Supporta lock persistente (Opzione B) dopo upload PDF firmato.
 * - Gestisce riferimento all’ultimo PDF non firmato (per pulsante “Apri”/dirty check).
 * - Facoltativo: sostitutiva (annulla e sostituisce una precedente).
 */
class RentalChecklist extends Model implements SpatieHasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity; // Log attività (create/update/delete + campi selezionati)

    /**
     * Campi assegnabili in massa (non modifichiamo i nomi già presenti).
     */
    protected $fillable = [
        'rental_id', 'type', 'mileage', 'fuel_percent', 'cleanliness',
        'signed_by_customer', 'signed_by_operator', 'signature_media_uuid',
        'checklist_json', 'created_by',

        // --- Nuovi campi (Opzione B + PDF state) ---
        'locked_at', 'locked_by_user_id', 'locked_reason', 'signed_media_id',
        'last_pdf_payload_hash', 'last_pdf_media_id',
        'replaces_checklist_id',
    ];

    /**
     * Cast coerenti con lo schema DB.
     */
    protected $casts = [
        'mileage'              => 'integer',
        'fuel_percent'         => 'integer',
        'signed_by_customer'   => 'boolean',
        'signed_by_operator'   => 'boolean',
        'checklist_json'       => 'array',

        // --- Cast nuovi campi ---
        'locked_at'            => 'datetime',   // timestamp blocco
        'locked_by_user_id'    => 'integer',
        'signed_media_id'      => 'integer',
        'last_pdf_media_id'    => 'integer',
        'replaces_checklist_id'=> 'integer',
    ];

    /* ==========================
       Relazioni principali
       ========================== */

    /** Noleggio padre */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /** Utente creatore della checklist */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Utente che ha “bloccato” la checklist (al momento della firma) */
    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    /** Media “firmato” che ha causato il lock (PDF/immagine) */
    public function signedPdf(): HasOne
    {
        // Collegamento per ID diretto alla tabella media di Spatie
        return $this->hasOne(Media::class, 'id', 'signed_media_id');
    }

    /** Ultimo PDF non firmato generato (per “Apri” e hash dirty) */
    public function lastPdf(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'last_pdf_media_id');
    }

    /** Checklist sostituita (se questa annulla/sostituisce una precedente) */
    public function replacedChecklist(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_checklist_id');
    }

    /** Eventuali checklist che sostituiscono questa (reverse relation) */
    public function replacements(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_checklist_id');
    }

    /* ==========================
       Scopes di utilità
       ========================== */

    /** Scope: solo checklist bloccate */
    public function scopeLocked($query)
    {
        return $query->whereNotNull('locked_at');
    }

    /** Scope: solo checklist sbloccate */
    public function scopeUnlocked($query)
    {
        return $query->whereNull('locked_at');
    }

    /* ==========================
       Helper di dominio
       ========================== */

    /**
     * Ritorna true se la checklist è bloccata (non modificabile).
     */
    public function isLocked(): bool
    {
        return !is_null($this->locked_at);
    }

    /**
     * Nome collection “firmato” attesa per questo record (in base al type).
     * - pickup  => checklist_pickup_signed
     * - return  => checklist_return_signed
     */
    public function signedCollectionName(): string
    {
        return $this->type === 'return'
            ? 'checklist_return_signed'
            : 'checklist_pickup_signed';
    }
    
    // accessor per usare $checklist->signedPdf in Blade
    public function getSignedPdfAttribute(): ?Media
    {
        try {
            return $this->getMedia($this->signedCollectionName())->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /* ==========================
       Media Library (Spatie)
       ========================== */

    /**
     * Collection media registrate per la checklist:
     * - photos: foto associate (odometro, carburante, esterni, ecc.)
     * - checklist_pdfs: PDF non firmati generati dal gestionale (ultima bozza)
     * - checklist_*_signed: PDF/immagine firmata dal cliente (sblocca lock)
     */
    public function registerMediaCollections(): void
    {
        // Foto varie
        $this->addMediaCollection('photos')
            ->useDisk(config('filesystems.default'));

        // PDF non firmati (bozze che si possono rigenerare)
        $this->addMediaCollection('checklist_pdfs')
            ->useDisk(config('filesystems.default'));

        // PDF/immagini firmate → causano lock. singleFile per evitare “doppie firme”.
        $this->addMediaCollection('checklist_pickup_signed')
            ->useDisk(config('filesystems.default'))
            ->singleFile();

        $this->addMediaCollection('checklist_return_signed')
            ->useDisk(config('filesystems.default'))
            ->singleFile();

        // NB: manteniamo anche la tua collection esistente “signatures” se la stai usando altrove.
        $this->addMediaCollection('signatures')
            ->useDisk(config('filesystems.default'));
    }

    /**
     * Conversioni immagini per foto veicolo e firme (no conversion per PDF).
     */
    public function registerMediaConversions(Media $media = null): void
    {
        if ($media && !Str::startsWith($media->mime_type, 'image/')) {
            return; // niente conversioni su PDF
        }

        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 256, 256)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->fit(Fit::Max, 1280, 1280)
            ->keepOriginalImageFormat()
            ->nonQueued();

        $this->addMediaConversion('hd')
            ->fit(Fit::Max, 1920, 1920)
            ->keepOriginalImageFormat()
            ->performOnCollections('photos') // conversione “hd” solo per le foto
            ->nonQueued();
    }

    /* ==========================
       Activity Log (Spatie)
       ========================== */

    /**
     * Configura cosa loggare:
     * - tutti i fillable + nuovi campi di lock/pdf/sostitutiva
     * - solo i cambiamenti (logOnlyDirty)
     * - nessun log vuoto
     */
    public function getActivitylogOptions(): LogOptions
    {
        // Campi da loggare (fillable + nuovi campi già inclusi in $fillable)
        $toLog = $this->fillable;

        return LogOptions::defaults()
            ->logOnly($toLog)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('rental_checklists');
    }

    /**
     * Testo descrittivo per l’evento (create/update/delete).
     */
    public function tapActivity(Activity $activity, string $eventName)
    {
        // Esempio descrizione leggibile
        $activity->description = match ($eventName) {
            'created' => "Creata checklist {$this->type} per rental #{$this->rental_id}",
            'updated' => "Aggiornata checklist {$this->type} per rental #{$this->rental_id}",
            'deleted' => "Eliminata checklist {$this->type} per rental #{$this->rental_id}",
            default   => ucfirst($eventName) . " checklist {$this->type} per rental #{$this->rental_id}",
        };
    }
}
