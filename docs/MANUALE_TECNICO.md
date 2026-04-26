# Manuale tecnico interno — Gestionale SubNoleggio

> Prima bozza interna orientata a chi dovrà mettere mano al codice.
> 
> Questo documento non sostituisce il codice sorgente: serve come mappa tecnica iniziale per capire **come è organizzato il progetto**, **quali sono i flussi principali**, **dove stanno i punti sensibili** e **come intervenire senza rompere isolamento tenant, disponibilità veicoli e flussi documentali**.

---

## 1. Scopo del progetto

Il progetto implementa un gestionale verticale per il **sub-noleggio auto** con modello **owner → renter → cliente finale**.

Nel dominio applicativo:

- l'**admin** possiede il parco veicoli
- il **renter** riceve i veicoli tramite assegnazioni temporali
- il renter crea e gestisce i **noleggi** verso i clienti finali
- il sistema governa anche media, checklist, danni, contratti, report e compliance

Il punto chiave da tenere sempre presente è questo:

**il renter non deve mai essere trattato come proprietario del veicolo**, ma come soggetto autorizzato a usarlo in un certo perimetro e per un certo intervallo.

---

## 2. Stack e convenzioni generali

### Stack tecnico

- Laravel 12
- PHP 8
- Blade
- Livewire
- Jetstream / Sanctum / email verification
- Spatie Permission
- Spatie Media Library
- PDF generation per contratti/checklist

### Convenzioni generali del progetto

- il dominio è centrato su **Organization** con `type = admin|renter`
- l'autorizzazione è mista:
  - middleware di permesso su route
  - policy sui model
  - scoping per organizzazione
- il progetto usa **SoftDeletes** su più entità sensibili
- i media non sono allegati “liberi”, ma distribuiti su collection con significato preciso
- i flussi noleggio non sono riducibili al solo `status`: contano anche date effettive, checklist, danni, addebiti e stato documentale

---

## 3. Mappa di accesso applicativa

### Middleware base

Le route principali sono protette da:

- `auth:sanctum`
- sessione Jetstream
- `verified`
- in versioni più recenti del file rotte anche `ensure.organization.active`

Questo significa che l'utente non deve essere considerato valido solo perché autenticato: in presenza del middleware dedicato, deve appartenere anche a un'organizzazione attiva.

### Entry point principali

Le aree applicative sono organizzate in blocchi funzionali:

- Dashboard
- Clienti
- Veicoli
- Sedi
- Noleggi
- Media / Contratti / Checklist
- Assegnazioni
- Blocchi
- Documenti veicolo
- Organizations (admin)
- Report
- Audit

### Regola pratica

Quando si aggiunge una nuova feature, bisogna decidere **prima** in quale livello ricade:

1. sola visibilità UI
2. route protetta da permesso
3. policy su model
4. scoping tenant nei dati
5. combinazione di tutti i livelli precedenti

Se si applica solo il middleware di permesso ma non lo scope tenant, si crea una falla logica.

---

## 4. Ruoli, permessi e gate

## 4.1 Ruoli base

I ruoli previsti dal seeder sono:

- `admin`
- `renter`

L'assegnazione avviene in base al `type` dell'organizzazione collegata all'utente.

## 4.2 Permessi

I permessi seguono naming coerente `resource.action`.

Aree principali:

- vehicles
- vehicle_documents
- vehicle_pricing
- vehicle_damages
- assignments
- blocks
- customers
- rentals
- locations
- rental_checklists
- rental_damages
- media
- audit
- reports

### Differenza importante

Non tutti i permessi hanno lo stesso livello di impatto:

- alcuni governano CRUD classico
- altri governano **azioni di workflow** (`rentals.checkout`, `rentals.checkin`, `rentals.close`, ecc.)
- altri ancora sono **granulari su sottocomportamenti** (`vehicles.update_mileage`, `vehicles.manage_maintenance`, `vehicles.assign_location`)

Questa distinzione va mantenuta anche nelle evoluzioni future. Un errore comune sarebbe accorpare logiche diverse dietro un unico `vehicles.update`.

## 4.3 Gate amministrativi

Le parti di amministrazione renter/organizations usano il gate `manage.renters`.

Questo gate non va trattato come semplice alias del ruolo admin: è il punto con cui la UI e le route separano la parte “tenant admin / backoffice” dal resto.

---

## 5. Multi-tenant: regola fondamentale

Il progetto è multi-tenant **applicativo**, non database-per-tenant.

### Conseguenza pratica

Un renter non deve mai poter:

- leggere clienti di altri renter
- leggere noleggi di altri renter
- modificare sedi di altri renter
- vedere veicoli non assegnati al suo tenant
- manipolare blocchi o media fuori perimetro

### Come viene implementato

La separazione è distribuita su più livelli:

- `organization_id` su entità renter-owned (es. customers, rentals, locations)
- `renter_org_id` sulle assegnazioni
- policy che confrontano il model con `user->organization_id`
- helper policy che verificano se un veicolo è assegnato “ora” al renter

### Implicazione per chi sviluppa

Quando si aggiunge una query, bisogna sempre chiedersi:

- questo dato è `owned by organization`?
- oppure è `visible because assigned now`?
- oppure è globale ma filtrabile?

Se questa distinzione non è chiara, si finisce per rompere la sicurezza o la logica operativa.

---

## 6. Modello dati: entità core

## 6.1 Organization

Rappresenta admin o renter.

Campi concettualmente importanti:

- dati anagrafici
- `type`
- `is_active`
- dati licenza noleggio
- credenziali CARGOS cifrate (`cargos_password`, `cargos_puk`)
- possibile mapping CARGOS tramite `police_place_code`

Relazioni principali:

- users
- locations
- vehiclesOwned
- customers
- vehicleAssignments
- vehicleBlocks
- rentals

### Nota

Qui convivono informazioni di business e configurazione. È un model ad alta responsabilità: modifiche qui hanno impatto ampio.

