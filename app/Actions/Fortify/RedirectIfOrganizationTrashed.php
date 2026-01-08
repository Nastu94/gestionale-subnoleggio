<?php

namespace App\Actions\Fortify;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * Action Fortify (login pipeline):
 * - viene eseguita DOPO AttemptToAuthenticate (quindi solo se credenziali valide)
 * - se l'organizzazione renter è archiviata (soft delete) => logout + redirect pagina bloccata
 */
class RedirectIfOrganizationTrashed
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function __invoke(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Se non c'è un utente autenticato, prosegui.
        if (! $user) {
            return $next($request);
        }

        /**
         * Gli admin devono poter accedere sempre.
         * method_exists evita errori se non fosse disponibile hasRole.
         */
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return $next($request);
        }

        // Se non è collegato a un'organizzazione, non applichiamo il blocco.
        if (empty($user->organization_id)) {
            return $next($request);
        }

        /**
         * Recupero Organization includendo anche le archiviate.
         * NB: withTrashed() è fondamentale per rilevare lo stato archived.
         */
        $org = Organization::withTrashed()->find($user->organization_id);

        // Se non troviamo l'organizzazione, non blocchiamo (dato incoerente).
        if (! $org) {
            return $next($request);
        }

        /**
         * Blocchiamo solo renter archiviati.
         */
        if ($org->type === 'renter' && $org->trashed()) {
            // Logout dell’utente corrente.
            Auth::guard('web')->logout();

            // Invalida la sessione e rigenera il token CSRF per sicurezza.
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->view('auth.organization-blocked', [], 403);
        }

        return $next($request);
    }
}
