# Release status

This is a working development implementation. It has not been deployed to production or submitted to app stores.

## Evidence in the current cloud instance

- PHP 8.4 container built with verified HTTPS package sources and platform trust configuration.
- MySQL 8.4 migrations and repeatable sample seeding completed.
- 24 feature tests / 81 assertions passed on SQLite and on isolated MySQL.
- A live HTTP smoke test verified signed email verification, 20 question assignments, private grading keys, option validation, a correct reward, duplicate rejection and token revocation.
- Two concurrent MySQL workers produced exactly one answer and one reward.
- Expo TypeScript checks and Android/iOS JavaScript bundle exports completed. These do not prove native device behavior or signed binary builds.
- Authenticated encrypted backup creation, tamper rejection and an isolated MySQL restore matched source category, user and ledger counts.
- Composer platform requirements passed; Composer audit reported no advisories.
- Metro served a real Android development bundle; scheduler and queue workers started.
- The remaining npm audit reports 15 high findings tracing to two upstream advisories; the UUID advisory was resolved.

## Dependency release blockers

The npm audit identifies upstream advisories in development/build tooling. Compatible updates were applied and Xcode's UUID dependency is overridden to CommonJS-compatible 11.1.1. At the time of checking, no patched registry releases were available for:

- `braces` 3.0.3: GHSA-vfj7-8cjw-p6xm, nested-pattern denial of service.
- `node-forge` 1.4.0: GHSA-86w9-cpqp-85rv, RSA signature verification.

Do not accept untrusted glob patterns in development tooling or use the affected signature verification for release trust decisions. Do not disable signatures or force an Expo downgrade to clear the audit. The CI npm audit is deliberately a release gate; it will fail while unresolved high advisories remain. Recheck patched supported packages before production release.

## Configuration and external release work

- Configure real email delivery and verify delivery of verification/reset messages.
- Configure an Expo project and optional authenticated push; test opt-in, revocation and delivery on physical devices. Push-provider behavior is mocked in tests; no real push delivery is claimed.
- Review the terms, privacy policy, collection purpose, retention period, anonymized educational-ledger retention and age/parental-consent requirements with qualified reviewers.
- Confirm `info.chillbreeze.learnandearn` as the permanent app ID, finalize branding, obtain signing credentials and perform Android/iOS builds and store submissions in your own accounts.
- Provision HTTPS hosting, supervised queue/scheduler services, monitoring and managed encrypted backups. Validate production backup restoration and deployment rollback.
- Complete independent security review, accessibility review and native permission validation (including iOS selected/limited contacts and Android declined permissions).

## Scope limits

The five seeded admin roles are enforced, but a custom role-editor interface is not provided. Reports include core participation, registration, coin, consent/deletion metrics and a basic large-reward review indicator; advanced fraud detection and special challenges are not implemented. A location purpose must be configured before optional sharing can be enabled. There is no cash redemption program.

## Hostinger and native build preparation

- A production-dependency Hostinger archive was built for `chillbreeze.info`, without application credentials, database files or development test dependencies. The split public/private entrypoint returned HTTP 200 for health and admin login and HTTP 404 for `.env` in a local check. Archive integrity and writable directory entries passed validation. Actual Hostinger deployment has not occurred.
- Mobile configuration uses `https://chillbreeze.info/api/v1`; EAS preview explicitly requests an APK. Java 21/17, Android SDK 36, NDK 27.1 and CMake were installed and checksums verified for direct tool archives. Native Gradle plugins compiled.
- The APK build failed when Maven redirected React Native 0.86.3 binaries to `repo.reactnative.dev`: the cloud proxy rejected that domain with HTTP 403. The required domain was saved in the cloud configuration draft but is not applied until publication. **No APK was generated.** Retry after publishing the network update, or use the documented EAS build from an authenticated Expo account.
