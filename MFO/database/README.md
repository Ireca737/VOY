# Database MFO

Database VOY: `virtualoveritaly_voy`.

Le tabelle `voy_mfo_slots` e `voy_mfo_programs` sono già operative nell'ambiente collaudato della baseline lifecycle v1.0.0 (`a51babc`). Non eseguire `001_programs.sql` come se la tabella programs fosse ancora da creare: è un riferimento per nuovi ambienti e migrazioni controllate.

`voy_mfo_slots` contiene `activity_id`, `activity_leg_id` UNIQUE, pilota, BID e stati `AVAILABLE`, `BOOKED`, `COMPLETED`, `OBSOLETE`. Il completamento avviene tramite storico Activity VaBase, con `completed_at` registrato al momento della riconciliazione.

Il campo legacy `mfo_month` richiede valutazione separata. Verificare schema e backup prima di migrazioni. Non pubblicare credenziali o dati personali.

Vedere [baseline](../docs/BASELINE_LIFECYCLE_V1.md).
