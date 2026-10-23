# Database MFO

Database operativo VOY: `virtualoveritaly_voy`.

**Esistente e verificata:** `voy_mfo_slots`, con chiave primaria `slot_id`, riferimento `activity_id`, `activity_leg_id` UNIQUE, campi di prenotazione e bonus. Il campo `mfo_month CHAR(7) NOT NULL` è un residuo della prima progettazione; sarà necessaria una migrazione prima di creare slot relativi a periodi non mensili.

**Da creare:** `voy_mfo_programs`, registro di associazione ActivityId → MFO. Lo script `001_programs.sql` è una proposta minima; richiede revisione prima di esecuzione.

**Non inserire** password, token API, credenziali DB o dati personali in repository.
