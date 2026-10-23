# MFO: functional model (offline)

Run from the repository root with PHP 8.2+ (PHP 8.3 on Laragon works):

```powershell
$php = "C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe"
& $php .\MFO\tests\functional\run.php
```

This runs an **isolated in-memory model** of the Activity → MFO registry and Leg → slot coordination protocol. It deliberately makes **zero** network or SQL calls and does not require VaBase source, MySQL, Apache, credentials, or production access.

Scenarios: program creation with forced Event/nonordered legs, slot creation as AVAILABLE, editability restriction, non-MFO rejection before API use, and simulated registration failure after VaBase succeeds (orphan detection).

**Scope limit:** this does not execute the current `MFO/admin/*.php` pages. Passing tests validates the coordination *design model*, not the deployed code. The admin PHP still requires refactoring to share the tested service and real adapters. A further test phase must cover CSRF, real SQL transactions/constraints, API response parsing, failure recovery and page rendering. Never infer production readiness from these results.
