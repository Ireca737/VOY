# NEWS VOY — inventario integrazioni Google Drive

Sorgente di ricognizione:

`modulo extra_voy/public_html`

Drive folder ID radice integrazioni: `1XJqd9HTdZXTIGc-uyM2ziDmNlNk-rWo1`.

## Struttura rilevata

```text
modulo extra_voy/
└── public_html/
    ├── index.php
    ├── _index.php
    ├── news_voy_item.php
    │
    ├── news_voy/
    │   ├── public_html/
    │   │   ├── assets/
    │   │   ├── eventi/
    │   │   ├── devtools/
    │   │   ├── cron/
    │   │   └── flotta/
    │   └── secure_config/
    │
    ├── site_widget/
    │   ├── news_feed_voy.php
    │   └── old_news_feed_voy.php
    │
    └── admin/
        ├── news_voy/
        │   ├── index.php
        │   ├── edit.php
        │   ├── index_raw.php
        │   ├── edit_raw.php
        │   ├── news_db.php
        │   ├── editor_voy.js
        │   └── templates/
        │       ├── template_news4voy.html
        │       ├── template_bacheca_compagnia.html
        │       └── ESEMPIO NR 0.html
        │
        └── site_widgets/
            └── news_voy/
                └── news_feed_voy.php
```

## Versioni osservate

La ricognizione ha evidenziato più copie del widget e del renderer. Questo è coerente con lo sviluppo incrementale avvenuto durante i test.

### Widget

La copia più recente individuata in Drive è:

`modulo extra_voy/public_html/site_widget/news_feed_voy.php`

Modifica Drive: **20 settembre 2026, 22:03 UTC circa**.

Contiene:

- filtro `is_public`;
- campo `teaser`;
- campo `views`;
- pannello esterno con cornice;
- tre card desktop / due tablet / 88% mobile;
- contatore visualizzazioni con simbolo Unicode;
- carousel orizzontale.

La copia `old_news_feed_voy.php` è da considerare storica.

### Renderer articolo

È presente `news_voy_item.php` con:

- lettura da `voy_news`;
- rendering diretto dei blocchi RAW;
- parser Editor.js per gli altri blocchi;
- incremento `views = views + 1`.

## Home

In Drive sono presenti sia `index.php` sia `_index.php`.

La variante `_index.php` contiene l'integrazione:

```php
$newsFeedPublicOnly = true;
include_once 'site_widgets/news_feed_voy.php';
```

collocata prima della sezione mappa.

Poiché Drive conserva entrambe le copie, questa informazione viene documentata senza assumere che il prefisso underscore identifichi automaticamente il file live sul server.

## Admin NEWS VOY

La cartella `admin/news_voy/` contiene il modulo amministrativo completo sviluppato in questa chat.

Il suo flusso è:

```text
index.php / index_raw.php
        ↓
editor_voy.js
        ↓
editorContentB64
        ↓
PHP base64_decode()
        ↓
news_db.php
        ↓
voy_news
```

## Template

I template ufficiali trovati in Drive sono ora versionati anche nella repository:

- `template_news4voy.html`;
- `template_bacheca_compagnia.html`.

`ESEMPIO NR 0.html` resta un esempio editoriale, non la specifica del motore.

## File non versionati per sicurezza

`secure_config/conn_voy.php` non deve essere copiato nella repository perché può contenere credenziali del database.

La repository documenta soltanto il suo percorso e il contratto atteso: disponibilità dell'oggetto mysqli `$conn`.

## Nota sulla struttura Drive

La cartella Drive è un archivio delle integrazioni e non va interpretata automaticamente come replica 1:1 del filesystem live. In particolare sono presenti cartelle e copie storiche con nomi simili.

Per le future sincronizzazioni la regola è:

1. Drive = archivio tecnico e sorgente delle integrazioni;
2. server live = verità sull'effettivo file in esecuzione;
3. GitHub = baseline versionata e documentata.
