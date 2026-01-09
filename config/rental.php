<?php

return [

    /**
     * Testi “legali” stampati nel PDF contratto.
     * Li teniamo in config per:
     * - versioning semplice
     * - niente hardcode nel Blade
     * - eventuale multi-tenant in futuro (override per organization)
     */
    'clauses' => [

        'title' => 'CONDIZIONI GENERALI DI NOLEGGIO',
        'subtitle' => 'ERA DIVISIONE RENT AMD MOBILITY Srls',

        'sections' => [
            [
                'n' => 1,
                'title' => 'Requisiti per il noleggio',
                'body' => implode("\n", [
                    "Per noleggiare un veicolo è necessario:",
                    "",
                    "• avere almeno 21 anni compiuti e patente di guida categoria B in corso di validità da almeno 12 mesi; per veicoli speciali/luxury l’età minima è 25 anni oltre all’idoneità alla guida da almeno 12 mesi;",
                    "• essere titolare di una carta di credito a garanzia (sono escluse carte prepagate, bancomat e contanti);",
                    "• presentare documento d’identità valido e codice fiscale (o passaporto per i non residenti in Italia).",
                    "",
                    "Eventuali conducenti aggiuntivi devono essere autorizzati dalla Società e comportano un supplemento giornaliero.",
                ]),
            ],
            [
                'n' => 2,
                'title' => 'Deposito cauzionale',
                'body' => implode("\n", [
                    "Al momento della consegna del veicolo verrà bloccato sulla carta di credito del cliente un importo a titolo di cauzione, variabile tra € 600,00 e € 3.500,00 in base alla tipologia di veicolo.",
                    "La cauzione sarà sbloccata alla riconsegna salvo danni, multe e/o contravvenzioni al CdS o altre spese pendenti.",
                    "Tale somma potrà essere trattenuta, in tutto o in parte, dalla Società di Noleggio a copertura di:",
                    "",
                    "• qualsiasi danno subito dal veicolo, ivi compresi danni agli interni, agli accessori, alle parti elettroniche o meccaniche;",
                    "• smarrimento o mancata restituzione di chiavi, documenti, accessori o dispositivi di bordo;",
                    "• scoperti e franchigie assicurative;",
                    "• penali contrattuali previste dal presente accordo;",
                    "• spese di fermo tecnico e di recupero del veicolo;",
                    "• multe e/o contravvenzioni al CdS.",
                    "",
                    "Al termine del noleggio, qualora non vengano rilevati danni o inadempimenti, il deposito cauzionale sarà sbloccato. In caso contrario, la Società di Noleggio tratterrà la somma necessaria, restituendo l’eventuale differenza, previa comunicazione scritta al Cliente.",
                ]),
            ],
            [
                'n' => 3,
                'title' => 'Uso del veicolo',
                'body' => implode("\n", [
                    "Il cliente si impegna a:",
                    "",
                    "• utilizzare il veicolo con diligenza e nel rispetto del Codice della Strada;",
                    "• non affidarlo a terzi non autorizzati;",
                    "",
                    "È severamente vietato affidare, sub-noleggiare, cedere e/o consegnare il veicolo a terzi non autorizzati e non indicati nel contratto di noleggio. Qualsiasi utilizzo del veicolo da parte di soggetti non autorizzati costituisce uso illecito e potrà essere perseguito come appropriazione indebita del mezzo ai sensi dell’Art. 646 Codice Penale, con facoltà per la Società di Noleggio di procedere immediatamente al recupero forzoso del veicolo e alla risoluzione del contratto, fatto salvo il diritto al risarcimento integrale dei danni e dei mancati ricavi;",
                    "",
                    "• non utilizzare il veicolo per gare, competizioni, trasporto merci pericolose o traino;",
                    "• non fumare a bordo del veicolo (penale € 70,00 per sanificazione).",
                ]),
            ],
            [
                'n' => 4,
                'title' => 'Chilometraggio e carburante',
                'body' => implode("\n", [
                    "Il contratto prevede un chilometraggio giornaliero massimo (indicato nella scheda di noleggio). I km eccedenti saranno addebitati secondo tariffario (€ 0,20 – € 0,80/km in base alla categoria).",
                    "Il veicolo viene consegnato con il pieno di carburante e deve essere restituito con il pieno; in caso contrario verranno addebitati il carburante mancante e una penale di € 20,00.",
                ]),
            ],
            [
                'n' => 5,
                'title' => 'Condizioni assicurative e franchigie',
                'body' => implode("\n", [
                    "I veicoli sono coperti da polizza RCA, Kasko, Furto e Incendio, con le seguenti franchigie a carico del cliente:",
                    "",
                    "• RCA: € 1.000,00 per sinistro;",
                    "• Atti vandalici / danni non identificati: € 2.000,00;",
                    "• Furto/Incendio: 25% del valore del veicolo con minimo € 2.500,00.",
                    "",
                    "È possibile ridurre le franchigie (Super Cover) mediante pagamento di un supplemento giornaliero come da tariffario vigente al momento del noleggio.",
                ]),
            ],
            [
                'n' => 6,
                'title' => 'Responsabilità del cliente',
                'body' => implode("\n", [
                    "Sono a carico del cliente:",
                    "",
                    "• tutte le contravvenzioni e/o infrazioni al CdS durante il periodo di noleggio (più € 30,00 per gestione pratica di rinotifica);",
                    "• i pedaggi e le spese di parcheggio;",
                    "• la perdita o lo smarrimento di accessori e documenti:",
                    "  - chiave con telecomando € 500,00 – chiave senza telecomando € 300,00;",
                    "  - libretto di circolazione € 300,00;",
                    "  - targa € 400,00;",
                    "  - giubbotto riflettente € 20,00.",
                ]),
            ],
            [
                'n' => 7,
                'title' => 'Incidenti, furto o danni',
                'body' => implode("\n", [
                    "In caso di sinistro, furto o incendio il cliente è obbligato a:",
                    "",
                    "• informare immediatamente AMD Mobility;",
                    "• presentare denuncia scritta alle autorità competenti entro 24 ore;",
                    "• compilare il modulo CID presente a bordo.",
                    "",
                    "Il mancato rispetto di tali obblighi comporta una penale tra € 500,00 e € 2.000,00 oltre al risarcimento del danno.",
                ]),
            ],
            [
                'n' => 8,
                'title' => 'Riconsegna del veicolo',
                'body' => implode("\n", [
                    "Il veicolo deve essere riconsegnato entro l’orario e nel luogo stabilito.",
                    "",
                    "Ritardi oltre i 30 minuti comportano l’addebito di un giorno extra.",
                    "",
                    "La vettura deve essere riconsegnata pulita internamente ed esternamente; in caso contrario verranno applicate penali di € 20,00 più il costo del lavaggio pari a €. 20,00 e € 120,00 per lavaggio interni con una penale di €. 20,00.",
                ]),
            ],
            [
                'n' => 9,
                'title' => 'Pagamenti',
                'body' => implode("\n", [
                    "Il cliente è tenuto a corrispondere:",
                    "",
                    "• il canone di noleggio pattuito;",
                    "• eventuali supplementi (km extra, pulizia, carburante, accessori);",
                    "• danni e penali come previsto dalle presenti condizioni.",
                    "",
                    "Il pagamento potrà essere addebitato sulla carta di credito lasciata a garanzia anche successivamente alla riconsegna, dietro documentazione giustificativa.",
                ]),
            ],
            [
                'n' => 10,
                'title' => 'Risoluzione e foro competente',
                'body' => implode("\n", [
                    "AMD Mobility si riserva la facoltà di risolvere il contratto in caso di uso improprio del veicolo o mancato pagamento.",
                    "Per ogni controversia è competente in via esclusiva il Foro di Bari, con espressa pattuizione di esclusione di qualsiasi altro foro.",
                ]),
            ],
            [
                'n' => 11,
                'title' => 'Privacy',
                'body' => implode("\n", [
                    "I dati personali forniti saranno trattati da AMD Mobility Srls in conformità al Regolamento UE 2016/679 (GDPR) per le sole finalità connesse all’esecuzione del contratto e agli obblighi di legge.",
                ]),
            ],
            [
                'n' => 12,
                'title' => 'Proroga del noleggio',
                'body' => implode("\n", [
                    "Il Cliente, qualora intenda trattenere la vettura oltre la durata pattuita, è obbligato a darne comunicazione scritta alla Società di Noleggio con almeno 2 (due) giorni di anticipo rispetto alla scadenza contrattuale.",
                    "",
                    "In assenza di tale comunicazione, il trattenimento del veicolo oltre i termini concordati sarà considerato inadempienza contrattuale grave, con facoltà della Società di Noleggio di procedere al recupero immediato del veicolo, addebitando al Cliente i relativi costi di recupero, le penali previste e i canoni giornalieri maggiorati del 200%.",
                ]),
            ],
            [
                'n' => 13,
                'title' => 'Trattamento Dati Sensibili',
                'body' => implode("\n", [
                    "Le parti si autorizzano reciprocamente al trattamento dei dati sensibili per finalità connesse e/o collegate al presente atto.",
                ]),
            ],
        ],
    ],

    /**
     * Configurazioni relative alle firme grafiche.
     */
    'signatures' => [
        // ordine sorgenti per firma noleggiante mostrata nel contratto
        // (tu hai chiesto: prima default aziendale, se manca usa override Rental)
        'lessor_precedence' => ['organization', 'rental'],
    ],
];
