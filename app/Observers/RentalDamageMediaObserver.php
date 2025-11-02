<?php

namespace App\Observers;

use App\Models\RentalDamage;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;
use Illuminate\Support\Facades\Log;

/**
 * Observer per mantenere allineato rental_damages.photos_count
 * quando si aggiungono/rimuovono media sulla collection "photos"
 * del modello RentalDamage.
 *
 * Nota: osserviamo direttamente il modello Media di Spatie.
 */
class RentalDamageMediaObserver
{
    /**
     * Verifica se il media riguarda RentalDamage e la collection "photos".
     */
    protected function isDamagePhoto(SpatieMedia $media): bool
    {
        return $media->model_type === RentalDamage::class
            && $media->collection_name === 'photos';
    }

    /**
     * Ricalcola il totale foto per il danno e lo salva in photos_count.
     */
    protected function recomputeCountFor(SpatieMedia $media): void
    {
        $damageId = (int) $media->model_id;

        // Calcolo conteggio attuale su tabella media (più robusto di ++/--)
        $count = SpatieMedia::query()
            ->where('model_type', RentalDamage::class)
            ->where('model_id',   $damageId)
            ->where('collection_name', 'photos')
            ->count();

        // Aggiorno campo denormalizzato sul danno
        RentalDamage::whereKey($damageId)->update(['photos_count' => $count]);

        Log::debug('[DamagePhotos][recompute]', [
            'damage_id' => $damageId,
            'count'     => $count,
        ]);
    }

    /**
     * Trigger su CREATE.
     */
    public function created(SpatieMedia $media): void
    {
        if (! $this->isDamagePhoto($media)) return;
        $this->recomputeCountFor($media);
    }

    /**
     * Trigger su DELETE (soft/hard: Spatie usa delete normale).
     */
    public function deleted(SpatieMedia $media): void
    {
        if (! $this->isDamagePhoto($media)) return;
        $this->recomputeCountFor($media);
    }

    /**
     * Se usi forceDelete in qualche punto, copri anche questo caso.
     */
    public function forceDeleted(SpatieMedia $media): void
    {
        if (! $this->isDamagePhoto($media)) return;
        $this->recomputeCountFor($media);
    }
}
