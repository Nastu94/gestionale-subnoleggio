<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cargos - credenziali Admin
    |--------------------------------------------------------------------------
    | Tenute in .env (non DB). In produzione valuta un secret manager / vault.
    |
    */

    'admin' => [
        'username' => env('CARGOS_ADMIN_USERNAME'),
        'password' => env('CARGOS_ADMIN_PASSWORD'),
        'puk'      => env('CARGOS_ADMIN_PUK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CARGOS Base URL
    |--------------------------------------------------------------------------
    | Es: https://cargos.poliziadistato.it/CARGOS_API/
    */
    'base_url' => env('CARGOS_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Timeout HTTP (secondi)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('CARGOS_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Verify SSL
    |--------------------------------------------------------------------------
    | In locale potresti mettere false, ma in produzione deve stare true.
    */
    'verify_ssl' => (bool) env('CARGOS_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Cargos API key (se richiesta in futuro)
    |--------------------------------------------------------------------------
    */
    'apikey' => env('CARGOS_APIKEY'),
];
