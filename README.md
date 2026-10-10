# Learn & Earn

A Laravel 12 / MySQL educational quiz API and Blade admin, with an Expo React Native TypeScript app.

Read [architecture and milestones](docs/architecture.md), [API contract](docs/api.md), [development setup](docs/setup.md), [administration](docs/admin.md), and [deployment](docs/deployment.md), and [verified release status](docs/release-status.md).

## Implemented development workflow

- Registration with policy acceptance, signed email verification, login/logout and password reset.
- Hashed expiring API bearer tokens, password hashing, throttling and account status checks.
- Ten seeded subjects and twenty sample questions.
- Stable user-local daily assignments, snapshotted grading, answer ownership validation and atomic reward accounting.
- Completion bonuses, unique reward references, database-enforced append-only coin and audit ledgers.
- Role-protected admin categories, questions, image uploads, CSV import, user suspension, manual rewards, announcements, reports and privacy operations.
- Mobile dashboard, quiz flow, profile, history, light/dark styling and optional selected-contact/foreground-location confirmation screens.
- Explicit field-specific consent, encrypted private submissions, revocation/deletion and retention cleanup. Sharing is off by default until a purpose and policy are configured.

This repository is an actively developed application, **not a certified production release**. Physical Android/iOS validation, store signing, production email, push delivery, backup restore drills, legal approval and independent security review are release requirements. See the documented limitations before deployment.

For Hostinger PHP/MySQL hosting at `chillbreeze.info`, follow [the Hostinger deployment guide](docs/hostinger.md). The prepared `dist/hostinger.zip` includes production dependencies and a public/private folder layout. The mobile app targets `https://chillbreeze.info/api/v1`; the EAS preview profile explicitly builds an APK. Local ARM64 test APK builds use `scripts/build-test-apk.sh` with the Android SDK and Java toolchains installed.
