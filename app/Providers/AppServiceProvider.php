<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;
use App\Observers\RentalDamageMediaObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Aggancia l'observer sul modello Media di Spatie.
         * In questo modo intercettiamo create/delete di media associati a RentalDamage.
         */
        SpatieMedia::observe(RentalDamageMediaObserver::class);
    }
}