## 6.2 Vehicle

Entità proprietaria della flotta admin.

Campi importanti:

- `admin_organization_id`
- plate / vin
- make / model / year / color
- fuel_type / transmission / seats / segment
- mileage_current
- default_pickup_location_id
- is_active

Relazioni principali:

- owner admin
- default pickup location
- documents
- states
- assignments
- rentals
- blocks
- mileage logs
- pricelists

### Punto delicato

La visibilità del veicolo non dipende solo dal fatto che esista. Per il renter dipende dall'assegnazione valida.

## 6.3 VehicleAssignment

È l'entità che rende il progetto davvero “sub-noleggio”.

Campi importanti:

- `vehicle_id`
- `renter_org_id`
- `start_at`
- `end_at`
- `status` (`scheduled`, `active`, `ended`, `revoked`)
- km iniziali/finali

### Regola concettuale

Il renter può operare solo sui veicoli che rientrano nel perimetro delle sue assegnazioni valide secondo la logica prevista dal codice.

## 6.4 VehicleState

Tiene traccia dello stato del mezzo nel tempo.

Stati previsti a schema:

- `available`
- `assigned`
- `rented`
- `maintenance`
- `blocked`

Questa tabella non sostituisce le altre: serve come **storico di stato**, non come unico motore della disponibilità.

## 6.5 Customer

Cliente finale del renter.

Campi sensibili:

- dati anagrafici
- nascita
- residenza
- cittadinanza
- documento identità
- patente
- codici collegati a CARGOS

È tenant-owned tramite `organization_id`.

## 6.6 Location

Sedi di admin o renter.

Serve sia per:

- gestione anagrafica sedi
- pickup/return
- mapping eventuale ai luoghi ufficiali CARGOS

## 6.7 Rental

Entità centrale del flusso operativo.

Campi chiave:

- `organization_id`
- `vehicle_id`
- `assignment_id`
- `customer_id`
- `planned_pickup_at`, `planned_return_at`
- `actual_pickup_at`, `actual_return_at`
- `pickup_location_id`, `return_location_id`
- `status`
- `mileage_out`, `mileage_in`
- `fuel_out_percent`, `fuel_in_percent`
- `amount`
- `admin_fee_percent`, `admin_fee_amount`
- `final_amount_override`
- `second_driver_id`
- `number_id`

Relazioni principali:

- organization
- vehicle
- assignment
- customer
- secondDriver
- pickup/return locations
- checklists
- damages
- charges
- contractSnapshot

### Punto critico

`Rental` non è solo contratto o solo prenotazione: è un aggregato applicativo che tiene insieme operatività, documenti, stato e parte economica.

## 6.8 RentalChecklist

Modello importante perché aggiunge il concetto di **lock documentale**.

Campi chiave:

- `type` (`pickup` / `return`)
- `mileage`
- `fuel_percent`
- `cleanliness`
- `checklist_json`
- `locked_at`
- `locked_by_user_id`
- `signed_media_id`
- `last_pdf_media_id`
- `replaces_checklist_id`

### Significato funzionale

Una checklist non è solo un form salvato: può diventare documento “chiuso”, sostitutivo, firmato e non più modificabile.

## 6.9 RentalCharge

Rappresenta le righe economiche del noleggio.

Tipi dichiarati nel model:

- base
- distance_overage
- damage
- surcharge
- fine
- other
- acconto
- base+distance_overage

Campi chiave:

- `kind`
- `is_commissionable`
- `amount`
- `payment_recorded`
- `payment_recorded_at`
- `payment_method`

### Punto importante

La reportistica economica legge qui. Non bisogna aggirare questo layer scrivendo importi “liberi” solo sul noleggio se poi quei dati devono comparire nei report.

## 6.10 RentalDamage

Danno rilevato nel flusso di noleggio.

Campi principali:

- `phase`
- `area`
- `severity`
- `description`
- `estimated_cost`
- `photos_count`

È separato dal danno veicolo persistente, ma può collegarsi alla parte flotta.

---

## 7. Lifecycle del noleggio

Dalle route emerge un workflow esplicito:

- create/store
- update
- payment
- distance overage
- checkout
- inuse
- checkin
- close
- cancel
- noshow

### Stati rilevanti

Nel progetto ricorrono stati come:

- `draft`
- `reserved`
- `in_use`
- `checked_in`
- `closed`
- `cancelled`
- `no_show`

In alcune parti esiste o è esistito anche il riferimento a `checked_out` / alias correlati.

### Regola pratica per sviluppatori

Quando si aggiunge una nuova transizione, non basta cambiare il campo `status`.

Va verificato l'impatto su:

- date effettive
- km e carburante
- checklist
- media
- addebiti
- report
- stato del veicolo
- permessi disponibili

---

## 8. Disponibilità del veicolo: non banalizzarla

Uno dei punti più facili da rompere è la disponibilità dei mezzi.

### La disponibilità reale dipende da più sorgenti

- assegnazioni
- blocchi
- noleggi sovrapposti
- stato del veicolo
- eventuale manutenzione

### Errore da evitare

Usare solo `vehicle_states` o solo `rentals` per stabilire disponibilità.

Nel progetto la disponibilità è una **composizione di vincoli**, non una colonna singola.

### Regola di manutenzione codice

Ogni volta che tocchi:

- planner
- assegnazioni
- block logic
- form di creazione noleggio
- picker veicolo

devi rieseguire mentalmente il caso:

> questo renter, in questo intervallo, su questo veicolo, è davvero autorizzato e senza overlap?

---

## 9. Media, firme, PDF e allegati

## 9.1 Tipi di media gestiti

Le route mostrano una distinzione forte tra media diversi:

- contratto generato
- contratto firmato
- firma cliente
- firma locatore
- firma organizzazione
- checklist firmata
- foto checklist
- foto danno
- documenti noleggio
- documenti cliente
- documenti veicolo
- foto veicolo / danno veicolo

