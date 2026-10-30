# VOYradar24 — Flight Test FT-01 (MFO / VaBase / ACARS)

**Stato:** predisposto, non eseguito. **Ambiente:** produzione, solo osservazione. Nessuna modifica al Booking MFO.

## Caso di prova
- Activity ID: `5280`
- Leg ID: `42670`
- Slot ID: `4`
- Pilot ID: `14240`; callsign: `VOY1968`
- Ultimo BID osservato: `692711` (**riconfermare prima del test**)
- SimBrief Dispatch: presente nell'ultimo BID osservato, ma **non requisito** del test.

## Fonti di verità
1. **Database MFO:** `voy_mfo_slots` (slot, BID, actmp, PIREP, stati).
2. **VaBase BID:** `GET v1/bids` con sessione del pilota (elenco dei BID attivi; la sua scomparsa NON prova completamento).
3. **VaBase ACARS:** `GET v1/map/acars` (sessioni attive, posizione, stage, identificatore `actmpId`).
4. **VaBase storico/PIREP:** `GET v1/activity/history/5280/14240` (verificare risposta, semantica e tempi).
5. **UI:** `slot.php?id=5280`, `live_flights.php`, e in futuro VOYradar24.

## Procedura
| Fase | Azione | Evidenze da acquisire |
|---|---|---|
| T0 | Prima di avviare VaBase ACARS | Query SQL slot; BID; risposta ACARS; schermate slot e live_flights |
| T1 | VaBase aperto / Pre-Flight | Stessi dati; distinguere applicazione aperta da ACARS attivo |
| T2 | Start Flight / Boarding | Timestamp UTC; ACARS grezzo; presenza `actmpId`; stage; associazione BID |
| T3 | Taxi | ACARS, coordinate, velocità, stage; tabellone |
| T4 | Takeoff / Climb | ACARS, heading, quota, velocità, DTG; marker mappa |
| T5 | Cruise | ACARS ripetuto; confronto tempo residuo vs ETA orario |
| T6 | Descent / Landing | Stage, DTG, coordinate; eventuali anomalie |
| T7 | END FLIGHT | Snapshot immediato ACARS, BID, DB e storico/PIREP |
| T8 | +2, +5, +15 minuti | Ripetere letture per eventuale elaborazione asincrona |
| T9 | Riapertura `slot.php` | Verificare se riconciliazione ha modificato lo slot; non assumere COMPLETED |

**Raccolta:** per ogni fase annotare UTC, ora locale, codice HTTP, JSON (con dati personali minimizzati), screenshot e note. Non modificare record a mano durante il test.

## Query SQL read-only
```sql
SELECT slot_id, activity_id, activity_leg_id, callsign, pilot_id,
       bid_id, actmp_id, pirep_id, status_mfo, status_flight,
       booked_at, completed_at, bonus_xp, bonus_awarded_at
FROM voy_mfo_slots
WHERE slot_id = 4 OR activity_leg_id = 42670;
```

## Criteri di verifica
- Il volo MFO è visibile prima di ACARS e occupa **una sola riga** anche quando ACARS diventa LIVE.
- ACARS restituisce identificativi sufficienti a correlare la sessione allo slot (verificare effettivamente i campi).
- Il volo non MFO, se presente, è trattato alla pari nel LIVE ma **non** richiede record MFO.
- Nessun completamento MFO viene dedotto solo dalla scomparsa del BID o di ACARS.
- Il PIREP è confermato dalla fonte corretta prima di considerare la chiusura definitiva.
- Il valore `eta` di `live_flights.php` è un **tempo residuo stimato (ETE)**, non un orario di arrivo.

## Scheda snapshot (duplicare per fase)
```text
FASE:
UTC:
ORA LOCALE:
BID (HTTP + ID):
ACARS (HTTP + actmpId, pilotId, flightNumber, depIcao, arrIcao, statStage):
MFO (slot_id, bid_id, actmp_id, pirep_id, status_mfo, status_flight):
HISTORY/PIREP (HTTP + identificativi):
UI (slot / live_flights):
OSSERVAZIONI:
```

## Questioni aperte
- Il campo ACARS che collega univocamente il volo al BID non è ancora dimostrato.
- Il comportamento dopo END FLIGHT e i tempi di pubblicazione del PIREP sono da misurare.
- Il codice di `site_widgets/map_acars.php` deve essere acquisito e analizzato separatamente.


## Nota successiva — baseline lifecycle v1.0.0

Questo è il piano originario FT-01 e non descrive lo stato corrente del lifecycle MFO. Il completamento è stato successivamente validato tramite lo storico delle Activity VaBase, su apertura della CREW FLIGHT BOARD; né Disembarking né la scomparsa di BID/ACARS sono trigger produttivi. Vedere [BASELINE_LIFECYCLE_V1.md](BASELINE_LIFECYCLE_V1.md).
