# Riferimenti API VaBase: BID, ACARS e storico MFO

Documentazione ricavata dai file PHP del sito VOY `booktest.php` (test fornito il 24/10/2026) e `live_flights.php` (pagina web di monitoraggio ACARS). Non è una specifica ufficiale delle API VaBase. Gli endpoint e i campi qui descritti sono quelli osservati nei sorgenti e, ove indicato, nei risultati del test.

## Accesso API

I due file inizializzano `Proxy\\Api\\Api` tramite `Api::__constructStatic()` e chiamano `Api::sendSync(...)` oppure `Api::sendAsync(...)`. Il test `booktest.php` richiede una sessione autenticata con `pilotid = 14240`: è una protezione specifica del test, non una regola generale dell'API.

## Endpoint verificati nel test booktest.php

| Metodo | Endpoint | Risultato osservato |
| --- | --- | --- |
| GET | `v1/activity/booking-status/5280/14240/42658` | HTTP 200, `hasBookedFlight=true`, `canBookActivity=false` |
| GET | `v1/activity/booking-status/5280/14240/42655` | HTTP 200, stessi valori |
| GET | `v1/activity/booking-status/5280/14240/99999` | HTTP 200, stessi valori anche per Leg inesistente |
| GET | `v1/bids` | HTTP 200, elenco JSON dei BID attivi visibili alla sessione |
| GET | `v1/activity/history/5280/14240` | HTTP 204, nessun corpo JSON |

**Avvertenza:** `booking-status` non identifica in modo affidabile una specifica Leg sulla base del test effettuato: ha risposto nello stesso modo anche con `99999`. Non usare `hasBookedFlight` come prova dell'esistenza del BID della Leg interrogata. Il significato completo di `canBookActivity` richiede ulteriori verifiche.

### Esempio BID reale del 24/10/2026

Il GET `v1/bids` ha restituito un record con:
- `id = 692671` (identificativo BID VaBase)
- `activityLegId = 42670`
- `pilotId = 14240`
- `bidType = activity`
- `flightNumber = VY1101`, `departureIcao = LIPZ`, `arrivalIcao = LIRA`
- `status = 0` (significato numerico non accertato)
- `sbDispatched = true` e `sbOfpId` valorizzato (dati SimBrief/dispatch; non provano l'avvio ACARS)

Nel database MFO lo slot `slot_id=4`, `activity_leg_id=42670`, `pilot_id=14240` risultava `BOOKED` e `bid_id=NULL` prima dell'aggiunta della sincronizzazione del BID. Il collegamento è dunque `voy_mfo_slots.bid_id = v1/bids[].id`, selezionando congiuntamente `pilotId`, `activityLegId` e `bidType=activity`.

## Monitoraggio ACARS web: live_flights.php

Il file `live_flights.php` interroga:

```php
$res = Api::sendAsync('GET', 'v1/map/acars', null);
```

Con HTTP 200 decodifica un array di voli in corso. I campi **effettivamente utilizzati dalla pagina** sono:
- Identità: `pilotId`, `name`, `callsign`, `flightNumber`, `actmpId`
- Tratta: `depIcao`, `arrIcao`, `aircraftTypeId`
- Posizione/distanza: `startLong`, `startLat`, `presLong`, `presLat`, `arrLong`, `arrLat`
- Telemetria: `statSpeed`, `statHdg`, `statStage`

La pagina calcola lato PHP distanza totale, distanza percorsa, distanza residua (DTG), ETA e percentuale completata. Usa `actmpId` per selezionare il marker della mappa, tramite `site_widgets/map_acars.php`.

**Limiti:** il sorgente non dimostra che `v1/map/acars` restituisca `activityLegId` o `bid_id`; non permette quindi, da solo, di associare con certezza un volo live allo slot MFO. `statStage` è mostrato senza una tabella di valori documentata. La presenza in mappa non equivale a PIREP completato; l'assenza non prova un abort o un completamento.

## Implicazioni per MFO

1. `status_mfo` gestisce l'assegnazione esclusiva (`AVAILABLE`, `BOOKED`, `COMPLETED`; `OBSOLETE` escluso dalla Flight Board).
2. Il BOOK confermato da VaBase è distinto dal successivo recupero di `bid_id`; la sincronizzazione introdotta in `slot_leg.php` dopo HTTP 200 usa `GET v1/bids` e aggiorna il record MFO solo con una corrispondenza univoca.
3. `v1/map/acars` è un possibile riferimento per informazioni **live**, non per confermare il completamento.
4. Per passare a `COMPLETED` occorre individuare e verificare una fonte affidabile di PIREP/esito finale, ancora da documentare.
5. Non modificare il flusso BOOK collaudato per introdurre monitoraggio ACARS; mantenere separati stato di prenotazione, telemetria live ed esito finale.

## Provenienza

- `booktest.php`, file di test fornito dall'utente; chiamate a `v1/bids`, `v1/activity/booking-status/...`, `v1/activity/history/...`.
- `live_flights.php`, file web fornito dall'utente; chiamata a `v1/map/acars` e presentazione dei campi ACARS.
- Risultati del test BID e dump della tabella `voy_mfo_slots` condivisi il 24/10/2026.

Documento di riferimento per sviluppo e test, non sostituisce la verifica empirica degli endpoint e delle transizioni di stato.
