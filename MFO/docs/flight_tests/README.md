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
- Monitor web: `booktest01.php` (distribuito sul server dall'operatore; sorgente GitHub `booktest_ft01.php`).

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

## Interpretazione per VOYradar24

1. **BOOKED ≠ LIVE.** Un MFO prenotato può essere presente sul tabellone senza una sessione ACARS.
2. **LIVE osservabile:** la presenza di una sessione in `v1/map/acars` è l'indicatore operativo da usare, previo corretto abbinamento al volo.
3. **Identificativi distinti:** `bid_id=692776` e `actmp_id=1097445`; ACARS non espone direttamente `bid_id` o `activityLegId` nello snapshot.
4. **Non dedurre COMPLETED:** né dalla sparizione di ACARS né da quella del BID, senza evidenza di chiusura/PIREP.
5. **Tutti i voli ACARS hanno pari dignità nel LIVE;** MFO aggiunge la programmazione anticipata e il vincolo one leg–one pilot.

## Prossime acquisizioni (da compilare)

| Fase | Evento | Timestamp UTC | Evidenza | Risultato |
|---|---|---|---|---|
| T3 | Taxi | — | — | In attesa |
| T4 | Takeoff / Climb | — | — | In attesa |
| T5 | Cruise | — | — | In attesa |
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
