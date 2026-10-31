# MFO — Riproponi slot

## Scopo
**Riproponi slot** permette all'amministratore di riutilizzare un programma MFO esistente senza reinserire manualmente la testata e le tratte. L'esperienza deve apparire come una normale modifica di un programma già compilato; la creazione di nuovi identificativi è un dettaglio tecnico invisibile all'utente.

## Flusso amministrativo
1. In `MFO/admin/index.php`, scegliere **Riproponi slot** accanto a un programma esistente.
2. Un modulo separato, `MFO/admin/repropose.php`, legge il programma originale e duplica la sua Activity e le tratte ammesse.
3. Terminata la duplicazione, il modulo apre la consueta schermata `edit.php?id=NUOVO_ACTIVITY_ID`, con testata e tratte già presenti.
4. L'amministratore aggiorna **manualmente** le date di validità, modifica eventuali altri campi o tratte se lo desidera, seleziona esplicitamente **Attivo** e salva.

## Regole di duplicazione
- **VaBase è autoritativo per le Activity e le Activity Legs.** Creare una nuova Activity con `POST v1/activity` e ogni nuova Leg con `POST v1/activity/leg`; gli ID sono assegnati da VaBase, non calcolati tramite SQL locale o sequenze presunte.
- Leggere la sorgente tramite `GET v1/activity/{activityId}`; utilizzare `voy_mfo_slots` per selezionare **solo le tratte non `OBSOLETE`**.
- Copiare fedelmente i campi della testata e delle Legs compatibili con i payload di creazione. Per le Legs, `Duration=null`, come nell'attuale `create_leg.php`.
- Copiare anche `StartDate` ed `EndDate` originali, **senza verifica preventiva di scadenza o obbligo di modifica**: l'aggiornamento è responsabilità dell'amministratore. Restano possibili gli errori di validazione restituiti dall'API.
- La **nuova Activity nasce sempre con `Active=false`**, anche se l'originale è attiva. Nessuna attivazione automatica al termine della duplicazione.
- Registrare un nuovo record `voy_mfo_programs`, associato al nuovo `activity_id`; verificare che il suo `status_mfo` sia `OPEN` prima di creare le Legs.
- Registrare un nuovo record `voy_mfo_slots` per ciascuna Leg creata, con nuovo `activity_leg_id` e `status_mfo='AVAILABLE'`. Non trasferire piloti, BID, completamenti o liquidazioni. Il programma originale non cambia.
- Riutilizzare, per quanto possibile, le convenzioni e le protezioni già implementate in `create.php`, `create_leg.php` e `_common.php`, senza modificare il core VaBase o il lifecycle MFO.

## Sicurezza ed errori
- Modulo accessibile solo con i permessi amministrativi MFO; un semplice **GET non deve creare record**. Eseguire la duplicazione con POST protetto da CSRF e conferma intenzionale.
- Proteggere da doppio clic, richieste concorrenti, retry e risposte API incerte. La creazione remota **non è una transazione SQL atomica**: in caso di esito ambiguo o parziale, interrompere e richiedere verifica/riconciliazione prima di ripetere.
- **Nessun journal persistente e nessuna nuova tabella DB** nella prima versione; nessuna ripresa automatica.
- Non imporre in `edit.php` una conferma speciale delle nuove date.
- Collaudare usando come sorgente un programma MFO esistente, tenendo presente che il test crea effettivamente nuovi record VaBase e MFO.

## Perimetro e sviluppo
- Branch di lavoro: `feat/mfo-repropose`, derivato da `feat/mfo-lifecycle`.
- Nuovo modulo previsto: `MFO/admin/repropose.php`; pulsante in `MFO/admin/index.php`.
- A duplicazione terminata, tutte le modifiche e l'attivazione avvengono nelle schermate amministrative già esistenti.
- Il tag `mfo-lifecycle-v1.0.0` resta immutato; nessuna modifica automatica al server di produzione.

## Stato
Specifica funzionale concordata. L'implementazione e il collaudo del plugin sono attività successive.
