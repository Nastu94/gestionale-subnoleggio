<?php

namespace App\Services\Cargos;

use App\Models\User;

class CargosCheckService
{
    public function __construct(
        protected CargosRentalContextResolver $resolver,
        protected CargosContractPayloadBuilder $builder,
        protected CargosContractFixedWidthFormatter $formatter,
        protected CargosTokenProvider $tokenProvider,
        protected CargosApiClient $api,
    ) {}

    /**
     * @return array{ok:bool, stage:string, errors:array<int,string>, response?:mixed}
     */
    public function checkRental(int $rentalId, ?User $operator = null): array
    {
        try {
            $ctx = $this->resolver->resolveOrFail($rentalId, $operator);
        } catch (\Throwable $e) {
            return ['ok' => false, 'stage' => 'resolver', 'errors' => [$e->getMessage()]];
        }

        $built = $this->builder->build($ctx);
        if (!($built['ok'] ?? false)) {
            return ['ok' => false, 'stage' => 'builder', 'errors' => $built['errors'] ?? ['Errore builder']];
        }

        $fw = $this->formatter->format($built['payload']);
        if (!($fw['ok'] ?? false)) {
            return ['ok' => false, 'stage' => 'formatter', 'errors' => $fw['errors'] ?? ['Errore formatter']];
        }

        try {
            $auth = $this->tokenProvider->getEncryptedBearer();
            $username = $this->tokenProvider->username();

            // Body = array di stringhe (una riga = un contratto)
            $resp = $this->api->check($auth['bearer'], $username, [$fw['record']]);

            // Se è un array di esiti riga-a-riga, estraiamo eventuali errori “umani”
            $errors = [];
            if (array_is_list($resp)) {
                foreach ($resp as $i => $row) {
                    $esito = (bool) ($row['esito'] ?? false);
                    if (!$esito) {
                        $desc = $row['errore']['error_description'] ?? $row['errore']['error'] ?? 'Errore non specificato';
                        $errors[] = 'Riga ' . ($i + 1) . ': ' . $desc;
                    }
                }
            }

            return [
                'ok'     => count($errors) === 0,
                'stage'  => 'api.check',
                'errors' => $errors,
                'response' => $resp,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'stage' => 'api.check', 'errors' => [$e->getMessage()]];
        }
    }
}
