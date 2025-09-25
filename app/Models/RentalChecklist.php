<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modello: RentalChecklist
 * - Una per tipo: pickup | return (vincolo UNIQUE a DB).
 */
class RentalChecklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id','type','mileage','fuel_percent','cleanliness',
        'signed_by_customer','signed_by_operator','signature_media_uuid',
        'checklist_json','created_by',
    ];

    protected $casts = [
        'mileage'            => 'integer',
        'fuel_percent'       => 'integer',
        'signed_by_customer' => 'boolean',
        'signed_by_operator' => 'boolean',
        'checklist_json'     => 'array',
    ];

    public function rental()  { return $this->belongsTo(Rental::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
