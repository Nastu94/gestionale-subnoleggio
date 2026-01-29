<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Rental;
use App\Models\RentalChecklist;
use App\Models\MediaEmailDelivery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Observer Media (Spatie Media Library)
 *
 * Invia mail quando viene creato un Media in collection di documenti firmati.
 * - Cliente: TRACCIATO su media_email_deliveries
 * - Admin: NON TRACCIATO (best effort)
 */
class MediaObserver
{
    /**
     * Collection "normalizzate" che triggerano invio.
     *
     * @var array<int, string>
     */
    private array $signedCollections = [
        'checklist_pickup_signed',
        'checklist_return_signed',
        'signatures',
    ];

    public function created(Media $media): void
    {
        // Normalizza subito (gestisce legacy naming)
        $normalizedCollection = $this->normalizeCollectionName((string) $media->collection_name);

        if (!in_array($normalizedCollection, $this->signedCollections, true)) {
            return;
        }

        $owner = $media->model;
        $rental = $this->resolveRental($owner);

        if (!$rental instanceof Rental) {
            Log::warning('MediaObserver: impossibile risalire al Rental per media', [
                'media_id'         => $media->getKey(),
                'collection_name'  => $media->collection_name,
                'owner_type'       => is_object($owner) ? get_class($owner) : gettype($owner),
            ]);
            return;
        }

        $customer = $this->resolveCustomer($rental);
        $customerEmail = ($customer instanceof Customer && !empty($customer->email))
            ? (string) $customer->email
            : '';

        $adminEmail = (string) config('rentals.admin_email');

        // Se non abbiamo nessun destinatario, stop
        if ($customerEmail === '' && $adminEmail === '') {
            return;
        }

        $rentalLabel = $rental->reference ?? $rental->display_number_label ?? ('#' . $rental->getKey());

        $subjectBase = $this->subjectForCollection($normalizedCollection);
        $subject = $subjectBase . ' - Noleggio ' . $rentalLabel;

        $body = "In allegato trovi il documento firmato relativo al tuo noleggio ({$rentalLabel}).\n\n"
            . "Se non riconosci questa email, contatta l'assistenza.";

        // Documento logico per dedup:
        // - Contratto: Rental + signatures
        // - Checklist: RentalChecklist + checklist_*_signed (così combacia con i metodi manuali)
        $docModelType = Rental::class;
        $docModelId   = (int) $rental->getKey();

        if ($owner instanceof RentalChecklist) {
            $docModelType = RentalChecklist::class;
            $docModelId   = (int) $owner->getKey();
        }

        $docCollection = $normalizedCollection;
        $mediaId = (int) $media->getKey();

        // Spostiamo a fine request: il file potrebbe non essere ancora scritto su storage al "created".
        app()->terminating(function () use (
            $mediaId,
            $customerEmail,
            $adminEmail,
            $subject,
            $body,
            $docModelType,
            $docModelId,
            $docCollection,
            $rentalLabel
        ): void {
            try {
                $freshMedia = Media::query()->find($mediaId);
                if (!$freshMedia) {
                    return;
                }

                $normalizedCollection = $this->normalizeCollectionName((string) $freshMedia->collection_name);
                if ($normalizedCollection !== $docCollection) {
                    return;
                }

                $disk = (string) $freshMedia->disk;
                $relativePath = $freshMedia->getPathRelativeToRoot();

                if (!Storage::disk($disk)->exists($relativePath)) {
                    // Se dovevamo inviare al cliente: log fallimento
                    if ($customerEmail !== '') {
                        Log::warning('MediaObserver: file non trovato su storage anche a fine request', [
                            'media_id' => $freshMedia->getKey(),
                            'disk'     => $disk,
                            'path'     => $relativePath,
                        ]);

                        $delivery = MediaEmailDelivery::query()->firstOrNew([
                            'model_type'      => $docModelType,
                            'model_id'        => $docModelId,
                            'collection_name' => $docCollection,
                        ]);

                        $delivery->recipient_email    = $customerEmail;
                        $delivery->current_media_id   = (int) $freshMedia->getKey();
                        $delivery->first_media_id     = $delivery->first_media_id ?: (int) $freshMedia->getKey();
                        $delivery->status             = MediaEmailDelivery::STATUS_FAILED;
                        $delivery->last_attempt_at    = now();
                        $delivery->send_attempts      = (int) $delivery->send_attempts + 1;
                        $delivery->last_error_message = 'File non trovato su storage (exists=false)';
                        $delivery->save();
                    }

                    // Admin: puoi decidere se notificare o no. Io NON invio senza allegato.
                    return;
                }

                // ====== INVIO CLIENTE (TRACCIATO) ======
                $sentToCustomer = false;

                if ($customerEmail !== '') {
                    $delivery = MediaEmailDelivery::query()->firstOrNew([
                        'model_type'      => $docModelType,
                        'model_id'        => $docModelId,
                        'collection_name' => $docCollection,
                    ]);

                    $isNewRow = !$delivery->exists;
                    $previousCurrent = $delivery->current_media_id;

                    $delivery->recipient_email  = $customerEmail;
                    $delivery->current_media_id = (int) $freshMedia->getKey();

                    if ($isNewRow && is_null($delivery->first_media_id)) {
                        $delivery->first_media_id = (int) $freshMedia->getKey();
                    }

                    $hasBeenSentOnce = !is_null($delivery->first_sent_at);

                    if ($hasBeenSentOnce && !is_null($previousCurrent) && (int) $previousCurrent !== (int) $delivery->current_media_id) {
                        $delivery->status = MediaEmailDelivery::STATUS_REGENERATED;
                        $delivery->regenerations_count = (int) $delivery->regenerations_count + 1;
                        $delivery->last_regenerated_at = now();
                    }

                    $delivery->save();

                    $shouldSend = is_null($delivery->first_sent_at)
                        || $delivery->status === MediaEmailDelivery::STATUS_RESEND_REQUESTED;

                    if ($shouldSend) {
                        $delivery->send_attempts      = (int) $delivery->send_attempts + 1;
                        $delivery->last_attempt_at    = now();
                        $delivery->last_error_message = null;
                        $delivery->save();

                        Mail::raw($body, function ($message) use ($customerEmail, $subject, $disk, $relativePath, $freshMedia): void {
                            $message
                                ->to($customerEmail)
                                ->subject($subject)
                                ->from(config('mail.from.address'), config('mail.from.name'));

                            if (method_exists($message, 'attachFromStorageDisk')) {
                                $message->attachFromStorageDisk($disk, $relativePath, $freshMedia->file_name, ['mime' => $freshMedia->mime_type]);
                            } else {
                                $fileContents = Storage::disk($disk)->get($relativePath);
                                $message->attachData($fileContents, $freshMedia->file_name, ['mime' => $freshMedia->mime_type]);
                            }
                        });

                        if (is_null($delivery->first_sent_at)) {
                            $delivery->first_sent_at = now();
                            if (is_null($delivery->first_media_id)) {
                                $delivery->first_media_id = (int) $freshMedia->getKey();
                            }
                        }

                        $delivery->last_sent_at       = now();
                        $delivery->last_sent_media_id = (int) $freshMedia->getKey();
                        $delivery->status             = MediaEmailDelivery::STATUS_SENT;
                        $delivery->save();

                        $sentToCustomer = true;
                    }
                }

                // ====== INVIO ADMIN (NON TRACCIATO) ======
                // Regola: lo invio quando ho effettivamente inviato al cliente.
                // Eccezione: se cliente non ha email, posso comunque notificare admin (senza DB).
                $shouldSendAdmin = ($adminEmail !== '') && ($sentToCustomer || $customerEmail === '');

                if ($shouldSendAdmin) {
                    try {
                        $adminSubject = '[ADMIN] ' . $subject;

                        $adminBody = ($customerEmail === '')
                            ? "È stato generato un documento firmato, ma il cliente non ha email.\n\n"
                                . "Noleggio: {$rentalLabel}\n"
                                . "Collection: {$docCollection}\n\n"
                                . "Documento in allegato."
                            : "È stato inviato un documento firmato al cliente.\n\n"
                                . "Noleggio: {$rentalLabel}\n"
                                . "Collection: {$docCollection}\n"
                                . "Email cliente: {$customerEmail}\n\n"
                                . "Documento in allegato.";

                        Mail::raw($adminBody, function ($message) use ($adminEmail, $adminSubject, $disk, $relativePath, $freshMedia): void {
                            $message
                                ->to($adminEmail)
                                ->subject($adminSubject)
                                ->from(config('mail.from.address'), config('mail.from.name'));

                            if (method_exists($message, 'attachFromStorageDisk')) {
                                $message->attachFromStorageDisk($disk, $relativePath, $freshMedia->file_name, ['mime' => $freshMedia->mime_type]);
                            } else {
                                $fileContents = Storage::disk($disk)->get($relativePath);
                                $message->attachData($fileContents, $freshMedia->file_name, ['mime' => $freshMedia->mime_type]);
                            }
                        });
                    } catch (\Throwable $ignored) {
                        // best effort: nessun log, nessun DB
                    }
                }
            } catch (\Throwable $e) {
                Log::error('MediaObserver: errore durante invio mail documento firmato', [
                    'media_id'   => $mediaId,
                    'exception'  => $e->getMessage(),
                ]);

                // Log DB solo se esiste email cliente
                if ($customerEmail !== '') {
                    try {
                        $delivery = MediaEmailDelivery::query()->firstOrNew([
                            'model_type'      => $docModelType,
                            'model_id'        => $docModelId,
                            'collection_name' => $docCollection,
                        ]);

                        $delivery->recipient_email    = $customerEmail;
                        $delivery->current_media_id   = $mediaId;
                        $delivery->status             = MediaEmailDelivery::STATUS_FAILED;
                        $delivery->last_attempt_at    = now();
                        $delivery->send_attempts      = (int) $delivery->send_attempts + 1;
                        $delivery->last_error_message = $e->getMessage();
                        $delivery->save();
                    } catch (\Throwable $inner) {
                        // evita loop
                    }
                }
            }
        });
    }

    private function resolveRental(mixed $owner): ?Rental
    {
        if ($owner instanceof Rental) {
            return $owner;
        }

        if (is_object($owner) && method_exists($owner, 'rental')) {
            $rental = $owner->rental;
            if ($rental instanceof Rental) {
                return $rental;
            }
        }

        if (is_object($owner) && isset($owner->rental_id) && !empty($owner->rental_id)) {
            return Rental::query()->find($owner->rental_id);
        }

        return null;
    }

    private function resolveCustomer(Rental $rental): ?Customer
    {
        if (method_exists($rental, 'customer')) {
            $customer = $rental->customer;
            if ($customer instanceof Customer) {
                return $customer;
            }
        }

        if (isset($rental->customer_id) && !empty($rental->customer_id)) {
            return Customer::query()->find($rental->customer_id);
        }

        return null;
    }

    private function normalizeCollectionName(string $collectionName): string
    {
        return match ($collectionName) {
            'checklists_return_signed'  => 'checklist_return_signed',
            'checklists_pickup_signed'  => 'checklist_pickup_signed',
            'rental-contract-signed'    => 'signatures', // compat
            default => $collectionName,
        };
    }

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
