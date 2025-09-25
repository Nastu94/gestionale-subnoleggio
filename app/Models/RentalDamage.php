<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modello: RentalDamage
 * - Danni rilevati (pickup/return/during).
 */
class RentalDamage extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id','phase','area','severity','description',
        'estimated_cost','photos_count','created_by',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'photos_count'   => 'integer',
    ];

    public function rental()  { return $this->belongsTo(Rental::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
