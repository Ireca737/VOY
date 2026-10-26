# Architettura e vincoli MFO

## Riuso VaBase
- Activity: programma, titolo, descrizione, banner, validità e attivazione.
- Activity Legs: rotte indipendenti (LegsInOrder=false).
- BID, ACARS, PIREP e storico: sistema operativo esistente.
- MFO: riconoscimento del programma, slot one-shot, prenotazione esclusiva, bonus XP.

## Compartimentazione
L'Activity MFO usa `Type=Event` solo come valore tecnico API; non deve essere presentata nella vetrina Tours & Events. Accessi diretti alle pagine Activity devono rinviare alla sezione MFO e nessun percorso di creazione BID deve aggirare il controllo esclusivo. Questo requisito richiederà interventi mirati alle pagine del sito e verifica degli endpoint raggiungibili: non è ancora implementato.

## Lifecycle consolidato
`AVAILABLE → BOOKED → COMPLETED` è il ciclo persistente dello slot MFO; `status_flight` e `actmp_id` descrivono separatamente l'evoluzione ACARS live.

Regole validate con i flight test del 25/10/2026:
- il BOOK atomico assegna lo slot e collega il BID VaBase;
- una cancellazione del BID prima del completamento libera lo slot e lo riporta ad `AVAILABLE`;
- durante ACARS vengono memorizzati `actmp_id` e `status_flight`;
- sul medesimo tentativo ACARS, `statStage=Disembarking` chiude definitivamente lo slot con `status_mfo='COMPLETED'` e `completed_at` UTC;
- uno slot `COMPLETED` non viene riaperto quando BID o ACARS scompaiono.

La chiusura è idempotente e vincolata a `activity_leg_id`, `pilot_id`, `bid_id` e allo stesso `actmp_id`. La semplice scomparsa da `v1/bids` o `v1/map/acars` non viene usata come evento di completamento. La logica consolidata è in `MFO/lib/acars_lifecycle.php`; `mfo_ft02.php` resta un harness di test/polling e non un daemon di produzione.

## Integrità
Il vincolo UNIQUE su `activity_leg_id` evita duplicati dello stesso slot, ma non basta a garantire la prenotazione concorrente: usare operazioni atomiche, controllo del numero di righe aggiornate e gestione di eventuali errori tra DB VOY e API VaBase.

## Bonus XP
Regola concordata: bonus per leg completata pari a `floor(distanza VaBase in NM / 10)`, aggiuntivo rispetto agli XP nativi, da verificare rispetto alla funzione distanza e al wallet effettivamente disponibili. Obbligatoria idempotenza per evitare doppi accrediti.

## Sicurezza e distribuzione
Non modificare il core/API VaBase senza necessità dimostrata. Nessuna modifica al sito in produzione durante lo sviluppo; verificare i permessi amministrativi, CSRF, API e controlli di input prima di un rilascio.
