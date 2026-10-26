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
- Chiusura slot MFO consolidata su ACARS `Disembarking`, con protezione one-shot e correlazione BID/attempt.
- Bonus XP MFO calcolato e accreditato dal modulo VOY con logica idempotente, da implementare e verificare su branch dedicato.

## Struttura
- `admin/`: interfaccia amministrativa dedicata, destinazione prevista sul sito `/admin/mfo/`.
- `public/`: vetrina e prenotazioni per i piloti, destinazione prevista `/mfo/`.
- `database/`: schema, migrazioni e note DB (NON eseguite automaticamente).
- `docs/`: architettura, API, verifiche e criteri di accettazione.

## Stato
Booking pilota, riconciliazione della cancellazione BID e chiusura ACARS dello slot sono stati validati nei flight test. La logica di completamento riusabile è in `MFO/lib/acars_lifecycle.php`; `mfo_ft02.php` rimane test-only. Nessun file del core VaBase è modificato in questa repository.

### Persistenza
La tabella `voy_mfo_slots` è già stata aggiornata sul database VOY: include `activity_id`, `activity_leg_id` univoco, riferimenti BID/ACARS/PIREP, stati e campi bonus XP. Resta il vecchio `mfo_month`, da rimuovere dopo una migrazione verificata. Il registro `voy_mfo_programs` è utilizzato dalle pagine amministrative MFO e risulta operativo nei test descritti; `database/001_programs.sql` ne documenta la struttura per nuove installazioni. La presenza e lo schema effettivi in ciascun ambiente devono comunque essere verificati sul relativo database.

### Funzionalità consolidate nei flight test
- Booking esclusivo dello slot con collegamento al BID VaBase.
- Riconciliazione della cancellazione BID prima del completamento.
- Chiusura ACARS a `Disembarking`, con corrispondenza di pilota, BID, tratta e tentativo `actmp_id`, timestamp UTC e protezione idempotente.

### Verifiche e attività ancora aperte prima del rilascio completo
1. Completare la compartimentazione rispetto a Tours & Events e verificare tutti gli accessi diretti, incluso `activity_leg.php`, per impedire prenotazioni che aggirino il controllo esclusivo MFO.
2. Verificare in modo sistematico concorrenza, rollback e recupero dagli errori tra database VOY e API VaBase, oltre ai casi già validati nei flight test.
3. Eseguire la revisione di sicurezza e autorizzazioni (permessi, CSRF, escaping, controlli input) sulle pagine destinate al rilascio.
4. Implementare e validare il bonus XP idempotente nel branch `feat/mfo-xp` e definire la verifica del PIREP, se richiesta dal processo di accredito.
5. Verificare lo schema database effettivo e pianificare la rimozione del campo legacy `mfo_month` tramite migrazione controllata.

La baseline lifecycle resta fissata nel commit `7f9b288` di `feat/mfo-pilot`; lo sviluppo XP prosegue separatamente in `feat/mfo-xp`.


## Cron giornaliero XP/Cash (FT-03, non ancora in produzione)
- Endpoint predisposto: `public/cron_settlement.php`, destinazione prevista `/mfo/cron_settlement.php`.
- Il cron **non** rileva ACARS: elabora programmi con tutte le legs `COMPLETED`/`OBSOLETE`, almeno una `COMPLETED`, e riprende soltanto liquidazioni `PENDING` di programmi `READY`.
- Autenticazione: header HTTPS `X-MFO-CRON-TOKEN`, corrispondente a variabile ambiente server `MFO_CRON_TOKEN` (almeno 32 caratteri). Non mettere il segreto nella URL.
- Kill switch: `MFO_PAYOUT_ENABLED=1` deve essere configurato esplicitamente; altrimenti l'endpoint restituisce 503 senza modifiche.
- Lock MySQL `GET_LOCK` per evitare sovrapposizioni del cron; stato `PROCESSING`/`UNCERTAIN` mai ritentato automaticamente.
- Anteprima senza scritture: `public/cron_settlement_preview.php`, stesso header.
- **Non abilitare ancora**: verificare compatibilità esatta di `Api::sendSync('POST', 'v1/pilot/credits/', ...)`, schema risposta VaBase, configurazione environment su hosting, disponibilità cron-job.org header e test FT-03.
- Le chiamate API di accredito non sono idempotenti lato VaBase: esiti incerti richiedono riconciliazione manuale.
- Non eseguire pulizia dei dati test né sincronizzazione locale/server prima del via libera.
