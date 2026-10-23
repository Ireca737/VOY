# Vetrina MFO

Destinazione prevista: `/mfo/`.

Mostra esclusivamente le Activity associate a `voy_mfo_programs`, potenzialmente più programmi contemporanei. Ogni programma espone le Activity Legs come slot con stato AVAILABLE, BOOKED, COMPLETED (ed eventuali stati intermedi da definire).

Prenotazione attraverso endpoint controllato MFO: verifica esclusiva atomica in VOY, creazione BID VaBase e trattamento degli errori senza lasciare slot bloccati.

**Non pubblicare ancora:** da verificare prima l'esclusione e il reindirizzamento delle pagine native Activity.
