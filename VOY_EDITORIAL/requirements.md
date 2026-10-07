# VOY Editorial — Requisiti funzionali

## 1. Visione

VOY Editorial deve fornire a più redattori, assistiti dall'AI, uno spazio editoriale condiviso nel quale creare, sviluppare, organizzare e revisionare liberamente contenuti indipendenti dal prodotto editoriale finale.

Il principio fondamentale è:

> **VOY Editorial organizza il lavoro della redazione, non decide al posto della redazione.**

Il sistema deve quindi favorire collaborazione, memoria editoriale, composizione e pubblicazione senza imporre a priori dove, quando o in quale formato un contenuto debba essere utilizzato.

## 2. Desktop editoriale condiviso

Il patrimonio editoriale attivo viene rappresentato concettualmente come un desktop condiviso.

Il desktop contiene **cellule editoriali**, assimilabili a cartelle di lavoro. Una cellula può contenere uno o più contenuti, materiali, contributi o altri elementi editoriali.

La struttura interna definitiva delle cellule non viene fissata in questa fase: dovrà emergere dall'uso reale della redazione.

Le cellule non appartengono a NEWS4VOY, alla Bacheca o ad altri prodotti. Esistono autonomamente nel patrimonio editoriale.

## 3. Contenuti liberi dalla destinazione

Un redattore deve poter creare e sviluppare un contenuto senza stabilire preventivamente:

- dove verrà pubblicato;
- quando verrà pubblicato;
- quale template verrà utilizzato;
- se verrà effettivamente pubblicato.

Un contenuto può quindi maturare completamente prima che la redazione decida la sua destinazione.

Non devono esistere vincoli di compatibilità tra contenuto/cellula e format editoriale. Se la redazione decide, ad esempio, di utilizzare un editoriale nella Bacheca, il sistema non deve impedirlo.

## 4. Collaborazione

Più redattori possono partecipare alle cellule con responsabilità e permessi differenti.

La collaborazione deve poter distinguere concettualmente almeno:

- chi può vedere un contenuto;
- chi può modificarlo;
- chi può contribuire o proporre modifiche;
- chi ha responsabilità di revisione o approvazione.

Le assegnazioni possono variare da cellula a cellula e non devono dipendere esclusivamente dal ruolo generale dell'utente.

## 5. AI come partecipante alla redazione

L'AI opera come partecipante assistivo della redazione.

Può, secondo le autorizzazioni e il contesto:

- ricercare informazioni e fonti;
- proporre argomenti;
- produrre bozze;
- sintetizzare materiale;
- revisionare testi;
- suggerire titoli e teaser;
- aiutare a completare cellule incomplete;
- recuperare materiale editoriale disponibile o storico.

La responsabilità editoriale finale rimane umana.

L'AI non deve inventare informazioni interne VOY non disponibili nelle fonti o nel patrimonio editoriale condiviso.

## 6. Decisione editoriale: cosa, dove e quando

Il completamento di un contenuto e la sua pubblicazione sono eventi distinti.

Una volta che un contenuto è disponibile, la redazione decide successivamente:

1. **se** utilizzarlo;
2. **dove** utilizzarlo;
3. **quando** utilizzarlo.

Un contenuto pronto può quindi essere accantonato per un numero successivo, mantenuto disponibile senza destinazione oppure utilizzato immediatamente.

La matrice editoriale deve conservare questa memoria nel tempo.

## 7. Composizioni

I prodotti editoriali sono **composizioni** di contenuti selezionati liberamente dal patrimonio editoriale.

Esempi iniziali:

- NEWS4VOY;
- Bacheca di Compagnia.

Possibili esempi futuri:

- Perle di Saggezza;
- Edizioni Straordinarie;
- Speciali;
- nuovi format non ancora definiti.

Un prodotto editoriale non è proprietario delle cellule che utilizza.

La stessa cellula può, se la redazione lo decide, essere utilizzata in più composizioni.

L'inserimento di un contenuto in una composizione non equivale alla sua rimozione dal patrimonio attivo.

## 8. Template e rendering

Una composizione viene trasformata nel prodotto finale applicando un template.

Il modello concettuale è:

```text
Cellule / contenuti
        ↓
Decisione editoriale
        ↓
Composizione
        ↓
Template
        ↓
Prodotto editoriale
        ↓
Output di pubblicazione
```

Inizialmente l'output previsto è l'HTML destinato all'attuale sistema NEWS VOY.

L'introduzione di un nuovo template o di un nuovo prodotto editoriale non deve richiedere modifiche al modello fondamentale delle cellule.

I template danno forma ai contenuti; non ne determinano la natura.

## 9. Materiale attivo e materiale speso

Il desktop editoriale deve rappresentare principalmente il materiale ancora disponibile alla redazione.

Un contenuto selezionato per una composizione non viene considerato immediatamente “speso”, perché la composizione può ancora essere modificata.

Il contenuto diventa speso quando il prodotto editoriale che lo utilizza viene effettivamente pubblicato.

A quel punto deve uscire dal normale desktop operativo ed essere conservato nell'archivio editoriale.

## 10. Archivio editoriale

Il materiale pubblicato non deve essere cancellato.

L'archivio deve consentire di:

- consultare i contenuti già pubblicati;
- sapere in quali prodotti sono stati utilizzati;
- recuperarli come riferimento storico;
- eventualmente utilizzarli come base per una nuova versione o un nuovo lavoro editoriale.

L'archivio non deve appesantire la normale vista del materiale ancora disponibile.

In termini concettuali:

> **Desktop = ciò che la redazione ha ancora da spendere.**  
> **Archivio = ciò che la redazione ha già speso.**

## 11. Matrice editoriale

La struttura sottostante può essere descritta come una matrice dinamica di relazioni tra partecipanti e cellule.

La matrice non coincide con un singolo prodotto editoriale.

NEWS4VOY, Bacheca e gli altri format selezionano materiale dalla matrice; non la contengono.

Oltre alle relazioni tra persone e contenuti, il sistema deve poter rappresentare nel tempo la maturazione dei contenuti e le successive decisioni di allocazione editoriale.

## 12. Modello logico complessivo

```text
PERSONE + AI
      ↓
DESKTOP EDITORIALE CONDIVISO
      ↓
CELLULE
      ↓
CONTENUTI LIBERI
      ↓
MATURAZIONE / REVISIONE
      ↓
DECISIONE EDITORIALE
   cosa + dove + quando
      ↓
COMPOSIZIONE
      ↓
TEMPLATE
      ↓
PRODOTTO EDITORIALE
      ↓
PUBBLICAZIONE
      ↓
ARCHIVIO
```

## 13. Indipendenza dalla tecnologia di persistenza

In questa fase non viene stabilito dove risiederanno fisicamente cellule e contenuti.

Potranno essere valutate successivamente soluzioni quali:

- Google Drive;
- GitHub;
- database dedicato;
- repository specifico;
- soluzione ibrida.

Il modello editoriale e l'esperienza d'uso non devono dipendere dalla tecnologia di persistenza adottata.

## 14. Perimetro iniziale

La prima implementazione dovrà integrarsi con l'attuale sistema NEWS VOY senza sostituire ciò che già funziona.

In particolare, VOY Editorial nasce **a monte** dell'attuale processo di pubblicazione: organizza la produzione collaborativa e la composizione, per poi consegnare al sistema esistente il prodotto finale.

NEWS_VOY rimane pertanto la baseline del sistema editoriale attualmente operativo.

Le decisioni relative a database, struttura tecnica definitiva delle cellule, storage, API, interfaccia e automazioni verranno affrontate in fasi successive sulla base dei casi d'uso reali.
