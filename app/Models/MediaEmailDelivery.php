<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MediaEmailDelivery
 *
 * Rappresenta lo stato di invio email per un "documento logico" (Spatie Media).
 * Anti-duplicati: chiave composta (model_type, model_id, collection_name).
 */
class MediaEmailDelivery extends Model
{
    /**
     * Tabella associata.
     *
     * @var string
     */
    protected $table = 'media_email_deliveries';

    /**
     * Mass assignment: consentiamo solo campi controllati.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'model_type',
        'model_id',
        'collection_name',
        'recipient_email',
        'first_media_id',
        'current_media_id',
        'last_sent_media_id',
        'status',
        'send_attempts',
        'regenerations_count',
        'first_sent_at',
        'last_sent_at',
        'last_attempt_at',
        'last_regenerated_at',
        'resend_requested_at',
        'last_error_message',
    ];

    /**
     * Cast per timestamp.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'first_sent_at'        => 'datetime',
        'last_sent_at'         => 'datetime',
        'last_attempt_at'      => 'datetime',
        'last_regenerated_at'  => 'datetime',
        'resend_requested_at'  => 'datetime',
    ];

    // Stati supportati (stringhe semplici per compatibilità DB)
    public const STATUS_PENDING          = 'pending';
    public const STATUS_SENT             = 'sent';
    public const STATUS_REGENERATED      = 'regenerated';
    public const STATUS_FAILED           = 'failed';
    public const STATUS_RESEND_REQUESTED = 'resend_requested';
}
