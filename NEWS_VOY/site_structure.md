# NEWS VOY — mappa della struttura sito

Questa mappa descrive la porzione di `/public_html` coinvolta dal modulo NEWS VOY.

La struttura VaBase originaria resta il contenitore generale del sito; NEWS VOY è un modulo custom inserito accanto al core e non sostituisce le cartelle core VaBase.

## Struttura funzionale

```text
/public_html/
│
├── index.php
│   └── include pubblico del widget con $newsFeedPublicOnly = true
│
├── news_voy_item.php
│   ├── legge voy_news
│   ├── renderizza Editor.js / RAW HTML
│   └── incrementa views
│
├── site_widgets/
│   └── news_feed_voy.php
│       ├── query voy_news
│       ├── filtro is_public per la Home
│       ├── teaser
│       ├── carousel
│       └── visualizzazioni
│
├── site_pilot_functions/
│   └── pilot_centre.php
│       └── include del widget senza filtro pubblico
│
├── voy/
│   └── secure_config/
│       └── conn_voy.php
│           └── connessione mysqli al DB VOY
│           [NON VERSIONARE CREDENZIALI]
│
└── admin/
    │
    ├── index.php
    │   └── dashboard con riepilogo statistiche NEWS VOY
    │
    ├── includes/
    │   └── sidebar.php
    │       └── voce "News VOY"
    │
    └── news_voy/
        ├── index.php
        │   └── elenco + creazione Editor.js
        ├── edit.php
        │   └── modifica Editor.js
        ├── index_raw.php
        │   └── creazione RAW HTML + template
        ├── edit_raw.php
        │   └── modifica RAW HTML
        ├── news_db.php
        │   └── CRUD su voy_news
        ├── editor_voy.js
        │   └── Editor.js + codifica Base64 al submit
        └── news_stats.php
            └── dettaglio statistiche
```

## Flusso dati

```text
ADMIN
/admin/news_voy/index.php
/admin/news_voy/index_raw.php
        │
        ├── editor_voy.js
        │      ↓ Base64
        │
        └── news_db.php
               ↓
             voy_news
               ↓
     ┌─────────┴─────────┐
     ↓                   ↓
news_voy_item.php   news_feed_voy.php
     ↓                   ↓
articolo completo   Home / Pilot Centre
```

## Dipendenze dal sito esistente

NEWS VOY riusa infrastruttura VaBase già esistente:

- `/lib/functions.php`
- `/config.php`
- `/proxy/api.php` dove ancora necessario all'area admin
- `/includes/header.php`
- `/includes/footer.php`
- `/admin/includes/nav.php`
- `/admin/includes/sidebar.php`

Queste dipendenze non trasformano NEWS VOY in una modifica del core: il codice specifico resta confinato nei file custom sopra elencati.

## Database

```text
voy_news
├── id
├── title
├── teaser
├── poster
├── date
├── news
├── is_public
└── views
```

## Copia presente nella repository

La cartella:

```text
NEWS_VOY/site_snapshot/public_html/
```

replica i percorsi dei file custom che appartengono direttamente al modulo e consente di capire dove vanno collocati sul server.

Non contiene credenziali né `conn_voy.php`.

## File di integrazione da acquisire dal server

I seguenti file contengono integrazioni NEWS VOY ma sono file generali del sito; prima di versionarli integralmente va acquisita la versione live corrente:

```text
/public_html/index.php
/public_html/site_pilot_functions/pilot_centre.php
/public_html/admin/index.php
/public_html/admin/includes/sidebar.php
/public_html/admin/news_voy/news_stats.php
```

Per questi file è preferibile conservare in repository la versione live completa oppure una patch documentata, evitando di ricostruirli da frammenti.

## Nota sullo snapshot

I file amministrativi archiviati provengono dalle versioni prodotte durante lo sviluppo NEWS VOY. Il widget è stato riallineato alle ultime modifiche definite in chat: cornice/pannello, titolo nella testata della card, campo `views` e indicatore Unicode delle visualizzazioni.

La repository deve essere aggiornata nuovamente se il server live contiene modifiche successive.
