<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Rental;
use App\Models\MediaEmailDelivery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Observer Media (Spatie Media Library)
 *
 * Invia una mail al cliente quando viene creato un Media
 * appartenente a specifiche collection di "documenti firmati".
 */
class MediaObserver
{
    /**
     * Collection che devono triggerare l'invio mail.
     *
     * @var array<int, string>
     */
    private array $signedCollections = [
        'checklist_pickup_signed',
        'checklist_return_signed',
        'signatures',
    ];

    /**
     * Gestisce l'evento "created" del Media.
     */
    public function created(Media $media): void
    {
        // Filtra subito: non ci interessano le altre collection.
        if (!in_array($media->collection_name, $this->signedCollections, true)) {
            return;
        }

        // Risolve il modello "owner" (morph) a cui è agganciato il Media.
        $owner = $media->model;

        // Prova a risalire al Rental in modo difensivo (senza assumere troppo sul tuo schema).
        $rental = $this->resolveRental($owner);

        if (!$rental instanceof Rental) {
            Log::warning('MediaObserver: impossibile risalire al Rental per media', [
                'media_id' => $media->getKey(),
                'collection_name' => $media->collection_name,
                'owner_type' => is_object($owner) ? get_class($owner) : gettype($owner),
            ]);

            return;
        }

        // Prova a risalire al Customer (cliente) collegato al noleggio.
        $customer = $this->resolveCustomer($rental);

        if (!$customer instanceof Customer || empty($customer->email)) {
            Log::warning('MediaObserver: customer assente o senza email', [
                'media_id' => $media->getKey(),
                'rental_id' => $rental->getKey(),
            ]);

            return;
        }

        // Componi oggetto e corpo. (Testo semplice; lo renderemo "bello" in uno step successivo.)
        $subject = $this->subjectForCollection($media->collection_name);
        $body = "In allegato trovi il documento firmato relativo al tuo noleggio.\n\n"
              . "Se non riconosci questa email, contatta l'assistenza.";

        // Memorizziamo solo dati semplici per usarli a fine request.
        $mediaId       = $media->getKey();
        $customerEmail = $customer->email;

        // Usiamo il Rental come "documento logico" per dedup (1 riga per rental + collection).
        $docModelType  = Rental::class;
        $docModelId    = $rental->getKey();
        $docCollection = $this->normalizeCollectionName($media->collection_name);

        /**
         * Spostiamo tutto a fine request:
         * - Spatie potrebbe non aver ancora scritto il file su storage nel momento del "created".
         */
        app()->terminating(function () use ($mediaId, $customerEmail, $subject, $body, $docModelType, $docModelId, $docCollection): void {
            try {
                /** @var \Spatie\MediaLibrary\MediaCollections\Models\Media|null $freshMedia */
                $freshMedia = Media::query()->find($mediaId);

                if (!$freshMedia) {
                    return;
                }

                // Normalizziamo anche qui per coerenza.
                $normalizedCollection = $this->normalizeCollectionName($freshMedia->collection_name);

                // Safety: se per qualche motivo non matcha più, stop.
                if ($normalizedCollection !== $docCollection) {
                    return;
                }

                $relativePath = $freshMedia->getPathRelativeToRoot();

                // Il file dovrebbe esistere a fine request.
                if (!Storage::disk($freshMedia->disk)->exists($relativePath)) {
                    Log::warning('MediaObserver: file non trovato su storage anche a fine request', [
                        'media_id' => $freshMedia->getKey(),
                        'disk'     => $freshMedia->disk,
                        'path'     => $relativePath,
                    ]);

                    // Log su tabella: segnaliamo fallimento di allegato (senza inviare).
                    $delivery = MediaEmailDelivery::query()->firstOrNew([
                        'model_type'       => $docModelType,
                        'model_id'         => $docModelId,
                        'collection_name'  => $docCollection,
                    ]);

                    if (!$delivery->exists) {
                        $delivery->recipient_email = $customerEmail;
                        $delivery->first_media_id  = $freshMedia->getKey();
                        $delivery->current_media_id = $freshMedia->getKey();
                        $delivery->status          = MediaEmailDelivery::STATUS_FAILED;
                    } else {
                        $delivery->recipient_email  = $customerEmail;
                        $delivery->current_media_id = $freshMedia->getKey();
                        $delivery->status           = MediaEmailDelivery::STATUS_FAILED;
                    }

                    $delivery->last_attempt_at      = now();
                    $delivery->send_attempts         = (int) $delivery->send_attempts + 1;
                    $delivery->last_error_message    = 'File non trovato su storage (exists=false)';

                    $delivery->save();

                    return;
                }

                /**
                 * Recupera/crea riga anti-duplicati per documento logico:
                 * - 1 riga per (Rental + collection)
                 * - aggiorna sempre current_media_id
                 */
                $delivery = MediaEmailDelivery::query()->firstOrNew([
                    'model_type'      => $docModelType,
                    'model_id'        => $docModelId,
                    'collection_name' => $docCollection,
                ]);

                $isNewRow = !$delivery->exists;

                // Aggiorniamo sempre l'email destinatario per audit.
                $delivery->recipient_email = $customerEmail;

                // Prima volta: aggancia "first_media_id"
                if ($isNewRow && is_null($delivery->first_media_id)) {
                    $delivery->first_media_id = $freshMedia->getKey();
                }

                // Se cambia versione, aggiorna "current_media_id"
                $previousCurrent = $delivery->current_media_id;
                $delivery->current_media_id = $freshMedia->getKey();

                // Se era già stata inviata una versione e ora cambia, è una rigenerazione.
                $hasBeenSentOnce = !is_null($delivery->first_sent_at);

                if ($hasBeenSentOnce && !is_null($previousCurrent) && (int) $previousCurrent !== (int) $delivery->current_media_id) {
                    $delivery->status = MediaEmailDelivery::STATUS_REGENERATED;
                    $delivery->regenerations_count = (int) $delivery->regenerations_count + 1;
                    $delivery->last_regenerated_at = now();
                }

                // Salviamo prima la riga (serve che esista sempre).
                $delivery->save();

                /**
                 * Regola invio automatico:
                 * - invia SOLO se non è mai stato inviato con successo (first_sent_at null)
                 * - oppure se è stato richiesto reinvio manuale (resend_requested)
                 */
                $shouldSend = is_null($delivery->first_sent_at)
                    || $delivery->status === MediaEmailDelivery::STATUS_RESEND_REQUESTED;

                if (!$shouldSend) {
                    // Documento rigenerato (o già inviato): non inviamo.
                    return;
                }

                // Legge contenuto file e invia.
                $fileContents = Storage::disk($freshMedia->disk)->get($relativePath);

                // Tracking tentativo
                $delivery->send_attempts    = (int) $delivery->send_attempts + 1;
                $delivery->last_attempt_at  = now();
                $delivery->last_error_message = null;
                $delivery->save();

                Mail::raw($body, function ($message) use ($customerEmail, $subject, $freshMedia, $fileContents): void {
                    $message
                        ->to($customerEmail)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'))
                        ->attachData(
                            $fileContents,
                            $freshMedia->file_name,
                            ['mime' => $freshMedia->mime_type]
                        );
                });

                // Success tracking
                if (is_null($delivery->first_sent_at)) {
                    $delivery->first_sent_at = now();
                    // Se non avevamo ancora settato il first_media_id, lo fissiamo qui.
                    if (is_null($delivery->first_media_id)) {
                        $delivery->first_media_id = $freshMedia->getKey();
                    }
                }

                $delivery->last_sent_at = now();
                $delivery->last_sent_media_id = $freshMedia->getKey();
                $delivery->status = MediaEmailDelivery::STATUS_SENT;

                $delivery->save();
            } catch (\Throwable $e) {
                Log::error('MediaObserver: errore durante invio mail documento firmato', [
                    'media_id' => $mediaId,
                    'exception' => $e->getMessage(),
                ]);

                // Proviamo comunque a registrare il fallimento nel log (best effort).
                try {
                    $delivery = MediaEmailDelivery::query()->firstOrNew([
                        'model_type'      => $docModelType,
                        'model_id'        => $docModelId,
                        'collection_name' => $docCollection,
                    ]);

                    $delivery->recipient_email     = $customerEmail;
                    $delivery->current_media_id    = $mediaId;
                    $delivery->status              = MediaEmailDelivery::STATUS_FAILED;
                    $delivery->last_attempt_at     = now();
                    $delivery->send_attempts       = (int) $delivery->send_attempts + 1;
                    $delivery->last_error_message  = $e->getMessage();

                    $delivery->save();
                } catch (\Throwable $inner) {
                    // Se fallisce anche il logging, evitiamo loop.
                }
            }
        });
    }

