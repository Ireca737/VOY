# MFO — Monthly Flight Operations

Modulo VOY basato sulle Activity VaBase, con prenotazioni esclusive per singola tratta.

## Baseline collaudata

Tag: `mfo-lifecycle-v1.0.0`. Commit: `a51babc`. Branch: `feat/mfo-lifecycle`.

Il ciclo operativo è `AVAILABLE → BOOKED → COMPLETED`; `OBSOLETE` esclude la tratta dalla bacheca. Una prenotazione confermata resta BOOKED anche quando il BID VaBase scompare.

La CREW FLIGHT BOARD in `slot.php` esegue la riconciliazione su richiesta con `MFO/lib/activity_lifecycle.php`, interrogando `GET v1/activity/history/{activityId}/{pilotId}` e verificando `activityLegHistories[].activityLegId`. Il completamento conserva il pilota e registra il timestamp UTC. Nessun cron o polling ACARS è richiesto per il lifecycle.

`MFO/lib/acars_lifecycle.php` e `mfo_ft02.php` sono componenti sperimentali FT-02, non il processo produttivo.

## Struttura

- `slots.php`: elenco programmi.
- `slot.php`: CREW FLIGHT BOARD, BOOKING STATUS e BOOK NOW.
- `slot_leg.php`: prenotazione esclusiva e BID VaBase.
- `MFO/admin/`: gestione programmi e tratte.
- `MFO/database/`: riferimenti di schema.
- `MFO/docs/`: architettura, API e collaudi.
- `MFO/public/`: componenti accessori, incluso settlement sperimentale.

Le tabelle `voy_mfo_programs` e `voy_mfo_slots` sono operative nell'ambiente collaudato. La baseline è stata verificata con voli reali VY1102, VY1103 e VY1104.

## Attività separate

Restano da completare la compartimentazione rispetto a tutti i percorsi Activity nativi, i test sistematici di concorrenza e sicurezza e la gestione XP/Cash. La regola bonus prevista è `floor(distanza VaBase get_distance() in NM / 10)`, con accredito idempotente da validare. Il codice settlement/cron presente non è autorizzato alla produzione dalla baseline lifecycle.

Vedere [baseline e prove di volo](docs/BASELINE_LIFECYCLE_V1.md), [architettura](docs/ARCHITETTURA.md) e [API](docs/API_VABASE_ACARS.md).

Il tag v1.0.0 resta immutato; le revisioni documentali sono successive e non comportano deploy.
