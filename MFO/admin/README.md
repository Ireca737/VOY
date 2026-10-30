# Amministrazione MFO

Le pagine amministrative MFO sono presenti nel repository e gestiscono programmi e tratte tramite le Activity VaBase. La baseline lifecycle v1.0.0 è identificata dal commit a51babc.

La gestione dei programmi utilizza voy_mfo_programs e voy_mfo_slots. Il completamento degli slot avviene tramite lo storico Activity VaBase, non tramite il processo sperimentale ACARS FT-02.

Restano da verificare integralmente autorizzazioni, CSRF, gestione degli errori e percorsi alternativi di prenotazione. Il percorso di distribuzione effettivo deve essere verificato sul server: non dedurlo dalla struttura del repository.
