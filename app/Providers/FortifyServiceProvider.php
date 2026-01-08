<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Actions\Fortify\RedirectIfOrganizationTrashed;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Log;


class FortifyServiceProvider extends ServiceProvider
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

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        /**
         * Personalizzazione pipeline login:
         * - esegue AttemptToAuthenticate (credenziali valide => utente autenticato)
         * - poi blocca l’accesso se l’organizzazione renter è archiviata
         * - infine prepara sessione se tutto ok
         *
         * Così evitiamo "auth.failed" e reindirizziamo correttamente alla pagina bloccata.
         */
        Fortify::authenticateThrough(function (Request $request) {
            return array_filter([
                /**
                 * DEBUG: log prima del tentativo autenticazione
                 * - ci dice se l'utente esiste
                 * - se è soft-deleted
                 * - se is_active è false
                 * - se la sua organization è soft-deleted
                 */
                function (Request $request, $next) {
                    $usernameField = Fortify::username();
                    $identifier = (string) $request->input($usernameField);

                    $u = User::withTrashed()->where($usernameField, $identifier)->first();

                    $org = null;
                    if ($u && !empty($u->organization_id)) {
                        $org = Organization::withTrashed()->find($u->organization_id);
                    }

                    return $next($request);
                },

                // Rate limiting login (se configurato)
                config('fortify.limiters.login') ? EnsureLoginIsNotThrottled::class : null,

                // Gestione 2FA (se attiva)
                RedirectIfTwoFactorAuthenticatable::class,

                // Tenta autenticazione (se fallisce -> auth.failed standard)
                AttemptToAuthenticate::class,

                /**
                 * DEBUG: log subito dopo AttemptToAuthenticate
                 * - se qui NON arriva mai, significa che AttemptToAuthenticate fallisce
                 */
                function (Request $request, $next) {

                    return $next($request);
                },

                // Blocco post-auth se org renter archiviata
                RedirectIfOrganizationTrashed::class,

                // Prepara sessione autenticata
                PrepareAuthenticatedSession::class,
            ]);
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
