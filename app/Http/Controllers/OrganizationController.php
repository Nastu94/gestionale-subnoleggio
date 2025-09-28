<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationStoreRequest;
use App\Http\Requests\OrganizationUpdateRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Controller Resource: Organization (Gestione renter)
 *
 * - Protezione via Gate 'manage.renters' impostata a livello rotta.
 * - Vista: per ora stub Blade minime (vedi sotto).
 */
class OrganizationController extends Controller
{
    /** 
     * Elenco organizations (renter/admin) con conteggio veicoli assegnati OGGI.
     */
    public function index()
    {
        // 1) Boundaries del giorno corrente nell’app timezone (es. Europe/Rome)
        $startOfToday = now()->startOfDay();
        $endOfToday   = now()->endOfDay();

        /**
         * 2) Elenco renter con colonna calcolata "vehicles_count":
         *    numero di veicoli che risultano assegnati OGGI (overlap sui datetime).
         *
         *    Overlap condition:
         *      - va.start_at <= endOfToday
         *      - AND (va.end_at IS NULL OR va.end_at >= startOfToday)
         *
         *    COUNT(DISTINCT) a prova di doppie assegnazioni “sporche”.
         */
        $organizations = Organization::query()
            ->where('type', 'renter')
            ->select('organizations.*')
            ->selectSub(function ($q) use ($startOfToday, $endOfToday) {
                $q->from('vehicle_assignments as va')
                ->selectRaw('COUNT(DISTINCT va.vehicle_id)')
                ->whereColumn('va.renter_org_id', 'organizations.id')
                ->where('va.start_at', '<=', $endOfToday)
                ->where(function ($q2) use ($startOfToday) {
                    $q2->whereNull('va.end_at')
                        ->orWhere('va.end_at', '>=', $startOfToday);
                });
            }, 'vehicles_count')
            ->orderBy('id')
            ->paginate(15);

        return view('pages.organizations.index', compact('organizations'));
    }

    /** Form creazione */
    public function create()
    {
        return view('pages.organizations.create');
    }

    /**
     * Crea Organization (type='renter') + User principale.
     * - Transazione atomica
     * - Assegna ruolo 'renter' all'utente
     * 
     * Valida con OrganizationStoreRequest.
     * NB: non permettiamo il cambio del tipo (rimane 'renter').
     * 
     * In caso di errore torna indietro con input e messaggio.
     * In caso di successo va alla lista con messaggio.
     * 
     * @return RedirectResponse
     */
    public function store(OrganizationStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($data) {
                // Caso B: aggiungi user a renter esistente
                if (!empty($data['organization_id'])) {
                    $orgId = (int) $data['organization_id'];

                    User::create([
                        'name'            => $data['user_name'],
                        'email'           => $data['user_email'],
                        'password'        => Hash::make($data['user_password']),
                        'organization_id' => $orgId,
                    ])->assignRole('renter');

                    return; // fine: nessun nuovo Organization
                }

                // Caso A: crea nuovo renter + user
                $org = Organization::create([
                    'name' => $data['name'],
                    'type' => 'renter',
                ]);

                User::create([
                    'name'            => $data['user_name'],
                    'email'           => $data['user_email'],
                    'password'        => Hash::make($data['user_password']),
                    'organization_id' => $org->id,
                ])->assignRole('renter');
            });

            return redirect()
                ->route('organizations.index')
                ->with('success', 'Operazione completata: utente creato' . (empty($data['organization_id']) ? ' e renter aggiunto.' : '.'));

        } catch (\Throwable $e) {
            Log::error('OrgStore failed', ['e' => $e->getMessage()]);
            return back()
                ->withInput()
                ->with('error', 'Errore durante il salvataggio. Riprova.');
        }
    }

    /** Dettaglio */
    public function show(Organization $organization)
    {
        return view('pages.organizations.show', compact('organization'));
    }

    /** Form modifica */
    public function edit(Organization $organization)
    {
        return view('pages.organizations.edit', compact('organization'));
    }

    /**
     * Aggiorna Organization + (opzionale) User principale.
     * - Se 'user_id' è inviato, aggiorna quello;
     *   altrimenti aggiorna il primo utente associato
     *   (o ne crea uno se non esiste e sono presenti i campi).
     * 
     * - Transazione atomica
     * - Assegna ruolo 'renter' all'utente (se creato o aggiornato)
     * 
     * Valida con OrganizationUpdateRequest.
     * NB: non permettiamo il cambio del tipo (rimane 'renter').
     * 
     * In caso di errore torna indietro con input e messaggio.
     * In caso di successo va alla lista con messaggio.
     * 
     * @return RedirectResponse
     */
    public function update(OrganizationUpdateRequest $request, Organization $organization): RedirectResponse
    {
        if ($organization->type !== 'renter') {
            abort(404); // hard guard: da questa UI gestiamo solo i renter
        }

        $data = $request->validated();

        try {
            DB::transaction(function () use ($organization, $data) {
                // 1) Aggiorna Organization
                $organization->update(['name' => $data['name']]);

                // 2) Trova/aggiorna User principale (se campi utente presenti)
                $hasUserFields = isset($data['user_name']) || isset($data['user_email']) || isset($data['user_password']);

                if ($hasUserFields) {
                    // Candidato: da user_id, oppure primo utente dell’org
                    $user = null;

                    if (!empty($data['user_id'])) {
                        $user = User::whereKey($data['user_id'])
                            ->where('organization_id', $organization->id)
                            ->first();
                    }

                    if (!$user) {
                        $user = User::where('organization_id', $organization->id)->orderBy('id')->first();
                    }

                    // Se non esiste e ho email+password+name, ne creo uno (opzionale)
                    if (!$user) {
                        if (!empty($data['user_email']) && !empty($data['user_password']) && !empty($data['user_name'])) {
                            $user = User::create([
                                'name'            => $data['user_name'],
                                'email'           => $data['user_email'],
                                'password'        => Hash::make($data['user_password']),
                                'organization_id' => $organization->id,
                            ]);
                            $user->assignRole('renter');
                        }
                        // se non ho tutti i dati necessari, semplicemente esco
                        return;
                    }

                    // Aggiornamento parziale campi presenti
                    $updates = [];
                    if (isset($data['user_name']))  { $updates['name']  = $data['user_name']; }
                    if (isset($data['user_email'])) { $updates['email'] = $data['user_email']; }
                    if (!empty($data['user_password'])) {
                        $updates['password'] = Hash::make($data['user_password']);
                    }

                    if (!empty($updates)) {
                        $user->update($updates);
                    }

                    // Assicurati che l'utente abbia ruolo renter
                    if (! $user->hasRole('renter')) {
                        $user->assignRole('renter');
                    }
                }
            });

            return redirect()
                ->route('organizations.index')
                ->with('success', 'Renter aggiornato correttamente.');
        } catch (\Throwable $e) {
            Log::error('OrgUpdate failed', ['e' => $e->getMessage()]);
            return back()
                ->withInput()
                ->with('error', 'Errore durante l’aggiornamento. Riprova.');
        }
    }

    /** Cancellazione */
    public function destroy(Organization $organization)
    {
        // TODO soft/hard delete
        return redirect()->route('organizations.index');
    }
}