### Implicazione

Non bisogna riutilizzare una collection “generica” per velocità se il media ha valore funzionale o probatorio diverso.

## 9.2 Checklist e lock

Una checklist firmata ha dinamiche particolari:

- può avere un PDF associato
- può essere bloccata (`locked_at`)
- può sostituirne una precedente
- ha un significato documentale, non solo UI

Chi modifica questa zona deve stare molto attento a non permettere edit retroattivi di materiale che dovrebbe essere considerato congelato.

---

## 10. Policies: logica reale di sicurezza

Le policy implementano una parte fondamentale della sicurezza applicativa.

### Pattern ricorrente

Per molte entità vale questa regola:

- admin → vede tutto se ha permesso
- renter → vede solo se il model appartiene alla sua organizzazione oppure se è legato a un veicolo assegnato al suo tenant

### Esempi

- `CustomerPolicy`: confronto diretto su `organization_id`
- `RentalPolicy`: confronto diretto su `organization_id`
- `LocationPolicy`: confronto diretto su `organization_id`
- `VehiclePolicy`: logica speciale basata su assegnazione “ora”
- `VehicleBlockPolicy`: visibilità mista tra ownership del blocco e assegnazione del veicolo

### Errore da evitare

Creare query corrette in controller ma dimenticare la policy, o viceversa.

Le due cose devono restare coerenti. Una UI filtrata non sostituisce un controllo di autorizzazione lato server.

---

## 11. Contratto e snapshot

Il model `Rental` espone `contractSnapshot()`.

Questo segnala una scelta importante di architettura:

- i dati contrattuali principali vengono congelati in un record dedicato
- il contratto non dovrebbe dipendere in modo fragile da dati mutabili successivi

### Regola pratica

Se si cambia il modo in cui vengono calcolati prezzi, km inclusi o fee, bisogna verificare:

- cosa resta sul `Rental`
- cosa finisce nello snapshot
- cosa viene letto dal PDF
- cosa viene letto dai report

---

## 12. Reportistica

Le route indicano due livelli:

- `/reports` come area report principale
- `/admin/report-presets/*` per preset salvati ed esecuzione lato admin

### Significato tecnico

La reportistica non è solo una vista: esiste una distinzione tra

- configurazione report
- esecuzione report
- eventuale persistenza preset

### Punto delicato

Le metriche economiche devono restare coerenti con `rental_charges`, commissionabilità e fee admin.

Quando si aggiungono campi economici nuovi, bisogna sempre decidere se:

- sono solo decorativi
- entrano in report
- entrano in commissione
- entrano nello snapshot contrattuale

---

## 13. Audit

L'area `/audit` indica la presenza di una sezione dedicata alla tracciabilità.

Inoltre alcuni model, come `RentalChecklist`, usano esplicitamente activity log.

### Implicazione

Quando si introduce una feature amministrativa o documentale importante, conviene chiedersi se debba lasciare traccia audit o activity log. In questo progetto la ricostruibilità storica è parte del valore applicativo.

---

## 14. CARGOS

Il progetto contiene modelli e campi collegati a CARGOS:

- `CargosLuogo`
- `CargosDocumentType`
- codici CARGOS su customer/location/organization
- credenziali cifrate su organization

### Obiettivo del modulo

Preparare e inviare i dati contrattuali verso il servizio esterno secondo tracciato e codifiche previste.

### Regola pratica

Chi tocca i campi anagrafici cliente/location/organization deve verificare se la modifica impatta anche:

- mapping verso codici ufficiali
- validazione del payload
- generazione record
- invio o retry verso il servizio esterno

La parte CARGOS non va trattata come integrazione accessoria: impatta la forma del dato interno.

---

## 15. Dove intervenire quando arriva una modifica

## 15.1 Modifica puramente UI

Controllare in ordine:

- Blade / Livewire
- route name usata
- policy/gate richiamata
- eventuale DTO / request validation

## 15.2 Modifica a un flusso noleggio

Controllare almeno:

- `RentalController`
- model `Rental`
- checklists / damages / charges
- PDF/contratto
- media associati
- report se tocca importi o commissioni

## 15.3 Modifica a disponibilità veicolo

Controllare almeno:

- assegnazioni
- blocchi
- overlap rentals
- policy veicolo
- eventuali componenti planner / picker veicolo

## 15.4 Modifica a sicurezza tenant

Controllare almeno:

- policy
- middleware di route
- query scope
- eventuali endpoint JSON o open media

---

## 16. Errori concettuali da evitare

### 1. Confondere ownership con visibilità

Un renter può vedere un veicolo perché assegnato, non perché ne sia owner.

### 2. Usare solo lo status per dedurre il flusso

Spesso servono anche date effettive, checklist e addebiti.

### 3. Saltare il layer `rental_charges`

Se un importo deve comparire in report o commissioni, quasi sempre deve transitare da lì.

### 4. Trattare i media come allegati generici

Molti media hanno significato legale o operativo preciso.

### 5. Applicare solo filtro UI

Il controllo reale deve stare anche lato server, via policy e query corrette.

### 6. Rompere il lock documentale

Checklist firmate o chiuse non vanno riaperte senza una logica esplicita e tracciabile.

---

## 17. Sequenza consigliata per chi entra nel progetto

1. leggere `routes/web.php`
2. leggere `RolesAndPermissionsSeeder`
3. leggere le policy principali
4. leggere i model core (`Organization`, `Vehicle`, `VehicleAssignment`, `Rental`, `RentalChecklist`, `RentalCharge`)
5. entrare poi nei controller dei flussi su cui si deve lavorare
6. verificare infine Blade/Livewire collegati

Questa sequenza riduce il rischio di intervenire sulla UI senza aver capito regole di dominio e sicurezza.

---

## 18. Stato di questo manuale

Questa è la **prima base tecnica interna**.

Copre:

