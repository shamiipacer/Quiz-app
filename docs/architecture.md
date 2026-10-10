# Quiz & Rewards architecture

## Project layout
- `backend/`: Laravel 12 REST API, Blade administration, migrations, seeders and feature tests.
- `mobile/`: Expo React Native TypeScript client. Tokens are held in SecureStore; answers and balances are authoritative on the server.
- `docs/`: API, operations, administration and privacy review documentation.
- `compose.yaml`: reproducible PHP and MySQL development services.

## Data model
Users own profiles, timezone-based daily assignments, answers, daily progress and an append-only coin ledger. Categories own versioned questions and options. Assignments uniquely identify user, local date and question. Answers are unique per assignment. Reward transactions uniquely reference the answer or completion bonus. A user row lock serializes concurrent reward operations. Question content is snapshotted into assignments to preserve grading across content edits.

Consent records identify purpose, policy version, authorized fields and revocation time. Contact and location submissions are separate restricted resources with retention deadlines. Admin roles and permissions control every administrative operation; sensitive access and manual adjustments are audited. Deletion requests are processed under an explicit retention policy.

## API boundaries
`/api/v1` provides public rate-limited authentication and authenticated Sanctum endpoints for profile, categories, daily assignments, answer submission, coins, notifications and optional sharing. Question responses exclude grading keys before submission. Assignment IDs identify answers; the user ID always comes from authentication.

Web administration uses session authentication, CSRF protection and explicit role authorization. No administrative credential is embedded in the mobile application.

## Milestones and acceptance
1. Foundation: Laravel and Expo projects, MySQL schema, reproducible setup, migrations and seeds.
2. Identity: registration, verification, login, logout, reset, profile and deletion requests; feature tests.
3. Administration: role-protected categories and user management; authorization tests.
4. Quiz: scheduled publication, stable daily selection, server grading, history; timezone tests.
5. Rewards: transactional append-only ledger, duplicate protection and configurable bonus; concurrency tests against MySQL.
6. Mobile: navigation, dashboard, quizzes, history, account and themes; type check and device smoke tests.
7. Privacy: explicit consent, selected contacts and foreground location, revocation and deletion; permission tests on physical devices.
8. Communications: announcements, configurable push provider, reports and CSV exports.
9. Assurance: automated security checks, abuse tests, accessibility and recovery drills.
10. Release: HTTPS hosting, backups, queue/scheduler, store builds and distribution.

Production release requires configured email and push providers, approved privacy text, HTTPS hosting, physical-device permission tests, real MySQL concurrency tests and backup restoration validation. A development build is not a production certification.
