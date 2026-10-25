# VOYradar24 — FT-01: diario delle evidenze

**Test:** primo ciclo operativo MFO → VaBase BID → ACARS → END FLIGHT  
**Data:** 25 ottobre 2026  
**Stato:** IN CORSO — documento incrementale  
**Branch:** `feat/mfo-pilot`  
**Scopo:** conservare gli snapshot grezzi e distinguere osservazioni certe, ipotesi e questioni aperte. Nessuna modifica operativa al Booking.

## Scenario

- Pilota: ID `14240`, callsign `VOY1968`
- Activity ID: `5280`; activity leg ID: `42670`; slot MFO: `4` (da riconfermare nel DB per il BID corrente)
- Volo: `VY1101`, `LIPZ → LIRA`
- BID osservato durante il test: `692776`
- Fonti: `GET v1/bids`, `GET v1/map/acars`; campionamento della pagina diagnostica ogni 5 secondi.
- Monitor web: `booktest01.php` generale (ACARS di tutti i piloti) e `booktest02.php` individuale (filtrato su `pilotId=14240`; sorgente GitHub `booktest_ft01.php`). Entrambi richiedono sessione autorizzata.

## Evidenze e cronologia

### T1 — Pre-flight / From Dispatch, 2026-10-25 07:57:57 UTC

Snapshot condiviso in conversazione (non ancora archiviato come file JSON autonomo).

- `v1/bids`: HTTP 200, BID `692776`, `status=0`, `activityLegId=42670`, `pilotId=14240`, `sbDispatched=true`.
- `v1/map/acars`: HTTP 200, `data=[]`.
- VaBase aperto e prenotazione acquisita tramite From Dispatch; Start Flight non ancora premuto.
- **Conclusione circoscritta:** nessuna sessione visibile nell'endpoint ACARS al momento dello snapshot. Non significa assenza assoluta di altri output VaBase.

### T2 — Start Flight / Boarding, 2026-10-25 08:15:32 UTC

**Evidenza integrale:** [FT01_T2_START_FLIGHT_20261025T081532Z.json](./FT01_T2_START_FLIGHT_20261025T081532Z.json)

- `v1/bids`: HTTP 200, BID `692776` ancora presente, `status=1`.
- `v1/map/acars`: HTTP 200, un volo con `pilotId=14240`, `callsign=VOY1968`, `flightNumber=VY1101`, `depIcao=LIPZ`, `arrIcao=LIRA`.
- `statStage=Boarding`, `actmpId=1097445`, `statSpeed=0`, `statAltitude=0`, `statHdg=0`.
- Coordinate iniziali e posizione corrente coincidono.
- **Conclusione circoscritta:** nello snapshot successivo a Start Flight il BID permane e lo stato passa da 0 a 1, mentre la sessione compare in ACARS. Il campionamento non dimostra simultaneità esatta.

### T3 — Pushback, arresto e taxi (25 ottobre 2026)

Snapshot forniti durante il collaudo: `pushback.json`, `pretaxi.json`, `taxing.json`. I file originali restano allegati alla conversazione; non sono ancora archiviati autonomamente nel repository.

| Ora UTC | Osservazione | BID status | statStage | statSpeed | statAltitude | statHdg | actmpId |
|---|---|---:|---|---:|---:|---:|---:|
| 08:31:32 | Pushback in movimento | 1 | Taxiing | 2 | 10 | 224 | 1097445 |
| 08:41:34 | Pushback concluso, aeromobile fermo | 1 | Boarding | 0 | 10 | 139 | 1097445 |
| 08:45:34 | Taxi in movimento | 1 | Taxiing | 6 | 10 | 139 | 1097445 |