- architettura applicativa ad alto livello
- ruoli e permessi
- modello dati core
- logiche multi-tenant
- flussi principali di noleggio
- punti sensibili su media, checklist, report e CARGOS

### Da approfondire nelle iterazioni successive

- controller per controller
- componenti Livewire e viste chiave
- planner disponibilità
- dettaglio generazione PDF contratto
- dettaglio logica reportistica
- dettaglio pipeline CARGOS
- elenco tabelle DB completo con finalità campo-per-campo

---

## 19. Regola finale

Quando tocchi il codice, chiediti sempre queste tre cose:

1. questa modifica rompe l'isolamento tenant?
2. questa modifica altera la disponibilità reale del veicolo?
3. questa modifica cambia il valore documentale/economico del noleggio?

Se almeno una risposta è “forse”, non è una modifica locale: è una modifica di dominio.

---

## 20. Approfondimento tecnico — flusso noleggi

Questa sezione entra nel dettaglio del flusso noleggi così com’è implementato oggi tra route, controller, Livewire e model.

### 20.1 Dove vive davvero il flusso

A livello di entry point applicativo, l’area noleggi espone:

- index
- create
- show
- update
- azioni di stato (`checkout`, `inuse`, `checkin`, `close`, `cancel`, `noshow`)
- azioni economiche (`payment`, `distance-overage`)
- checklist create
- generazione contratto

Ma il punto importante è questo:

- `RentalController` espone il resource controller principale
- `store()`, `update()` e `destroy()` nel controller risultano ancora placeholder / TODO
- la **creazione reale della bozza** è implementata nel componente Livewire `App\Livewire\Rentals\CreateWizard`
- la vista elenco monta `App\Livewire\Rentals\RentalsBoard`

Quindi chi deve toccare il flusso non deve fermarsi al controller resource: una parte importante del comportamento effettivo vive nei componenti Livewire.

### 20.2 Creazione noleggio: source of truth attuale

La creazione parte dalla pagina `resources/views/pages/rentals/create.blade.php`, che monta `livewire:rentals.create-wizard`.

Il wizard lavora in più step ma, a livello di dominio, i passaggi veri sono questi.

#### Step 1 — Bozza noleggio

Il wizard valida e salva una bozza con:

- veicolo
- sedi pickup/return
- date pianificate
- note
- eventuale `final_amount_override`
- coperture / franchigie

Durante `saveDraft()`:

- viene cercata l’assegnazione attiva del veicolo (`VehicleAssignment::active()`)
- il noleggio viene sempre creato o aggiornato come `draft`
- alla prima creazione viene allocato `number_id` tramite `RentalNumberAllocator`
- viene denormalizzato `amount` se il pricing è risolvibile con `VehiclePricingService`
- viene creato o confermato il record `coverage()` 1:1

#### Regole forti già presenti in step 1

Prima di passare avanti, il wizard applica `assertVehicleAvailability()`.

Questa regola:

- lavora sulle **date pianificate**, non sulle date effettive
- cerca overlap sullo stesso `vehicle_id`
- ignora noleggi in `cancelled` e `no_show`
- usa la regola classica di overlap: `startA < endB && endA > startB`

Questo significa che il primo presidio di disponibilità in creazione non è il planner e non è il controller, ma il wizard stesso.

#### Popolamento veicoli disponibili nel wizard

`loadOptions()` applica una regola diversa a seconda del ruolo:

- **admin**: vede veicoli attivi **non assegnati** a nessun renter tramite `whereDoesntHave('assignments', fn($q) => $q->active())`
- **renter**: vede solo veicoli con assegnazione attiva alla propria organizzazione

Questa regola è importante perché la creazione noleggio non dipende solo dai permessi utente, ma anche dal perimetro flotta effettivamente affidato.

#### Step 2 — Cliente

Il wizard permette di:

- selezionare un cliente esistente
- creare o aggiornare il cliente inline

Qui la logica è più ricca di quanto sembri, perché una parte del form cliente è già allineata a CARGOS:

- `first_name`, `last_name`
- `birth_place_code`
- `police_place_code`
- `citizenship_place_code`
- `identity_document_type_code`
- `identity_document_place_code`
- `driver_license_place_code`

Il salvataggio cliente fa anche mapping e derivazioni:

- costruzione di `name` da `first_name + last_name`
- mapping del tipo documento CARGOS verso `doc_id_type` interno
- derivazione campi testuali di residenza da `police_place_code`
- salvataggio di cittadinanza e codici CARGOS nel model cliente

#### Regole forti già presenti in step 2

Prima di procedere allo step successivo, il wizard impone:

- cliente associato obbligatorio
- nessun overlap per lo stesso cliente nel periodo (`assertCustomerNoOverlap()`)
- patente valida almeno fino alla data/ora di riconsegna (`assertDriverLicenseValidThroughReturn()`)

Quest’ultima è una regola di dominio concreta, non solo documentale: blocca il passaggio allo step successivo se la patente scade prima della fine del noleggio.

#### Step 3 — Contratto

La generazione contratto passa da `GenerateRentalContract`.

Dopo la generazione:

- se lo stato del rental è `draft`, viene portato a `reserved`
- il contratto corrente viene riletto dalla collection `contract` cercando il media con `custom_properties.current === true`

Quindi, nello stato attuale del progetto, la vera transizione “bozza pronta all’uso” è:

`draft -> reserved` dopo generazione contratto.

### 20.3 Controller noleggi: transizioni operative

Il `RentalController` attuale è soprattutto il punto di ingresso delle transizioni operative e delle azioni economiche.

#### Checkout

`checkout()` applica queste verifiche:

- esistenza checklist `pickup`
- presenza del contratto nella collection `contract`
- presenza firma checklist pickup
- presenza contratto firmato nella collection `signatures`

Se tutto è valido:

- lo stato viene impostato direttamente a `in_use`
- `actual_pickup_at` viene valorizzato se mancante

