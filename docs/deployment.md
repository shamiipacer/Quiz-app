# Production hosting and mobile distribution

This is a deployment checklist, not evidence of deployment. No hosting account, app store credentials, domain, email provider or signing keys are supplied or embedded.

Use PHP 8.4+, MySQL 8.4, Composer 2, an HTTPS reverse proxy and a production PHP process manager. The development `artisan serve` container is not the production web server. Serve only `backend/public`; deny dotfiles and never expose `.env`, vendor internals, storage logs or backup archives.

Configure `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, database credentials, HTTPS-only secure session cookies, actual mail delivery, trusted proxy settings, and a unique persistent application key. Store secrets in deployment environment settings. Run `composer install --no-dev --prefer-dist --optimize-autoloader`, `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`, and `php artisan storage:link`. Generate cached configuration under the limited application credentials, never the elevated migration credentials. Preserve the encryption key across deploys; rotate keys through a planned migration for encrypted private submissions.

Run `php artisan schedule:run` each minute. Configure supervised queue workers before moving mail/announcements to queues. Establish encrypted automated MySQL backups with limited access and documented expiration. Verify restore into an isolated environment before launch. Ledger triggers require database TRIGGER privileges during migration; production application credentials should not have permissions to alter schema or drop triggers after deployment.

Enable optional sharing only after approval of an actual purpose, policy and retention terms: `SHARING_ENABLED=true`, `SHARING_PURPOSE=<specific approved purpose>`. Update the policy version in configuration and mobile disclosure together. Audit retention cleanup, revocation and deletion on real MySQL. Pseudonymous educational ledger retention requires a documented duration and deletion strategy before release.

## Android

Replace the placeholder application ID and branding in `mobile/app.json`. Configure `EXPO_PUBLIC_API_URL` as HTTPS and publish approved terms/privacy pages. Install EAS CLI, authenticate to your own Expo account, run `eas build:configure`, then build development, internal preview and production Android profiles. Test on physical Android devices, including declined contacts/location permissions, selected contacts, retry after network loss, expired tokens, day rollover, accessibility and dark mode. Generate signing keys through your approved signing process; upload the AAB to Google Play with accurate Data Safety declarations.

## iOS

Use your own Apple Developer account and unique bundle identifier. Use EAS Build or Xcode on macOS for provisioning and signing. Test selected/limited contacts access on supported iOS versions, denied foreground location, consent revocation and reinstall behavior. Review privacy manifests, App Privacy labels, deletion access and export compliance. Distribute to TestFlight before App Store review.

## Release gates

Use `scripts/backup.mjs` as a development encrypted-backup example with a securely injected QUIZ_BACKUP_PASSPHRASE; production should use managed encryption keys and automated backup schedules. Restore encrypted dumps only into an isolated database and validate counts and ledger constraints. The example uses authenticated AES-256-GCM and scrypt and caps development dumps at 256 MB; production should use scalable managed backups. Use `node scripts/backup.mjs <private backup directory>` and `node scripts/backup.mjs decrypt <encrypted file> <private SQL output>`. Decryption authenticates the complete archive before writing plaintext. Schedule backups with your deployment scheduler and test restore regularly.

Run feature tests on SQLite and MySQL, simultaneous answer submissions against MySQL, dependency audits, security and upload review, real mail delivery, backup restoration and physical-device tests. Configure monitoring for errors, scheduler failures, unusual reward issuance and retention failures. Do not advertise cash redemption: coins have no cash value.
