<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // abilita authorize()/authorizeResource()
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;       // ⚠️ deve estendere questo

/**
 * Controller base dell’applicazione.
 *
 * - Estende Illuminate\Routing\Controller per avere ->middleware().
 * - Usa AuthorizesRequests per authorize()/authorizeResource().
 * - Usa ValidatesRequests per helper di validazione.
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
