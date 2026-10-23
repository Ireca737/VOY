# Architettura e vincoli MFO

## Riuso VaBase
- Activity: programma, titolo, descrizione, banner, validità e attivazione.
- Activity Legs: rotte indipendenti (LegsInOrder=false).
- BID, ACARS, PIREP e storico: sistema operativo esistente.
- MFO: riconoscimento del programma, slot one-shot, prenotazione esclusiva, bonus XP.

## Compartimentazione
L'Activity MFO usa `Type=Event` solo come valore tecnico API; non deve essere presentata nella vetrina Tours & Events. Accessi diretti alle pagine Activity devono rinviare alla sezione MFO e nessun percorso di creazione BID deve aggirare il controllo esclusivo. Questo requisito richiederà interventi mirati alle pagine del sito e verifica degli endpoint raggiungibili: non è ancora implementato.

## Lifecycle proposto
`AVAILABLE → BOOKED → LIVE → PENDING → COMPLETED`.
Una cancellazione o un abort senza PIREP valido può liberare lo slot secondo criteri da definire. Il completamento richiede la conferma del PIREP; non si deduce dalla scomparsa del pilota dalla mappa.

## Integrità
Il vincolo UNIQUE su `activity_leg_id` evita duplicati dello stesso slot, ma non basta a garantire la prenotazione concorrente: usare operazioni atomiche, controllo del numero di righe aggiornate e gestione di eventuali errori tra DB VOY e API VaBase.

## Bonus XP
Regola concordata: bonus per leg completata pari a `floor(distanza VaBase in NM / 10)`, aggiuntivo rispetto agli XP nativi, da verificare rispetto alla funzione distanza e al wallet effettivamente disponibili. Obbligatoria idempotenza per evitare doppi accrediti.

## Sicurezza e distribuzione
Non modificare il core/API VaBase senza necessità dimostrata. Nessuna modifica al sito in produzione durante lo sviluppo; verificare i permessi amministrativi, CSRF, API e controlli di input prima di un rilascio.
