# NEWS VOY

## Stato

**ATTIVO — sistema editoriale di riferimento del portale VOY**

NEWS VOY è il modulo custom sviluppato per superare i limiti dell'editor RAW VaBase senza modificare il core VaBase.

È attualmente il sistema editoriale più moderno e maturo del sito, sia dal punto di vista tecnologico sia per l'utilizzo quotidiano.

## Principio architetturale

Il modulo vive accanto a VaBase, ma fuori dal suo core.

```text
/admin/news_voy/
        ↓
PHP custom VOY
        ↓
database VOY / tabella voy_news
        ↓
/news_voy_item.php
        ↓
/site_widgets/news_feed_voy.php
        ↓
Home / Pilot Centre
```

## Database

Tabella principale:

```text
voy_news
--------
id
title
poster
date
news
is_public
teaser
views
```

Significato dei campi principali:

- `news`: contenuto completo dell'articolo;
- `teaser`: sintesi mostrata nel widget;
- `is_public`: determina dove viene pubblicata la notizia;
- `views`: numero complessivo di aperture dell'articolo.

## Visibilità

```text
is_public = 1
    → Home
    → Pilot Centre

is_public = 0
    → Pilot Centre
```

La protezione dell'accesso diretto agli articoli non pubblici va mantenuta come requisito architetturale.

## Pipeline editoriale

Il problema storico del sito era il blocco WAF/ModSecurity dei POST contenenti HTML con attributi `style=`.

NEWS VOY usa una soluzione diversa:

```text
Editor / RAW HTML
      ↓
codifica Base64 nel browser
      ↓
POST
      ↓
base64_decode() lato PHP
      ↓
salvataggio del contenuto normale nel database
```

Questo consente di usare HTML editoriale ricco e stili inline senza applicare i workaround richiesti dal vecchio editor VaBase.

## Famiglie editoriali

### NEWS4VOY

Rubrica editoriale dedicata a:

- mondo della simulazione di volo;
- eventi e network online;
- simulatori e add-on;
- hardware e home cockpit;
- novità tecniche;
- community;
- vita Virtual Over Italy.

### Bacheca di Compagnia

Comunicazioni interne e operative:

- avvisi;
- reminder;
- comunicazioni staff;
- preparazione alle serate;
- informazioni operative per i piloti.

### Evento

Template previsto per la futura automazione calendario → Bacheca.

Stato: **DESIGN / NON IMPLEMENTATO**.

## Statistiche

Ogni apertura di `news_voy_item.php` incrementa `views`.

Le visualizzazioni sono:

- mostrate nel widget;
- riepilogate nella dashboard amministrativa;
- consultabili nella pagina statistiche dedicata.

## File server coinvolti

Baseline funzionale:

```text
/public_html/admin/news_voy/index.php
/public_html/admin/news_voy/edit.php
/public_html/admin/news_voy/index_raw.php
/public_html/admin/news_voy/edit_raw.php
/public_html/admin/news_voy/news_db.php
/public_html/admin/news_voy/editor_voy.js
/public_html/admin/news_voy/news_stats.php

/public_html/news_voy_item.php
/public_html/site_widgets/news_feed_voy.php
/public_html/site_pilot_functions/pilot_centre.php
/public_html/index.php
/public_html/admin/index.php
/public_html/admin/includes/sidebar.php
/public_html/voy/secure_config/conn_voy.php
```

Il file `conn_voy.php` è usato dal modulo ma non deve essere versionato con credenziali o dati sensibili.

## Automazione eventi — direzione prevista

Flusso progettuale:

```text
Calendario eventi
      ↓
evento tra 2 giorni
      ↓
lettura URL dettaglio evento
      ↓
estrazione dati
      ↓
template EVENTO
      ↓
creazione automatica in voy_news
      ↓
pubblicazione
      ↓
ore 01:00 del giorno successivo all'evento
      ↓
rimozione automatica dalla Bacheca
```

Decisione ancora aperta:

- mantenere il calendario JSON esistente;
- oppure introdurre una tabella database dedicata agli eventi.

Il link della pagina evento costituisce oggi la chiave per recuperare i dati di dettaglio.

## Direzione futura

NEWS VOY è la baseline tecnologica da cui far evolvere:

1. Tour ed eventi;
2. VOY Tutorial;
3. automazioni editoriali collegate al calendario.

Le aree legacy non vanno forzatamente convertite finché non viene progettata e testata la migrazione.
