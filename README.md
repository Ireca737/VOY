# VOY — Contenuti, eventi e sistemi editoriali

Repository di riferimento per la progettazione, redazione e documentazione dei contenuti del portale **Virtual Over Italy**.

Il repository fotografa tre aree editoriali con livelli di maturità differenti.

## 1. NEWS VOY — sistema attuale e di riferimento

**NEWS VOY** rappresenta il sistema editoriale più moderno e maturo attualmente in uso sul sito VOY.

Caratteristiche principali:

- modulo custom esterno al core VaBase;
- persistenza su database VOY;
- editor con trasporto Base64 del contenuto HTML/Editor.js;
- supporto affidabile agli stili inline senza gli storici problemi WAF/ModSecurity del RAW VaBase;
- pubblicazione differenziata Home / Pilot Centre;
- teaser dedicato;
- conteggio visualizzazioni;
- statistiche amministrative;
- template editoriali specializzati;
- base tecnologica di riferimento per le future evoluzioni editoriali del sito.

Documentazione: [NEWS_VOY](NEWS_VOY/README.md)

## 2. Tour ed eventi — sistema legacy ancora operativo

La sezione **Tour ed eventi**, compreso **Aeroporto del mese**, utilizza ancora il metodo RAW HTML VaBase documentato nella cartella `instructions`.

Le regole relative a `style=`, entità HTML, sintassi compatibili e limitazioni VaBase **restano valide e non devono essere rimosse**, perché sono ancora necessarie per la pubblicazione in questa parte del sito.

A tendere, Tour ed eventi dovrà migrare verso un sistema simile a NEWS VOY, con persistenza strutturata e gestione editoriale più robusta.

Documentazione: [Tour ed eventi](TOUR_EVENTI/README.md)

### Eventi attualmente archiviati

- [Aeroporto del mese](Aeroporto_del_mese/README.md)

## 3. VOY Tutorial — temporaneamente sospeso

**VOY Tutorial** usa un metodo concettualmente vicino a NEWS VOY, ma precedente e più macchinoso.

Per il momento resta congelato. La direzione prevista è integrarne la produzione nel nuovo sistema editoriale, mantenendo però la pubblicazione degli articoli nella sezione Tutorial/Academy del sito anziché nel feed News.

Documentazione: [VOY Tutorial](VOY_TUTORIAL/README.md)

## Istruzioni e riferimenti legacy

Questi file restano il riferimento per le aree del sito che utilizzano ancora il RAW VaBase:

- [Regole VOY Editor per RAW HTML VaBase](instructions/voy_editor_rules.json)
- [Template HTML di riferimento](instructions/voy_template_base.html)
- [CSS tutorial di riferimento](instructions/tutorial_article.css)

> Le regole legacy non descrivono NEWS VOY. NEWS VOY usa una pipeline editoriale diversa e più moderna, documentata nella propria sezione.
