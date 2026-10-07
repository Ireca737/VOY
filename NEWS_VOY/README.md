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

## Documentazione tecnica

- [Mappa struttura sito](site_structure.md)
- [Inventario integrazioni Google Drive](drive_inventory.md)
- [Schema database](database/voy_news.sql)
- [Template NEWS4VOY](templates/template_news4voy.html)
- [Template Bacheca di Compagnia](templates/template_bacheca_compagnia.html)

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

La protezione dell'accesso diretto agli articoli non pubblici resta un requisito da completare/verificare.

## Pipeline editoriale

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

Questa pipeline evita il blocco WAF/ModSecurity dei POST contenenti HTML con attributi `style=`, che continua invece a condizionare il sistema legacy Tour ed eventi.

## Famiglie editoriali

### NEWS4VOY

Rubrica dedicata a simulazione di volo, eventi, network online, simulatori, add-on, hardware, home cockpit, novità tecniche, community e vita VOY.

### Bacheca di Compagnia

Comunicazioni interne e operative: avvisi, reminder, comunicazioni staff, preparazione alle serate e informazioni operative per i piloti.

### Evento

Template previsto per la futura automazione calendario → Bacheca.

Stato: **DESIGN / NON IMPLEMENTATO**.

## Statistiche

Ogni apertura di `news_voy_item.php` incrementa `views`.

Le visualizzazioni sono mostrate nel widget e riepilogate nell'area amministrativa.

## File custom principali

```text
/public_html/admin/news_voy/index.php
/public_html/admin/news_voy/edit.php
/public_html/admin/news_voy/index_raw.php
/public_html/admin/news_voy/edit_raw.php
/public_html/admin/news_voy/news_db.php
/public_html/admin/news_voy/editor_voy.js

/public_html/news_voy_item.php
/public_html/site_widgets/news_feed_voy.php
```

La baseline versionata è disponibile in `site_snapshot/public_html/`.

## File di integrazione

NEWS VOY interviene anche in file generali del sito:

```text
/public_html/index.php
/public_html/site_pilot_functions/pilot_centre.php
/public_html/admin/index.php
/public_html/admin/includes/sidebar.php
/public_html/admin/news_voy/news_stats.php
```

Per evitare di confondere codice generale del sito e codice NEWS VOY, le porzioni pertinenti sono documentate nella cartella `integrations/`.

## Sicurezza

`/public_html/voy/secure_config/conn_voy.php` viene usato dal modulo ma non deve essere versionato con credenziali o dati sensibili.

## Automazione eventi — direzione prevista

```text
Calendario eventi
      ↓
evento tra 2 giorni
      ↓
URL dettaglio evento
      ↓
estrazione dati
      ↓
template EVENTO
      ↓
creazione automatica in voy_news
      ↓
pubblicazione
      ↓
01:00 del giorno successivo
      ↓
rimozione automatica dalla Bacheca
```

Resta da decidere se mantenere il calendario JSON o introdurre una tabella eventi dedicata.

## Direzione futura

NEWS VOY è la baseline tecnologica da cui far evolvere:

1. Tour ed eventi;
2. VOY Tutorial;
3. automazioni editoriali collegate al calendario.
