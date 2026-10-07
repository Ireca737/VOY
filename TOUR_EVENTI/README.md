# Tour ed eventi

## Stato

**ATTIVO — sistema legacy VaBase**

La sezione Tour ed eventi del sito utilizza ancora l'editor RAW HTML VaBase.

Questo comprende anche la produzione editoriale di **Aeroporto del mese**.

## Regole ancora valide

Restano pienamente operative le regole documentate in:

- `../instructions/voy_editor_rules.json`
- `../instructions/voy_template_base.html`

In particolare restano da rispettare le regole empiriche relative a:

- uso degli attributi `style=`;
- entità HTML compatibili;
- sintassi RAW accettate da VaBase;
- limitazioni che possono causare errori 404/WAF.

Queste regole **non devono essere eliminate o sostituite** finché Tour ed eventi continua a utilizzare il metodo legacy.

## Direzione futura

Il metodo attuale è considerato transitorio.

A tendere, Tour ed eventi dovrà utilizzare un sistema simile a NEWS VOY:

```text
editor moderno
    ↓
trasporto sicuro del contenuto
    ↓
database VOY
    ↓
renderer dedicato
    ↓
pubblicazione nella sezione Tour ed eventi
```

La migrazione dovrà mantenere separata la destinazione editoriale: gli articoli continueranno ad appartenere alla sezione Tour ed eventi, anche se la tecnologia di editing verrà condivisa con NEWS VOY.

## Aeroporto del mese

La documentazione e gli articoli già archiviati restano in:

- [Aeroporto_del_mese](../Aeroporto_del_mese/README.md)
