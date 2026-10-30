# VOY MFO — Baseline lifecycle v1.0.0

- Tag immutabile: `mfo-lifecycle-v1.0.0`
- Commit collaudato: `a51babc`
- Branch: `feat/mfo-lifecycle`
- Consolidamento: 30 ottobre 2026
- Collaudo: Activity VaBase `5280`

## Regole confermate

1. La prenotazione esclusiva associa la Leg e il pilota allo slot e crea il BID VaBase.
2. Dopo la conferma, lo slot resta BOOKED anche quando il BID scompare. La sua assenza non prova né annullamento né completamento.
3. All'apertura/refresh della CREW FLIGHT BOARD (`slot.php`), `mfo_reconcile_activity_history()` interroga lo storico `GET v1/activity/history/{activityId}/{pilotId}` per i piloti con slot BOOKED.
4. Solo la presenza della stessa Leg in `activityLegHistories` permette l'UPDATE condizionato BOOKED → COMPLETED, con pilota conservato e `completed_at` UTC. La riconciliazione è idempotente.
5. Se lo storico non è disponibile, BOOKED persiste e sarà verificato alla successiva apertura della bacheca. Nessun cron o polling ACARS è richiesto.
6. OBSOLETE non compare nella bacheca; lo stato ACARS è distinto dallo stato della prenotazione.

## Collaudi reali

- **VY1102 / Leg 42671:** una riga erroneamente liberata dalla precedente versione è stata riparata manualmente a BOOKED; la riconciliazione ha trovato il completamento nello storico anche senza BID. La riparazione SQL non è una procedura ordinaria.
- **VY1103 / Leg 42672:** prenotazione, volo ACARS, END FLIGHT/PIREP e successivo refresh hanno portato a COMPLETED.
- **VY1104 / Leg 42673:** dopo END FLIGHT/PIREP e spegnimento del PC simulatore, il DB mostrava ancora BOOKED; il refresh della bacheca ha riconciliato a COMPLETED.

I collaudi verificano i casi osservati, non ogni possibile concorrenza o errore API.

## Componenti

- `slots.php`: elenco programmi.
- `slot_leg.php`: prenotazione.
- `slot.php`: CREW FLIGHT BOARD, con BOOKING STATUS e BOOK NOW.
- `MFO/lib/activity_lifecycle.php`: riconciliazione produttiva con lo storico VaBase.
- `MFO/lib/acars_lifecycle.php` e `mfo_ft02.php`: esperimenti FT-02, non utilizzati dal lifecycle produttivo.

## Fuori ambito

Compartimentazione completa dalle pagine Activity native, test sistematici di concorrenza e sicurezza, XP/Cash e settlement, migrazioni legacy. Il bonus XP previsto usa `get_distance()` VaBase con `floor(NM/10)`, da validare separatamente. Nessun deploy o cron è implicato da questa documentazione.

Il tag v1.0.0 resta immutato; le modifiche documentali sono successive al commit di baseline.
