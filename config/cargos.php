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
        'password' => env('CARGOS_ADMIN_PASSWORD'),
        'puk'      => env('CARGOS_ADMIN_PUK'),
    ],

];