- Il BID `692776` resta presente, con `status=1`, in tutti e tre i campioni.
- Il `pilotId=14240`, il volo `VY1101` e la sessione `actmpId=1097445` restano invariati.
- Le coordinate `presLat/presLong` cambiano fra i campioni.
- **Sequenza osservata:** `Boarding → Taxiing → Boarding → Taxiing`.
- **Interpretazione, non regola dimostrata:** il passaggio a `Boarding` sembra associato all'arresto, e il ritorno a `Taxiing` alla ripresa del movimento. Soglia e altre condizioni interne VaBase non sono note.
- Lo snapshot `pushback.json` include anche un secondo volo, `BOX3S390` (`pilotId=81865`, `actmpId=1097448`), perché la prima versione del monitor non filtrava l'endpoint ACARS. Non è un secondo volo del pilota FT-01.
- La versione individuale del monitor applica il filtro `pilotId=14240` lato PHP e confronta soltanto i dati operativi, evitando eventi fittizi dovuti a `duration_ms`.
- **Implicazione VOYradar24:** una regressione di `statStage` non implica la creazione di un nuovo volo; lo stato LIVE dipende dall'esistenza della sessione ACARS, non dalla velocità istantanea.

### T4 — Allineamento e decollo (25 ottobre 2026)

Tre snapshot originali allegati alla conversazione, acquisiti tramite monitor individuale `booktest02.php`:

| Ora UTC | File originale | statStage | statSpeed | statAltitude | statHdg |
|---|---|---|---:|---:|---:|
| 08:57:44 | `voy-ft01-snapshot-1792918669401.json` | Boarding | 0 | 10 | 47 |
| 08:58:04 | `voy-ft01-snapshot-1792918686617.json` | Boarding | 0 | 10 | 47 |
| 08:58:29 | `voy-ft01-snapshot-1792918712593.json` | Climbing | 233 | 820 | 46 |

- In tutti i campioni: `bid_id=692776`, `bids.status=1`, `actmpId=1097445`, `pilotId=14240`, volo `VY1101`.
- Fra le 08:58:04 e le 08:58:29 UTC compare il passaggio da `Boarding` a `Climbing`; non è possibile escludere stati intermedi non acquisiti.
- Lo stato `Boarding` permane nei due campioni con velocità 0 anche dopo l'allineamento in pista, secondo l'osservazione operativa del pilota.
- La posizione cambia nel campione `Climbing`; i valori di velocità e quota sono quelli restituiti dall'API, senza attribuire unità non dichiarate nel payload.
- **Conseguenza:** non derivare la fase reale di volo dal solo `statStage` senza conoscere la logica VaBase.
- **Archiviazione:** i tre JSON sono conservati come allegati originali alla conversazione; il README registra i loro nomi, ma non li presenta falsamente come file già caricati nel repository.

### T5 — Livellamento intermedio a circa 10.000 ft, 2026-10-25 09:04:54 UTC

Snapshot originale allegato alla conversazione: `stable1000ft.json` (nome fornito; quota dichiarata dal pilota: 10.000 piedi).

- `v1/bids`: HTTP 200, BID `692776`, `status=1`, `activityLegId=42670`.
- `v1/map/acars`: HTTP 200, `pilotId=14240`, `actmpId=1097445`, `statStage=Climbing`, `statSpeed=237`, `statAltitude=9960`, `statHdg=204`.
- Posizione: `presLat=45.2617229386`, `presLong=12.3993220956`.
- **Osservazione:** il pilota riferisce volo stabilizzato a circa 10.000 ft, ma l'API riporta ancora `Climbing`.
- **Limite:** un singolo snapshot non stabilisce la logica di transizione a `Cruising`; quota pianificata nel BID `29000`.
- **Archiviazione:** JSON originale allegato alla conversazione, non ancora copiato nel repository.

### T5-bis — Livellamento confermato / In-flight, 2026-10-25 09:07:24 UTC

Snapshot originale allegato alla conversazione: `stable10000_2.json`.

- ACARS: `statStage=In-flight`, `statAltitude=9970`, `statSpeed=235`, `statHdg=204`.
- Il campione precedente, alle 09:04:54 UTC, mostrava `Climbing` a quota `9960` e velocità `237`.
- `actmpId=1097445` e `bid_id=692776` con `status=1` invariati.
- **Conclusione:** VaBase può indicare `In-flight` a circa 10.000 ft, ben prima della quota pianificata di 29.000 ft.
- **Ipotesi da verificare:** lo stato verticale potrebbe dipendere dalla variazione di quota/rateo verticale e da una media o latenza; i campioni non dimostrano lettura diretta del beccheggio né consentono di ricavare soglie o algoritmo.
- **Implicazione:** per VOYradar24 evitare di trattare `Climbing`, `Descending` e `In-flight` come transizioni irreversibili.