Questo è un punto importante: nel codice commenti e route parlano ancora di `checkout`, ma il flusso effettivo **non usa più `checked_out` come stato principale**, perché il checkout porta direttamente a `in_use`.

#### In use

`inuse()` esiste ancora come endpoint separato e accetta transizioni da:

- `draft`
- `checked_out`
- `reserved`

Anche qui, se valido:

- lo stato diventa `in_use`
- `actual_pickup_at` viene impostato se assente

Questa coesistenza indica una compatibilità con flussi precedenti o alternativi. Non va rimossa senza prima allineare board, UI e permessi.

#### Check-in

`checkin()` può partire solo da:

- `in_use`
- `checked_out`

Verifiche applicate:

- esistenza checklist `return`
- per ogni danno con `phase in ['return', 'during']`, presenza di almeno una foto nella collection `photos`

Se tutto è valido:

- stato `checked_in`
- `actual_return_at` valorizzato se mancante

Questa è una regola importante da ricordare: **il danno senza foto blocca il check-in**.

#### Close

`close()` delega la validazione sostanziale a `CloseRentalGuard`.

Regole del guard:

1. il rental deve essere in stato `checked_in`
2. deve esistere la checklist `return`
3. se richieste, le firme devono essere presenti
4. se richiesto, deve esistere il pagamento base
5. se ci sono km extra, deve esistere il pagamento `distance_overage`
6. se `closed_at` è già valorizzato e fuori grace period, lo snapshot è considerato bloccato (`snapshot_locked`)

Durante la chiusura:

- se l’organizzazione del rental è di tipo `renter`, viene calcolata e salvata la fee admin con `AdminFeeResolver`
- lo stato passa a `closed`
- vengono valorizzati `closed_at` e `closed_by`

Esiste anche un override applicativo del blocco `snapshot_locked`, ma solo per chi ha `rentals.close.override`.

#### Cancel / No-show

`cancel()` e `noshow()` sono già convergenti dal punto di vista del dato finale:

- entrambi sono consentiti solo da `draft` o `reserved`
- entrambi salvano `status = cancelled`

Questo significa che `no_show` è ormai più una nozione di flusso/permesso che uno stato finale primario persistito.

### 20.4 Modello economico del noleggio

Il noleggio ha due livelli economici distinti.

#### Livello 1 — Totale denormalizzato sul rental

Nel wizard, `amount` viene valorizzato come importo calcolato dal pricing quando possibile.

Questo valore è utile, ma **non sostituisce** le righe economiche reali.

#### Livello 2 — Righe economiche in `rental_charges`

Le righe economiche vere vivono in `RentalCharge`.

Tipi principali:

- `base`
- `distance_overage`
- `damage`
- `surcharge`
- `fine`
- `other`
- `acconto`
- `base+distance_overage`

`storePayment()` in controller registra una riga come pagamento già contabilizzato.

Regole importanti:

- il `kind` è univoco per rental tra i record non soft-deleted
- quindi, per come è scritto oggi il codice, **può esistere una sola riga per tipo** su uno stesso noleggio
- il default di `is_commissionable` non è libero: viene impostato automaticamente per `base`, `distance_overage`, `base+distance_overage`, `acconto` solo se il rental ha `assignment_id`

Questo ha una conseguenza pratica importante:

se un domani serviranno più righe dello stesso tipo sullo stesso noleggio, la logica attuale di unicità andrà ripensata prima a livello applicativo e poi eventualmente a livello report.

#### Flag derivati usati dalla UI

Il model `Rental` espone accessor usati direttamente da UI e controller:

- `has_base_payment`
- `base_payment_at`
- `distance_overage_km`
- `needs_distance_overage_payment`
- `has_distance_overage_payment`
- `can_checkout`
- `can_close`

Questi accessor non sono cosmetici: sono parte della logica operativa del flusso.

#### Calcolo km extra

`distance_overage_km`:

- preferisce i km letti dalle checklist (`pickupChecklist()->mileage`, `returnChecklist()->mileage`)
- se mancano, fa fallback su `mileage_out` / `mileage_in` del rental
- ricostruisce i km inclusi dallo snapshot contrattuale (`pricing_snapshot`)
- se possibile usa `km_daily_limit * days`
- in fallback legge campi legacy tipo `included_km_total`

Questa parte è delicata: il kilometraggio extra non è un semplice delta tra campi del rental, ma una risoluzione composta tra checklist, rental e snapshot.

### 20.5 Checklist e danni: regole reali

#### Checklist

Il model `RentalChecklist` è più di un contenitore dati.

Gestisce:

- due tipi canonici (`pickup`, `return`)
- lock persistente (`locked_at`, `locked_by_user_id`, `locked_reason`)
- collegamento al media firmato (`signed_media_id`)
- ultimo PDF generato (`last_pdf_media_id`)
- sostituzione checklist precedente (`replaces_checklist_id`)
- activity log su create/update/delete

Collection media rilevanti:

- `photos`
- `checklist_pdfs`
- `checklist_pickup_signed`
- `checklist_return_signed`
- `signatures` (legacy/generica ancora mantenuta)

Questo spiega perché il codice di chiusura e di `inuse()` controlla più collection possibili per la firma pickup: il flusso è stato evoluto senza eliminare del tutto la compatibilità precedente.

#### Danni

`RentalDamage` modella il danno emerso durante il noleggio.

Campi utili al dominio:

- `phase` (`pickup`, `return`, `during`)
- `area`
- `severity`
- `description`
- `estimated_cost`
- `photos_count`

Collection media:

- `photos`

Regola già usata nel controller:

- in `checkin()`, ogni danno di fase `return` o `during` deve avere almeno una foto

Quindi, quando si modifica UI o API danni, bisogna sempre preservare la sincronizzazione tra:

- creazione danno
- upload foto
- conteggio / presenza media
- logica di blocco del check-in

### 20.6 Policy e scoping specifici dei noleggi

La `RentalPolicy` segue il pattern tenant classico:

