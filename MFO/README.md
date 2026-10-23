# MFO — Monthly Flight Operations

Modulo VOY per programmi di volo **one shot**, basato sulle Activity native VaBase, ma completamente compartimentato da Tours & Events.

## Principi concordati
- Ogni programma MFO corrisponde a una Activity VaBase.
- Il campo API `Type` è impostato automaticamente a `Event`, senza scelta nella UI MFO.
- `LegsInOrder = false`: le tappe non richiedono completamento in sequenza.
- Ogni Activity Leg è uno slot esclusivo: una prenotazione alla volta, consumato definitivamente solo dopo completamento convalidato.
- Sono ammessi più MFO attivi contemporaneamente, anche con date sovrapposte.
- Inizio, fine, attivazione, titolo, descrizione e banner restano governati dall'Activity VaBase.
- BID, ACARS e PIREP continuano a usare VaBase, senza duplicarne il motore.
- Vetrina pubblica e amministrazione MFO separate dalle normali pagine Tours & Events.
- Bonus XP MFO calcolato e accreditato dal modulo VOY con logica idempotente, da implementare e verificare.

## Struttura
- `admin/`: interfaccia amministrativa dedicata, destinazione prevista sul sito `/admin/mfo/`.
- `public/`: vetrina e prenotazioni per i piloti, destinazione prevista `/mfo/`.
- `database/`: schema, migrazioni e note DB (NON eseguite automaticamente).
- `docs/`: architettura, API, verifiche e criteri di accettazione.

## Stato
Impostazione iniziale del progetto, **non distribuibile in produzione**. Nessun file del core VaBase è modificato in questa repository.

### Persistenza
La tabella `voy_mfo_slots` è già stata aggiornata sul database VOY: include `activity_id`, `activity_leg_id` univoco, riferimenti BID/ACARS/PIREP, stati e campi bonus XP. Resta il vecchio `mfo_month`, da rimuovere dopo una migrazione verificata. Manca il registro `voy_mfo_programs`, definito in `database/001_programs.sql` e da creare separatamente.

### Blocchi prima della pubblicazione
1. Verificare e integrare gli endpoint VaBase per Activity, Legs, BID e completamenti.
2. Assicurare che Tours & Events non mostri programmi MFO.
3. Prevenire prenotazioni dirette tramite `activity_leg.php` o altri percorsi che aggirerebbero lo slot esclusivo.
4. Implementare gestione concorrente della prenotazione, recupero errori API e riconciliazione.
5. Validare permessi, protezione CSRF, escaping, bonus XP e PIREP confermato.
