<?php

/**
 * Config contratti di noleggio.
 *
 * - Le clausole qui sotto sono testi base, pensati per essere mostrati nel PDF contratto.
 * - Non contengono numeri variabili (tariffe, franchigie, km, ecc.): quelli arrivano da rental/pricing/coverage.
 * - I segnaposto tra {GRAFFE} sono opzionali: li puoi sostituire a runtime se vuoi personalizzarli.
 *
 * Best practice:
 * - Per personalizzazioni per organizzazione, in futuro puoi caricare testi da DB (es. organization_settings)
 *   e fonderli con questi di default (config) prima del render del PDF.
 */

return [

    // Testi delle clausole (contratto)
    'clauses' => [

        // Responsabilità generale del cliente
        'responsabilita' => <<<'TEXT'
            Il Cliente si impegna a custodire e utilizzare il veicolo con la diligenza del buon padre di famiglia.
            È responsabile di ogni danno derivante da uso improprio o negligente, nonché delle sanzioni e oneri
            (amministrativi o fiscali) connessi all’utilizzo del veicolo nel periodo di noleggio.
            Restano a carico del Cliente i danni non coperti dalle garanzie opzionali selezionate e le relative franchigie.
            TEXT,

        // Norme d’uso del veicolo
        'utilizzo' => <<<'TEXT'
            È vietato il sub-noleggio, il trasporto di sostanze pericolose, la conduzione su aree non idonee o interdette,
            la partecipazione a gare o competizioni, nonché qualunque uso in violazione del Codice della Strada.
            Il Cliente è tenuto a controllare periodicamente lo stato del veicolo (indicatori di bordo) e a
            sospendere l’utilizzo in presenza di anomalie, avvisando immediatamente il noleggiante.
            TEXT,

        // Coperture assicurative e franchigie (RC base sempre inclusa)
        'coperture' => <<<'TEXT'
            La copertura RC obbligatoria è sempre inclusa. Eventuali coperture opzionali (Kasko, Furto/Incendio, Cristalli,
            Assistenza stradale) si intendono attive solo se esplicitamente selezionate nel presente contratto.
            Le relative franchigie (se previste) si applicano secondo gli importi indicati nel riepilogo economico.
            TEXT,

        // Riconsegna (pieno a pieno, pulizia, accessori)
        'riconsegna' => <<<'TEXT'
            Il veicolo deve essere riconsegnato entro la data/ora previste, nello stesso luogo indicato
            o in quello concordato con il noleggiante, in condizioni di normale usura d’uso.
            La consegna avviene con serbatoio pieno; la riconsegna deve avvenire con serbatoio pieno (pieno/pieno).
            Sono a carico del Cliente eventuali costi per mancato pieno, eccessivo stato di sporcizia o mancanza accessori.
            TEXT,

        // Penali e compensazioni su cauzione + overage chilometrico
        'penali' => <<<'TEXT'
            Eventuali penali, addebiti accessori e oneri (inclusi rifornimento, pulizia straordinaria, accessori mancanti,
            ritardi nella riconsegna, franchigie per danni) saranno compensati prioritariamente sulla cauzione ove prevista.
            Qualora i chilometri percorsi superino i chilometri inclusi totali, l’eccedenza sarà addebitata
            al prezzo al km extra indicato nel contratto.
            TEXT,
    ],
];