- per renter: accesso solo se `rental.organization_id === user.organization_id`
- per admin: basta il permesso

Per le azioni di workflow (`checkout`, `checkin`, `close`, `cancel`, `noshow`) la policy attuale controlla soprattutto il permesso, con meno enfasi sul confronto tenant rispetto ai metodi `view/update/delete`.

Questo non è automaticamente sbagliato, ma significa che il corretto scoping dipende anche dal fatto che il rental sia già stato recuperato dentro un perimetro corretto.

### 20.7 Board e planner: attenzione alla divergenza dal dominio

L’elenco noleggi (`resources/views/pages/rentals/index.blade.php`) monta `RentalsBoard`.

In quel componente convivono:

- tabella
- kanban
- planner mese/settimana/giorno
- ricerca libera su id cliente e targa
- logiche di disponibilità visiva e overbooking

Punti importanti del board:

- il planner usa come date “effettive” `actual_*` con fallback a `planned_*`
- il planner è quindi più aderente al movimento reale del noleggio rispetto alla sola pianificazione
- la creazione rapida dal planner apre il wizard già precompilato con veicolo e data/ora

#### Punto di attenzione tecnico

`RentalsBoard::restrictToViewer()` usa una logica di scoping che non è espressa nello stesso modo della `RentalPolicy`.

Nel componente compaiono infatti riferimenti a:

- `renter_id`
- `sub_renter_id`
- fallback su `organization_id = user.id`

mentre il model `Rental` e la `RentalPolicy` ragionano soprattutto con `organization_id`.

Questo non significa automaticamente che il board sia sbagliato, ma significa che questa parte va trattata come **zona sensibile**: prima di toccarla bisogna verificare la coerenza reale con schema DB e policy attuali.

### 20.8 Debito tecnico e ambiguità da conoscere prima di toccare il flusso

#### 1. Resource controller non completo

`store()`, `update()` e `destroy()` nel `RentalController` risultano ancora TODO.

Quindi il flusso vero non è centralizzato in un singolo punto applicativo.

#### 2. `checked_out` è ancora vivo come compatibilità, ma non è più il centro del workflow

Nel codice esistono ancora riferimenti a `checked_out` in:

- controller
- planner / board
- transizioni permesse

ma `checkout()` porta direttamente a `in_use`.

Chi semplifica questa parte deve farlo in modo coerente su tutto il progetto, non solo in un metodo.

#### 3. `no_show` è ancora nominato ma viene normalizzato a `cancelled`

Il wizard ignora ancora `no_show` negli overlap e la route dedicata esiste, ma il dato finale salvato è `cancelled`.

Questa è una legacy compatibility da tenere a mente.

#### 4. `has_distance_overage_payment` merita attenzione

L’accessor nel model costruisce la query combinando `where()` e `orWhere()`.

Prima di toccare quella logica o riusarla in report/guard, conviene verificarne bene il grouping SQL reale, perché è il classico punto in cui una query apparentemente innocua può restituire veri positivi troppo larghi.

### 20.9 Checklist operativa per sviluppatori sulla sezione noleggi

Quando devi modificare qualcosa nell’area noleggi, questa è la sequenza minima consigliata:

1. controlla la route effettivamente usata
2. verifica se il comportamento vive nel controller o in Livewire
3. controlla `RentalPolicy`
4. controlla gli accessor del model `Rental`
5. controlla se la modifica impatta `RentalChecklist`, `RentalDamage` o `RentalCharge`
6. controlla se la modifica cambia il contratto o lo snapshot
7. controlla se la modifica tocca report e fee admin

Se salti uno di questi livelli, è molto facile introdurre inconsistenze silenziose.

---

## 21. Approfondimento tecnico — contratto, media, firme e checklist firmate

Questa sezione descrive la pipeline documentale del progetto. Qui il principio da tenere a mente è semplice:

**un media non è solo un file allegato**. In diversi punti del codice il caricamento di un file cambia lo stato logico del dominio, congela dati economici, blocca modifiche future o innesca invii email.

### 21.1 Architettura del flusso documentale

I punti applicativi principali sono:

- `RentalContractController@generate` per la generazione del PDF contratto
- `GenerateRentalContract` come service reale che costruisce HTML, snapshot e media finale
- `RentalMediaController` per upload, open e delete dei media noleggio/checklist/danni/firme
- `MediaObserver` per l’invio mail automatico dei documenti firmati
- `RentalDamageMediaObserver` per mantenere sincronizzato `photos_count`

La logica documentale quindi è distribuita su:

- controller di orchestrazione
- service di generazione PDF
- model con collection media dedicate
- observer che reagiscono alla creazione dei media

### 21.2 Contratto: generazione reale e snapshot congelato

`RentalContractController@generate()` è un thin controller: autorizza e delega tutto a `GenerateRentalContract`.

Il service `GenerateRentalContract` è la vera source of truth del contratto. Il suo comportamento reale è più ricco di quanto sembri.

#### Relazioni lette dal service

Prima di generare il PDF, il service carica:

- `vehicle`
- `customer`
- `organization` del rental
- sedi pickup/return
- eventuale `secondDriver`
- eventuale `coverage()`
- eventuale `contractSnapshot()` già esistente

Quindi il PDF non è un rendering “cieco” del solo `Rental`, ma un aggregato documentale che usa più relazioni.

#### Snapshot pricing: freeze-once

Il punto architetturale più importante è questo:

- se esiste già uno snapshot in `RentalContractSnapshot`, il service lo riusa
- se non esiste, lo costruisce dal listino attivo e lo salva una sola volta
- da quel momento il contratto deve restare ancorato allo snapshot, non ai listini correnti

Il model `RentalContractSnapshot` contiene:

- `rental_id`
- `pricing_snapshot` JSON
- `created_by_user_id`

Questo significa che il contratto è progettato per **non cambiare retroattivamente** se cambia un listino o una tariffa dopo la prima generazione.

