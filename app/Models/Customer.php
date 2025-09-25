<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modello: Customer
 * - Cliente finale del noleggiatore.
 */
class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id','name','email','phone','doc_id_type','doc_id_number',
        'birthdate','address_line','city','province','postal_code','country_code','notes',
    ];

    protected $casts = [
        'birthdate' => 'date',
    ];

    public function organization() { return $this->belongsTo(Organization::class); }
    public function rentals()      { return $this->hasMany(Rental::class); }

    // Scope: per organizzazione
    public function scopeForOrganization($q, int $orgId) { return $q->where('organization_id', $orgId); }
}
