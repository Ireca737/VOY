# NEWS VOY — mappa della struttura sito

Questa mappa descrive la porzione di `/public_html` coinvolta dal modulo NEWS VOY.

NEWS VOY è un modulo custom inserito accanto al core VaBase. Non sostituisce il core e non richiede di versionare credenziali o configurazioni sensibili.

## Struttura funzionale sul sito

```text
/public_html/
│
├── index.php
│   └── Home: include del widget con $newsFeedPublicOnly = true
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
    ├── index.php
    │   └── dashboard con riepilogo statistiche NEWS VOY
    ├── includes/
    │   └── sidebar.php
    │       └── voce "News VOY"
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
index.php / index_raw.php
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

## Baseline versionata in GitHub

La copia dei file custom è in:

```text
NEWS_VOY/site_snapshot/public_html/
```

e contiene:

```text
admin/news_voy/
├── index.php
├── edit.php
├── index_raw.php
├── edit_raw.php
├── news_db.php
└── editor_voy.js

news_voy_item.php

site_widgets/
└── news_feed_voy.php
```

I template editoriali sono in:

```text
NEWS_VOY/templates/
├── template_news4voy.html
└── template_bacheca_compagnia.html
```

Lo schema della tabella è documentato in:

```text
NEWS_VOY/database/voy_news.sql
```

## Integrazioni con file generali del sito

I file generali del sito non vengono duplicati integralmente nella cartella del modulo quando contengono molte altre funzioni non correlate a NEWS VOY.

Le integrazioni sono quindi isolate e documentate in:

```text
NEWS_VOY/integrations/
├── home_news_voy.php
├── pilot_centre_news_voy.php
├── admin_sidebar_news_voy.php
└── admin_dashboard_stats.php
```

Questi file sono frammenti di riferimento e non vanno caricati sul server come pagine autonome.

## Ricognizione Google Drive

La cartella Drive delle integrazioni è stata ricontrollata.

Documentazione completa:

`NEWS_VOY/drive_inventory.md`

La ricognizione ha confermato:

- modulo amministrativo `admin/news_voy/`;
- widget NEWS VOY;
- renderer `news_voy_item.php`;
- template NEWS4VOY;
- template Bacheca di Compagnia;
- copie storiche/intermedie del widget;
- una variante Home con l'include del feed pubblico.

Poiché Drive contiene anche copie di lavoro e versioni intermedie, la presenza di un file non implica automaticamente che sia il file effettivamente attivo sul server live.

## Dipendenze dal sito esistente

NEWS VOY riusa:

- `/lib/functions.php`;
- `/config.php`;
- `/proxy/api.php` dove necessario;
- `/includes/header.php`;
- `/includes/footer.php`;
- `/admin/includes/nav.php`;
- `/admin/includes/sidebar.php`.

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

## Stato di alcuni file di integrazione

La struttura logica prevede anche:

```text
/public_html/admin/news_voy/news_stats.php
/public_html/site_pilot_functions/pilot_centre.php
/public_html/admin/index.php
/public_html/admin/includes/sidebar.php
```

La ricognizione Drive non ha fornito una copia univoca e aggiornata di tutti questi file generali. Per questo GitHub conserva i frammenti NEWS VOY pertinenti e non ricostruisce arbitrariamente l'intero file.

## Regola di sincronizzazione

1. **Drive** = archivio tecnico delle integrazioni e delle copie di lavoro.
2. **Server live** = verità sul file effettivamente in esecuzione.
3. **GitHub** = baseline versionata, documentata e riproducibile.
