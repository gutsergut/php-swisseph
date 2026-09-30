# Contributing

Thank you for helping improve PHP Swiss Ephemeris. Numerical correctness and reproducibility take priority over API-count claims.

## Before opening a pull request

1. Open or reference an issue for a numerical or public-API change.
2. Install dependencies with `composer install`.
3. Run `composer check`.
4. If calculation code changed, also run `composer test:full` and report known unrelated failures separately.
5. Keep generated dependencies, ephemeris files, JPL files, credentials and machine-specific paths out of Git.

## Numerical changes

A numerical fix must include a regression test and enough provenance to reproduce the expected value:

- Swiss Ephemeris version/commit and reference executable hash;
- complete flags and requested backend;
- UTC input, time scale and calendar;
- ephemeris/catalogue file names and SHA-256 hashes;
- raw expected result and returned flags/error;
- comparison tolerance with a short rationale.

Use wrap-aware angular comparisons. Do not increase a tolerance merely to turn a test green. If the requested backend is unavailable, skip with an explicit reason or fail closed; never accept an unreported fallback as parity.

## Code style and scope

- PHP 8.1 is the minimum runtime.
- New source files use `declare(strict_types=1);`, PSR-4 namespaces and explicit parameter/return types.
- Keep refactors separate from numerical changes where practical.
- Avoid bulk formatting of legacy numerical tables.
- Public API breaks require a migration note and are allowed only before 1.0 with an explicit changelog entry.

## Commit and PR guidance

- Use a focused imperative subject, for example `fix: preserve requested backend in calc result`.
- Explain behavior before/after, verification commands and data requirements.
- Never include birth data, API keys or production responses containing personal information.

By contributing, you agree that your work is distributed under AGPL-3.0-or-later and that you have the right to submit it.
