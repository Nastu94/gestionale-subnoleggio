<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modello: RentalPhoto
 * - Foto associate al rental (pickup/return), integrate con Media Library via media_uuid.
 */
class RentalPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id','phase','label','media_uuid','created_by',
    ];

    public function rental()  { return $this->belongsTo(Rental::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
