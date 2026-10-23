# Amministrazione MFO

Destinazione prevista: `/admin/mfo/`.

Pagine previste: elenco programmi, creazione, modifica, gestione Activity Legs/slot e monitoraggio.

La creazione usa le API Activity native con `Type = Event` fisso e `LegsInOrder = false`; registra l'ActivityId in `voy_mfo_programs`. La gestione è riservata agli amministratori autorizzati, con le stesse verifiche di permesso dell'amministrazione VaBase.

**Non pubblicare ancora:** qui verranno sviluppati e revisionati i file PHP dopo verifica dei sorgenti esistenti.
