# VOY Tutorial

## Stato

**SOSPESO / DA RIPROGETTARE**

VOY Tutorial utilizza un sistema di editing sviluppato prima di NEWS VOY.

Il principio di fondo è simile: consentire contenuti HTML più ricchi rispetto al normale RAW VaBase. L'implementazione attuale, però, è più macchinosa e va riesaminata.

## Decisione attuale

Non vengono effettuate modifiche strutturali in questa fase.

La direzione prevista è:

```text
nuovo sistema editoriale comune
        ↓
stessa tecnologia di NEWS VOY
        ↓
articolo Tutorial
        ↓
pubblicazione / reindirizzamento nella sezione Tutorial del sito
```

Quindi il futuro sistema potrà condividere:

- editor;
- gestione HTML;
- trasporto Base64;
- persistenza database;
- logica amministrativa;

ma **non** la destinazione finale degli articoli.

I Tutorial dovranno continuare a essere presentati nella propria area del sito e non confusi con il normale feed NEWS VOY.

## File di riferimento esistenti

Restano disponibili:

- `../instructions/voy_template_base.html`
- `../instructions/tutorial_article.css`

Questi file descrivono il sistema precedente e non devono essere interpretati come specifica definitiva della futura integrazione.
