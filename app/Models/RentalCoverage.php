<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modello: RentalCoverage
 *
 * Rappresenta le coperture e le franchigie associate ad un noleggio (1:1).
 * - Colonne booleane per le coperture (rca, kasko, furto_incendio, cristalli, assistenza).
 * - Colonne decimal(10,2) per le franchigie in EUR (nullable).
 * - Note testuali opzionali.
 */
class RentalCoverage extends Model
{
    // Nome tabella esplicito per chiarezza
    protected $table = 'rental_coverages';

    /**
     * Attributi assegnabili in massa.
     * Manteniamo i nomi esattamente come in migration.
     */
    protected $fillable = [
        'rental_id',
        'rca',
        'kasko',
        'furto_incendio',
        'cristalli',
        'assistenza',
        'franchise_rca',
        'franchise_kasko',
        'franchise_furto_incendio',
        'franchise_cristalli',
        'notes',
    ];

    /**
     * Cast per tipi coerenti:
     * - boolean per le coperture
     * - decimal: stringhe numeriche formattate con due decimali (Laravel 10+/12)
     */
    protected $casts = [
        'rca'                    => 'boolean',
        'kasko'                  => 'boolean',
        'furto_incendio'         => 'boolean',
        'cristalli'              => 'boolean',
        'assistenza'             => 'boolean',
        'franchise_rca'          => 'decimal:2',
        'franchise_kasko'        => 'decimal:2',
        'franchise_furto_incendio'=> 'decimal:2',
        'franchise_cristalli'    => 'decimal:2',
    ];

    /**
     * Relazione inversa: la coverage appartiene ad un Rental.
     */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
