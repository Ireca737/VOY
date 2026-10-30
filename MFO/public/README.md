# Vetrina e prenotazioni MFO

Le pagine operative sono nella radice web: `slots.php` (programmi), `slot.php` (CREW FLIGHT BOARD), `slot_leg.php` (prenotazione esclusiva).

La bacheca mostra AVAILABLE, BOOKED e COMPLETED e riconcilia gli slot BOOKED con lo storico delle Activity VaBase al caricamento. La scomparsa del BID non libera una prenotazione confermata.

Questa cartella `MFO/public/` contiene invece componenti accessori, inclusi endpoint di settlement/cron non abilitati dalla baseline lifecycle v1.0.0. Non attivarli in produzione senza collaudo e autorizzazione separati.

La compartimentazione completa rispetto alle pagine native Activity rimane da verificare. Vedere [baseline](../docs/BASELINE_LIFECYCLE_V1.md).
