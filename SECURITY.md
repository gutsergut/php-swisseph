# Security policy

## Supported versions

The project is pre-1.0. Security fixes are applied to the latest `main` branch and the newest tagged release, when one exists.

## Reporting a vulnerability

Use GitHub's private vulnerability reporting for this repository. If that feature is unavailable, contact the maintainer through the private address listed on the GitHub profile and request a secure channel before sharing details.

Do not open a public issue for a vulnerability and do not include credentials, private birth data, production URLs with tokens or licensed ephemeris files in a report.

Please include affected revision, impact, reproduction steps and any proposed mitigation. Expect an acknowledgement within seven days. Disclosure timing will be coordinated after a fix is available.

## Security boundaries

- Ephemeris and catalogue paths are untrusted input in hosted applications; restrict them to approved directories.
- This library uses process-global Swiss-style settings. Long-running or multi-tenant applications must isolate mutable calculation context.
- Remote ephemeris services and their credentials are outside this package and must fail closed when high precision is required.
- Astronomical output is not a substitute for safety-critical navigation, medical, legal or financial advice.
