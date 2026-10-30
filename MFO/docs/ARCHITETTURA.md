# Architettura e vincoli MFO — baseline lifecycle v1.0.0

## Riuso VaBase

Le Activity VaBase gestiscono programma e tratte (`Type=Event` tecnico, `LegsInOrder=false`); VaBase continua a gestire BID, ACARS, PIREP e storico. MFO registra i programmi in `voy_mfo_programs` e le prenotazioni esclusive in `voy_mfo_slots` senza modifiche al core VaBase.

## Lifecycle produttivo

- `AVAILABLE`: tratta prenotabile mediante `slot_leg.php` con assegnazione atomica e BID VaBase.
- `BOOKED`: prenotazione confermata e persistente. La cancellazione o scomparsa del BID **non** riporta lo slot ad AVAILABLE.
- `COMPLETED`: all'apertura di `slot.php`, `MFO/lib/activity_lifecycle.php` interroga `GET v1/activity/history/{activityId}/{pilotId}` e verifica `activityLegHistories[].activityLegId`. L'UPDATE condizionato sullo stesso programma, pilota e Leg registra `completed_at=UTC_TIMESTAMP()` mantenendo l'assegnazione.
- `OBSOLETE`: slot escluso dalla bacheca.

La riconciliazione è idempotente e avviene su richiesta, non tramite cron o polling. In caso di storico non disponibile lo slot rimane BOOKED fino a una visita successiva. Il timestamp `completed_at` è quello della riconciliazione, non necessariamente del volo.

## ACARS sperimentale

`MFO/lib/acars_lifecycle.php` e `mfo_ft02.php` appartengono ai test FT-02 e **non** costituiscono il lifecycle produttivo. `Disembarking` e la scomparsa di ACARS/BID non provano il completamento.

## Integrità e attività aperte

Il vincolo UNIQUE su `activity_leg_id` non sostituisce le transazioni e i controlli sulle righe aggiornate. Restano da validare sistematicamente concorrenza, recupero da errori DB/API, autorizzazioni, CSRF e tutti i percorsi alternativi delle pagine Activity native. La compartimentazione completa rispetto a Tours & Events non è ancora garantita.

Bonus XP e Cash sono sviluppi separati: il bonus previsto usa la distanza VaBase `get_distance()` con `floor(NM/10)` e accredito idempotente da collaudare. Non abilitare il cron di settlement sulla base della sola presenza dei file.

Riferimento: [BASELINE_LIFECYCLE_V1.md](BASELINE_LIFECYCLE_V1.md).
