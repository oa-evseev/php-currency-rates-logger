# Project instructions

Before planning or modifying files, read:

`~/projects/AGENTS.md`

If the workspace policy cannot be read, stop and report that it is unavailable.

The rules below supplement or tighten the workspace policy for this repository.

## Project boundary

- This maintenance PHP 7.2+ utility runs as a production cron job, fetches external rates, and writes production database state.
- Preserve standalone PHP/PDO compatibility and the provider extension model; do not modernize unrelated code.
- Its database identity should have only the reads and currency-rate writes required by the pipeline. Do not broaden database or API permissions while changing provider behavior.

## Production boundary

- Do not inspect the installed configuration or cron environment.
- `php run.php` performs network and database work; `index.php` exposes the same operation through a web UI. Neither is a development check, and neither may be run without an explicit production operation request.
- Do not use a real database or provider credential in tests. Any new tests must use synthetic provider responses and a disposable fake data layer.

## Actual contract and done criteria

- There is no Makefile, automated test suite, or CI workflow.
- Validate PHP changes with a safe syntax-only command on the changed files when a compatible interpreter is available; state the PHP 7.2 compatibility limitation.
- A task is complete only when append-only rate behavior, locking, normalization, and least-privilege assumptions remain intact and external verification is clearly deferred.