#### Contenuto reale dello snapshot

Nel JSON dello snapshot vengono congelati, tra gli altri:

- `pricelist_id`
- `currency`
- `days`
- `tariff_total_cents`
- `km_daily_limit`
- `extra_km_cents`
- `deposit_cents`
- `second_driver_daily_cents`
- `tariff_override_cents`

Il punto delicato è `tariff_override_cents`:

- è l’unico campo pensato per restare mutabile
- deriva da `rentals.final_amount_override`
- viene sincronizzato nello snapshot anche dopo la prima creazione tramite `syncTariffOverrideOnly()`

Quindi il principio non è “snapshot totalmente immutabile”, ma piuttosto:

- **freeze-once per tutti i valori strutturali di pricing**
- **eccezione controllata per l’override tariffario**

#### Totali usati nel PDF

Il service distingue chiaramente tra:

- tariffa listino congelata
- eventuale override della tariffa
- seconda guida congelata
- totale finale effettivo

Questo è importante: chi modifica il PDF o il pricing deve evitare di calcolare direttamente dal rental valori che il service sta già ricostruendo dallo snapshot.

### 21.3 Contratto non firmato vs contratto firmato

Il service salva il PDF in collection diverse a seconda del contesto.

#### Collection usate dal Rental

Sul model `Rental` sono registrate le collection:

- `contract`
- `signatures`
- `documents`
- `signature_customer`
- `signature_lessor`

#### Regola di salvataggio del PDF

Il service sceglie la collection di destinazione così:

- `contract` per il contratto base / non firmato
- `signatures` per il contratto firmato

Ogni nuova generazione versiona solo la collection di destinazione:

- i media precedenti nella stessa collection vengono marcati con `current = false`
- il nuovo media viene salvato con `current = true`
- viene salvato anche `generated_with_signatures`
- viene allegato `pricing_snapshot` come custom property sul media

Questa scelta è importante perché consente:

- storico versioni nella stessa collection
- risoluzione del documento corrente
- fallback legacy sul media stesso se manca la tabella snapshot

#### Risoluzione del contratto corrente

`GenerateRentalContract::resolveCurrentContractMedia()` preferisce:

1. collection `signatures`
2. collection `contract`

E dentro ogni collection preferisce il media con `current = true`.

Quindi, nel dominio applicativo, un contratto firmato corrente “vince” sempre su quello non firmato.

### 21.4 Firme: cliente, noleggiante, organizzazione

Il progetto gestisce più livelli di firma e non sono intercambiabili.

#### Firma cliente

Sul `Rental` esiste la collection `signature_customer`.

È una firma grafica immagine usata nel rendering del contratto PDF. Non coincide automaticamente con il PDF firmato completo: sono due cose diverse.

#### Firma noleggiante su Rental

Sul `Rental` esiste anche `signature_lessor`.

Questa è una firma override specifica del noleggio.

#### Firma aziendale su Organization

Sul model `Organization` esiste la collection single-file `signature_company`.

Questa rappresenta la firma aziendale di default del noleggiante.

#### Precedenza reale della firma noleggiante

Il service `GenerateRentalContract` applica una precedenza esplicita:

1. override sul `Rental` (`signature_lessor`)
2. firma dell’`Organization` che funge da noleggiante effettivo
3. fallback alla firma aziendale dell’organizzazione renter collegata al rental

Quindi la firma del noleggiante non è una semplice “immagine letta dal rental”, ma una risoluzione a cascata.

#### Sync Rental -> Organization

Quando `RentalMediaController@storeLessorSignature()` salva una firma noleggiante sul rental, chiama anche `syncLessorSignatureToOrganization()`.

Questa funzione:

- copia il media su `Organization.signature_company`
- mantiene una sola firma aziendale corrente

Quindi il caricamento firma sul rental ha anche un effetto di propagazione sulla configurazione di default dell’organizzazione.

### 21.5 Checklist firmate e lock persistente

Questa è una delle parti più sensibili del dominio documentale.

#### Collection firmate per checklist

Ogni checklist usa collection firmate diverse in base al tipo:

- `checklist_pickup_signed`
- `checklist_return_signed`

Resta anche la collection `signatures` per compatibilità/legacy.

#### Upload del firmato

`RentalMediaController@storeChecklistSigned()`:

- autorizza tramite `uploadSignature`
- rifiuta l’operazione se la checklist è già locked
- carica PDF/immagine nella collection firmata corretta
- imposta campi di lock persistente sul model

Campi aggiornati durante il lock:

- `signed_by_customer = true`
- `signed_by_operator = true`
- `signature_media_uuid`
- `locked_at`
- `locked_by_user_id`
- `locked_reason = customer_signed_pdf`
- `signed_media_id`

Quindi il caricamento del firmato non è solo un upload: è una **transizione documentale irreversibile a livello di dominio**, salvo logiche esplicite di sostituzione future.

#### Policy checklist e read-only effettivo

`RentalChecklistPolicy` applica il lock a più livelli:

- `update()` vietato se locked
- `uploadPhoto()` vietato se locked
- `uploadSignature()` vietato se locked
- `deleteMedia()` vietato se locked
- `generatePdf()` vietato se locked

Questo significa che la sola presenza di `locked_at` rende di fatto la checklist in sola lettura applicativa.

### 21.6 Danni e media collegati alla checklist

I media dei danni non sono indipendenti dalla checklist.

#### Regola di policy

`RentalDamagePolicy` verifica la checklist della fase corrispondente (`pickup`, `return`, `during`).

Se la checklist di quella fase è locked:

- update danno vietato
- upload foto vietato
- delete media vietato

#### Regola nel controller media

`RentalMediaController` applica anche un ulteriore blocco operativo:

- se il danno appartiene a una checklist locked, `storeDamagePhoto()` risponde `423 Locked`
- lo stesso succede in delete media per i media dei danni

Questo doppio presidio è voluto: policy + controller.

