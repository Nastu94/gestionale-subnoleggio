<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: blocca l’accesso se l’Organization dell’utente è in soft delete.
 *
 * Copre il caso:
 * - utente già autenticato
 * - admin archivia l’organizzazione
 * - alla prima request utile l’utente viene sloggato e reindirizzato
 *
 * NB: usiamo withTrashed() per rilevare l’Organization anche se archiviata.
 */
class EnsureOrganizationIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Se non c’è un utente autenticato, non facciamo nulla.
        if (! $user) {
            return $next($request);
        }

        /**
         * Gli admin devono poter entrare sempre nell’area di gestione.
         * Evita auto-lockout accidentali e mantiene la gestione operativa.
         */
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return $next($request);
        }

        // Se l’utente non ha organization_id, non possiamo applicare la regola.
        if (empty($user->organization_id)) {
            return $next($request);
        }

        /**
         * Recupero Organization includendo anche le archiviate (soft delete),
         * così possiamo rilevare correttamente lo stato.
         */
        $org = Organization::withTrashed()->find($user->organization_id);

        // Se non troviamo l’organizzazione, lasciamo proseguire (scenario anomalo).
        if (! $org) {
            return $next($request);
        }

        /**
         * Applichiamo il blocco solo ai renter:
         * - se l’organizzazione è archiviata → logout + redirect alla pagina informativa
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