    /**
     * Tenta di risalire al Rental a partire dal modello owner del Media.
     *
     * @param  mixed $owner
     */
    private function resolveRental(mixed $owner): ?Rental
    {
        // Caso 1: il Media è direttamente attaccato a Rental.
        if ($owner instanceof Rental) {
            return $owner;
        }

        // Caso 2: il modello owner espone una relazione "rental".
        if (is_object($owner) && method_exists($owner, 'rental')) {
            $rental = $owner->rental;

            if ($rental instanceof Rental) {
                return $rental;
            }
        }

        // Caso 3: il modello owner ha un campo rental_id.
        if (is_object($owner) && isset($owner->rental_id) && !empty($owner->rental_id)) {
            return Rental::query()->find($owner->rental_id);
        }

        return null;
    }

    /**
     * Tenta di risalire al Customer collegato al Rental.
     */
    private function resolveCustomer(Rental $rental): ?Customer
    {
        // Caso 1: relazione "customer" sul Rental.
        if (method_exists($rental, 'customer')) {
            $customer = $rental->customer;

            if ($customer instanceof Customer) {
                return $customer;
            }
        }

        // Caso 2: campo customer_id sul Rental.
        if (isset($rental->customer_id) && !empty($rental->customer_id)) {
            return Customer::query()->find($rental->customer_id);
        }

        return null;
    }

    /**
     * Normalizza il nome della collection per evitare duplicati dovuti a naming diverso.
     */
    private function normalizeCollectionName(string $collectionName): string
    {
        return match ($collectionName) {
            'checklists_return_signed' => 'checklist_return_signed',
            default => $collectionName,
        };
    }

    /**
     * Soggetto email in base alla collection.
     */
    private function subjectForCollection(string $collectionName): string
    {
        return match ($collectionName) {
            'checklist_pickup_signed'  => 'Checklist di consegna firmata',
            'checklist_return_signed'  => 'Checklist di rientro firmata',
            'signatures'               => 'Contratto firmato',
            default                    => 'Documento firmato',
        };
    }
}