### T5-ter — Ripresa salita verso FL290, 2026-10-25 09:11:34 UTC

Snapshot originale `climb.json` allegato alla conversazione.

- ACARS: `statStage=Climbing`, `statAltitude=10190`, `statSpeed=274`, `statHdg=195`; `presLat=44.8237759573`, `presLong=12.1926090547`.
- BID `692776` ancora `status=1`; `actmpId=1097445` invariato.
- Sequenza verificata sul medesimo volo: `Climbing` (09:04:54, quota 9960) → `In-flight` (09:07:24, quota 9970) → `Climbing` (09:11:34, quota 10190).
- **Conferma operativa:** `statStage` non è una progressione monotona; può tornare a `Climbing` quando il pilota riprende la salita. Non è ancora noto l'algoritmo VaBase.

## Interpretazione per VOYradar24

1. **BOOKED ≠ LIVE.** Un MFO prenotato può essere presente sul tabellone senza una sessione ACARS.
2. **LIVE osservabile:** la presenza di una sessione in `v1/map/acars` è l'indicatore operativo da usare, previo corretto abbinamento al volo.
3. **Identificativi distinti:** `bid_id=692776` e `actmp_id=1097445`; ACARS non espone direttamente `bid_id` o `activityLegId` nello snapshot.
4. **Non dedurre COMPLETED:** né dalla sparizione di ACARS né da quella del BID, senza evidenza di chiusura/PIREP.
5. **Tutti i voli ACARS hanno pari dignità nel LIVE;** MFO aggiunge la programmazione anticipata e il vincolo one leg–one pilot.

## Prossime acquisizioni (da compilare)

| Fase | Evento | Timestamp UTC | Evidenza | Risultato |
|---|---|---|---|---|
| T3 | Pushback / arresto / Taxi | 08:31:32 / 08:41:34 / 08:45:34 | `pushback.json`, `pretaxi.json`, `taxing.json` (allegati conversazione) | `Taxiing → Boarding → Taxiing`; BID e actmp invariati |
| T4 | Allineamento / Takeoff / Climb | 08:57:44 / 08:58:04 / 08:58:29 | Tre snapshot `voy-ft01-snapshot-*.json` (allegati conversazione) | `Boarding → Climbing`, BID e actmp invariati |
| T5 | Livellamento intermedio ~10.000 ft | 09:04:54 / 09:07:24 | `stable1000ft.json`, `stable10000_2.json` (allegati conversazione) | `Climbing → In-flight`, BID 1, actmp invariato |
| T6 | Descent / Landing | — | — | In attesa |
| T7 | END FLIGHT | — | — | In attesa |
| T8 | +2 / +5 / +15 min | — | — | In attesa |
| T9 | Stato MFO e PIREP | — | — | In attesa |

## Regole di archiviazione

- Ogni nuovo evento ha un JSON grezzo, con nome `FT01_<FASE>_<EVENTO>_<YYYYMMDDTHHMMSSZ>.json`.
- Aggiungere qui un paragrafo con ora UTC, dati osservati, differenze dalla fase precedente, conclusioni supportate e punti aperti.
- Conservare il JSON **senza eliminare o trasformare campi**; evitare di pubblicare dati sensibili ulteriori.
- Separare fatti osservati da ipotesi e comportamenti ancora da verificare.
- Non alterare la baseline di `booktest.php` o la logica del Booking durante il collaudo.

## Questioni aperte

- Correlazione certa sessione ACARS ↔ BID ↔ slot MFO, senza basarsi soltanto sul callsign.
- Evoluzione del `bids.status` e di `statStage` durante le fasi del volo.
- Comparsa del PIREP e semantica di END FLIGHT.
- Effetti della riconciliazione `slot.php` quando il BID non è più attivo.
- Analisi di `site_widgets/map_acars.php` quando disponibile.