#### Observer `photos_count`

`RentalDamageMediaObserver` osserva il modello Media di Spatie e, quando un media `photos` viene creato o cancellato per un `RentalDamage`, ricalcola `photos_count` direttamente da DB.

Quindi `photos_count` è un campo denormalizzato mantenuto in modo reattivo, non affidato alla UI.

### 21.7 Upload e delete media: regole reali

Il `RentalMediaController` è il punto più importante per capire cosa si può o non si può più fare una volta che un documento esiste.

#### Upload contratto base

`storeContract()`:

- salva in `Rental.contract`
- autorizza con `contractGenerate` + `uploadMedia`
- non applica lock di dominio

#### Upload contratto firmato

`storeSignedContract()`:

- salva una volta sul `Rental` in `signatures`
- duplica il media sulla checklist pickup in `signatures`
- quindi il contratto firmato è presente sia sul rental sia sulla checklist

Questa duplicazione spiega perché alcune parti del codice controllano la firma sia sul rental sia sulla checklist pickup.

#### Upload documenti generici rental

`storeRentalDocument()` consente upload in collection:

- `documents`
- `id_card`
- `driver_license`
- `privacy`
- `other`

Qui c’è un dettaglio tecnico importante: il model `Rental` dichiara esplicitamente `documents`, ma il controller consente anche collection ulteriori. Prima di usare stabilmente queste collection in UI o conversioni, conviene verificare se il progetto le tratta ormai come convenzione consolidata o come estensione compatibile ma non ancora omogeneamente formalizzata.

#### Open media

`open()`:

- autorizza in base al model padre del media
- per `Rental` e `RentalChecklist` usa `view`
- per `Organization` oggi non applica un controllo specifico ulteriore
- restituisce inline il file se presente su disco
- fallback su redirect URL pubblica se il disk è `public`

Questo metodo è importante perché è il punto di accesso ai documenti in visualizzazione e va trattato come endpoint sensibile.

#### Delete media

`destroy()` applica regole forti:

- media di `RentalChecklist` locked: non eliminabili
- media firmato che ha causato il lock (`signed_media_id`): non eliminabile
- media di `RentalDamage` collegati a checklist locked: non eliminabili
- media di `Rental.signatures`: non eliminabili

Quindi il contratto firmato e i media probatori collegati a checklist locked vengono trattati come prova documentale da non alterare.

### 21.8 Observer mail: il documento firmato genera effetti esterni

`MediaObserver` ascolta la creazione dei media nelle collection firmate “normalizzate”:

- `checklist_pickup_signed`
- `checklist_return_signed`
- `signatures`

Quando intercetta uno di questi media:

- risale al `Rental`
- risale al `Customer`
- decide il subject in base alla collection
- invia l’email a fine request
- traccia lo stato su `media_email_deliveries` per il cliente
- opzionalmente invia notifica best effort anche all’admin

#### Perché a fine request

L’observer usa `app()->terminating(...)` perché al momento del solo evento `created` il file potrebbe non essere ancora fisicamente scritto su storage.

Questa è una scelta tecnica importante: evita race condition tra record media creato e file ancora non disponibile.

#### Dedup e rigenerazioni

L’observer distingue tra:

- primo invio
- reinvio richiesto
- rigenerazione dello stesso documento logico con nuovo media

Quindi non sta inviando mail “alla cieca a ogni upload”: ha già una logica di dedup e tracciamento per cliente.

### 21.9 Interazione fra PDF, media e stato noleggio

Le dipendenze reali da ricordare sono queste:

- il contratto generato porta il rental da `draft` a `reserved`
- il checkout richiede contratto presente + firme richieste
- il check-in richiede checklist return + danni con foto
- la chiusura può richiedere firme, pagamenti base e overage
- il media firmato può innescare email automatiche
- la checklist firmata blocca ulteriori modifiche e cancellazioni

Quindi PDF, media e stato noleggio non sono layer indipendenti: si condizionano a vicenda.

### 21.10 Debito tecnico e punti di attenzione su questa sezione

#### 1. Convivenza di collection legacy e nuove

Nel codice convivono:

- `checklist_pickup_signed` / `checklist_return_signed`
- `signatures` su checklist
- `signatures` su rental
- naming legacy normalizzati in observer

Prima di ripulire queste collection bisogna mappare bene tutti i punti in cui vengono lette, non solo quelli in cui vengono scritte.

#### 2. Differenza tra firma grafica e contratto firmato

Non vanno confusi:

- `signature_customer` / `signature_lessor` come immagini usate nel PDF
- PDF/immagine firmata caricata in `signatures` o `checklist_*_signed`

Sono due piani diversi del dominio documentale.

#### 3. Collection consentite dal controller ma non tutte formalizzate nello stesso modo sul model

Il controller documenti rental ammette collection aggiuntive oltre a `documents`.

Prima di estendere conversioni, UI o reporting media, conviene verificare se questa flessibilità è voluta in modo definitivo.

#### 4. Effetto collaterale della firma noleggiante

Caricare una firma noleggiante sul rental aggiorna anche la firma aziendale di default dell’organizzazione.

È utile, ma è anche un effetto collaterale forte: chi modifica questo pezzo deve essere consapevole che non sta toccando solo il rental corrente.

### 21.11 Checklist operativa per sviluppatori sulla pipeline documentale

Quando devi modificare contratto, media o firme, questa è la sequenza minima consigliata:

1. controlla se il cambiamento tocca `GenerateRentalContract`
2. controlla se modifica lo snapshot o solo il rendering PDF
3. controlla la collection media coinvolta
4. controlla se esiste un observer collegato
5. controlla se la checklist può andare in lock
6. controlla le policy di checklist/damage
7. controlla se il documento viene usato come prerequisito di stato (`checkout`, `close`)

Se cambi solo il punto di upload o solo la view PDF, senza fare questa verifica, rischi di rompere la pipeline documentale senza accorgertene.
