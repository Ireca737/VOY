# Istruzioni legacy VaBase

Questa cartella documenta il metodo RAW HTML usato dalle sezioni del sito che non sono ancora migrate al nuovo sistema editoriale.

## Ambito attuale

Le regole qui presenti restano valide soprattutto per:

- Tour ed eventi;
- Aeroporto del mese;
- altri contenuti ancora inseriti direttamente attraverso il RAW VaBase.

## Importante

Le limitazioni relative a `style=`, entità HTML e sintassi che possono provocare errori 404/WAF **sono ancora attuali per queste aree**.

Non applicare automaticamente tali limitazioni a NEWS VOY.

NEWS VOY utilizza una pipeline diversa basata su codifica Base64 del contenuto durante il POST e decodifica lato PHP, evitando il problema che aveva imposto i workaround del RAW VaBase.

## VOY Tutorial

Il materiale Tutorial presente in questa cartella è storico e rimane come riferimento fino alla riprogettazione del modulo.
