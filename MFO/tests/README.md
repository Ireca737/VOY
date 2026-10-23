# MFO — collaudo offline

Questa cartella contiene test locali **senza connessione a VaBase o al database VOY**.

## 1. Controllo sintattico (PowerShell)

```powershell
$php = "C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe"
Get-ChildItem .\MFO\admin\*.php | ForEach-Object { & $php -l $_.FullName }
```

## 2. Smoke test di isolamento

Dalla radice del repository:

```powershell
& $php .\MFO\tests\smoke.php
```

Questo test verifica in modo statico i contratti essenziali del modulo, inclusi i vincoli MFO, le API utilizzate e l'assenza di riferimenti diretti a credenziali di produzione. Non esegue il codice amministrativo e non apre connessioni di rete.

**Limite importante:** non è un collaudo funzionale di pagine, API o database. Per quello occorrono mock eseguibili delle dipendenze VaBase e un database locale, che saranno aggiunti in una fase separata. Non esporre questi test nella cartella web di produzione.
