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
