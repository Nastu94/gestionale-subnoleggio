# Gestionale SubNoleggio

Gestionale Laravel per la **gestione del sub-noleggio auto** tra un proprietario del parco veicoli (**admin**) e uno o più **noleggiatori/renter**.

Il sistema copre l'intero ciclo operativo:

- gestione anagrafiche di organizzazioni, sedi, clienti e veicoli
- affidamento dei veicoli dall'admin ai renter
- disponibilità flotta tramite assegnazioni, blocchi e stati veicolo
- creazione e gestione dei noleggi renter → cliente finale
- contratti, checklist, firme, allegati e danni
- addebiti, pagamenti, commissioni e reportistica
- integrazione con **CARGOS** per l'invio normativo dei contratti

---

## Indice

- [Panoramica](#panoramica)
- [Problema che risolve](#problema-che-risolve)
- [Attori del sistema](#attori-del-sistema)
- [Funzionalità principali](#funzionalità-principali)
- [Flussi operativi](#flussi-operativi)
- [Ruoli, permessi e isolamento dati](#ruoli-permessi-e-isolamento-dati)
- [Documenti, media e checklist](#documenti-media-e-checklist)
- [Integrazione CARGOS](#integrazione-cargos)
- [Report e controllo](#report-e-controllo)
- [Stack tecnico](#stack-tecnico)
- [Installazione](#installazione)
- [Struttura funzionale](#struttura-funzionale)
- [Note progettuali](#note-progettuali)

---

## Panoramica

Questo progetto non è un semplice gestionale di autonoleggio “classico”.

È pensato per un modello **multi-organizzazione** in cui:

- l'**admin** possiede il parco veicoli
- i **renter** ricevono veicoli in affidamento per intervalli temporali definiti
- ogni renter gestisce i propri clienti e i propri contratti di noleggio
- l'admin mantiene controllo su flotta, documenti, commissioni, audit e report aggregati

L'obiettivo è avere un unico sistema capace di gestire sia la parte **operativa** sia la parte **documentale e amministrativa** del sub-noleggio.

---

## Problema che risolve

Nel sub-noleggio il veicolo non viene solo "prenotato":

1. deve essere **assegnato** dal proprietario a un renter
2. deve risultare **disponibile** nel periodo corretto
3. deve poter essere usato solo dal renter autorizzato
4. il contratto deve produrre documenti, media, checklist e addebiti coerenti
5. i dati devono restare separati tra organizzazioni diverse
6. alcune informazioni devono essere inviate a sistemi esterni per finalità normative

Il gestionale nasce per tenere insieme questi vincoli in un unico flusso.

---

## Attori del sistema

### Admin

È il proprietario del parco veicoli.

Può:

- creare e gestire i veicoli
- assegnarli ai renter
- controllare disponibilità, manutenzioni, documenti e danni
- configurare fee e regole economiche
- consultare audit e report globali
- gestire le integrazioni di compliance

### Renter

È il noleggiatore operativo.

Può:

- vedere solo i veicoli assegnati al proprio perimetro
- gestire sedi e clienti del proprio tenant
- creare noleggi verso clienti finali
- gestire listini, addebiti, checklist, contratti e pagamenti
- registrare check-out, check-in, danni e media del noleggio

### Cliente finale

È il soggetto che utilizza il veicolo.

Nel sistema vengono gestiti dati anagrafici, patente, documenti, residenza, cittadinanza e informazioni necessarie alla produzione del contratto e all'eventuale invio CARGOS.

---

## Funzionalità principali

### Anagrafiche

- organizzazioni `admin` e `renter`
- sedi operative e punti di ritiro/restituzione
- clienti finali
- parco veicoli

### Flotta

- assegnazioni veicolo → renter
- blocchi calendario
- storico stati veicolo
- manutenzioni
- documenti veicolo con scadenze
- danni persistenti lato veicolo

### Noleggi

- creazione contratto
- numerazione progressiva per noleggiatore
- pianificazione ritiro e rientro
- stato del noleggio (`draft`, `reserved`, `in_use`, `checked_in`, `closed`, `cancelled`, `no_show`)
- seconda guida opzionale
- calcolo importi e override finali

### Contratti e documentazione

- generazione contratto PDF
- snapshot economico del contratto
- checklist di pickup e return
- firme e allegati
- lock documentale sulle checklist concluse

### Economico

- righe economiche del noleggio
- addebiti commissionabili e non commissionabili
- pagamenti registrati
- fee amministrative per renter
- report economici e aggregazioni

### Compliance

- dati compatibili con il tracciato richiesto per CARGOS
- mappature codici e luoghi ufficiali
- flusso di invio contratti verso il servizio esterno

---

## Flussi operativi

## 1. Setup iniziale

Si configurano:

- organizzazioni
- utenti e ruoli
- sedi operative
- parco veicoli
- eventuali mappature utili per compliance e CARGOS

Questo è il livello in cui si definisce chi possiede i veicoli e chi potrà usarli.

## 2. Affidamento veicolo admin → renter

Il renter non lavora su un veicolo solo perché il veicolo esiste nel sistema.

Deve esistere un'**assegnazione** valida che definisce almeno:

- veicolo
- organizzazione renter
- periodo di validità
- stato dell'assegnazione
- eventuali vincoli aggiuntivi

Questo è il cuore del dominio: l'assegnazione determina il perimetro operativo reale del renter.

## 3. Disponibilità del mezzo

La disponibilità non dipende da una singola tabella, ma dalla combinazione di:

- assegnazioni attive
- blocchi temporanei
- altri noleggi sovrapposti
- stato attuale del mezzo

In questo modo il sistema evita sovrapposizioni e utilizzi fuori perimetro.

## 4. Creazione del noleggio

Il renter crea il noleggio selezionando:

- cliente
- veicolo assegnato
- date pianificate di ritiro e rientro
- sedi di pickup e return
- eventuale seconda guida
- note operative

Durante questo passaggio il sistema prepara la base contrattuale ed economica del noleggio.

## 5. Contratto e snapshot economico

Quando il contratto viene generato, il gestionale congela i dati economici principali in uno **snapshot**.

Questo serve a mantenere coerenza tra:

- importi mostrati nel contratto
- km inclusi
- regole applicate al momento della stipula
- eventuali calcoli successivi, come overage chilometrico e fee

In pratica il contratto non resta agganciato in modo fragile a listini che potrebbero cambiare dopo.

## 6. Pagamenti e addebiti

Il noleggio può contenere più righe economiche, ad esempio:

- quota base
- extra
- penali
- overage chilometrico
- voci commissionabili o non commissionabili

Le righe pagate alimentano sia il flusso operativo sia la reportistica.

## 7. Check-out

Alla consegna del veicolo si registrano le informazioni di uscita:

- data/ora effettiva
- chilometraggio di uscita
- carburante in uscita
- checklist pickup
- eventuali danni e foto

Il veicolo entra così nella fase di utilizzo effettivo.

## 8. Durante il noleggio

Il sistema conserva la storia operativa tramite:

- stato del noleggio
- media allegati
- eventuali danni emersi in corso d'uso
- documenti collegati al contratto

## 9. Check-in e chiusura

Al rientro si registrano:

- data/ora effettiva di ritorno
- chilometraggio di ingresso
- carburante in ingresso
- checklist return
- eventuali danni finali

Da questi dati il gestionale può determinare, se previsto:

- km eccedenti
- eventuali extra da addebitare
- possibilità di chiusura amministrativa del noleggio

La chiusura avviene solo quando il record è coerente con i requisiti previsti dal flusso.

## 10. Storico veicolo

Parallelamente al noleggio, il sistema mantiene una traccia storica dello stato del veicolo:

- assegnato
- disponibile
- bloccato
- in noleggio
- rientrato
- soggetto a manutenzione o danno

Questo consente all'admin di ricostruire cosa è successo al mezzo nel tempo.

---

## Ruoli, permessi e isolamento dati

L'applicazione usa un modello combinato di sicurezza:

- **ruoli** (`admin`, `renter`)
- **permessi granulari** per risorsa e azione
- **policy** applicative
- **query scoping** per organizzazione

Questo è importante perché in un progetto multi-tenant i permessi da soli non bastano.

Un renter può avere il permesso di vedere i clienti, ma deve comunque poter vedere **solo i clienti del proprio tenant**. Lo stesso vale per sedi, noleggi, veicoli assegnati e documenti collegati.

In sintesi:

- l'admin governa l'intero ecosistema
- il renter lavora soltanto nel proprio perimetro
- l'accesso diretto via URL o ID non deve bypassare il confine organizzativo

---

## Documenti, media e checklist

Il progetto usa una gestione documentale strutturata per conservare prove operative e allegati.

Sono previsti, tra gli altri:

- contratto di noleggio
- contratto firmato
- firme cliente e noleggiante
- checklist pickup/return
- foto checklist
- foto danni
- documenti cliente
- documenti veicolo

Un aspetto importante è il **lock documentale**: quando una checklist entra nello stato finale previsto, non deve più essere alterabile. Questo protegge il valore probatorio del materiale raccolto.

---

## Integrazione CARGOS

Il gestionale include un'integrazione con **CARGOS**, il servizio usato per l'invio dei dati dei contratti di noleggio secondo il tracciato previsto dal Ministero dell'Interno / Polizia di Stato.

Il flusso, in termini funzionali, prevede:

1. produzione dei dati del contratto nel formato richiesto
2. mappatura dei codici ufficiali necessari
3. gestione autenticazione tramite token
4. cifratura del token con API key
5. invio dei contratti
6. acquisizione dell'esito e delle eventuali attestazioni / errori

Questo modulo consente di collegare l'operatività del gestionale a un obbligo normativo reale.

---

## Report e controllo

La reportistica è pensata soprattutto per la parte amministrativa e direzionale.

Permette di analizzare i dati per:

- periodo
- renter
- veicolo
- metodo di pagamento
- tipologia voce economica
- commissionabilità
- singolo noleggio

L'obiettivo non è solo vedere quanto è stato incassato, ma anche distinguere:

- totale noleggiato
- quota commissionabile
- fee admin
- distribuzione economica per renter e veicolo

Accanto ai report è presente anche una sezione di **audit** per la tracciabilità delle operazioni.

---

## Stack tecnico

- **Laravel 12**
- **PHP 8**
- **Blade** per le viste server-side
- **Livewire** per le interfacce dinamiche
- **Spatie Laravel Permission** per ruoli e permessi
- **Spatie Media Library** per la gestione dei media
- **PDF generation** per i documenti contrattuali e checklist

---

## Installazione

### Requisiti

- PHP 8.x
- Composer
- Node.js / npm
- Database MySQL o compatibile

### Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configura poi il file `.env` con:

- credenziali database
- filesystem
- mail
- eventuali parametri dedicati a CARGOS

Esegui quindi:

```bash
php artisan migrate --seed
npm run build
php artisan serve
```

Per lo sviluppo frontend puoi usare:

```bash
npm run dev
```

---

## Struttura funzionale

Le aree principali del progetto sono:

- **Dashboard**
- **Clienti**
- **Veicoli**
- **Sedi**
- **Noleggi**
- **Assegnazioni**
- **Blocchi**
- **Documenti veicolo**
- **Media e contratti**
- **Report**
- **Audit**

La navigazione effettiva dipende dai permessi del ruolo autenticato.

---

## Note progettuali

### Multi-tenant applicativo

Il progetto segue una logica multi-tenant basata su organizzazioni e policy, non su istanze separate del database per tenant.

### Snapshot contrattuali

Gli importi e alcune regole vengono congelati al momento corretto per evitare inconsistenze future dovute a cambi di listino.

### Storico e tracciabilità

Assegnazioni, stati, checklist, danni, documenti e audit sono pensati per rendere ricostruibile il ciclo di vita del veicolo e del noleggio.

### Compliance come parte del dominio

L'integrazione con CARGOS non è un'aggiunta marginale: è parte integrante del disegno del sistema, perché influenza sia i dati richiesti sia il flusso documentale.

---

## Stato del progetto

Il repository rappresenta un gestionale verticale costruito su esigenze operative reali del settore sub-noleggio.

Le aree chiave già coperte dal dominio sono:

- gestione tenant admin / renter
- assegnazioni flotta
- noleggi e contratti
- checklist e danni
- media documentali
- reportistica economica
- integrazione CARGOS

---

## Licenza

Definire la licenza del progetto in base alla modalità di distribuzione del repository.
