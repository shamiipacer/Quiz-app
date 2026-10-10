# Deploy to Hostinger

This guide targets Hostinger web hosting with PHP, MySQL, SSH and cron jobs. If your plan lacks SSH or permits no database triggers, use a VPS or upgrade; the immutable ledger requires triggers. The native Android app uses the same HTTPS backend. Hosting is not yet deployed and no hosting credentials are included.

## 1. Prepare the domain and hosting

In hPanel, attach your domain/subdomain (recommended: `chillbreeze.info`), enable its SSL certificate and force HTTPS. Select PHP 8.4 (8.3 also satisfies Laravel requirements). Enable PDO MySQL, mbstring, OpenSSL, XML/DOM, ctype, fileinfo, tokenizer, session, filter and zip extensions. Enable SSH if supported by your plan. Select the matching PHP binary for both SSH and cron; confirm `php -v` and `php -m`.

Create a MySQL database and user in hPanel and record the exact prefixed names and database host shown there. Do not reuse the administrator password as a database password.

## 2. Upload the prepared package

Download `dist/hostinger.zip` and upload/extract it into your domain directory, **outside** `public_html`. It contains `quiz-backend/`, `public_html/`, and this guide. Keep the default Hostinger public directory path when placing the two folders:

```
/home/HOSTINGER_USER/domains/chillbreeze.info/
  quiz-backend/         # application, vendor, .env, storage
  public_html/          # only public entrypoint and public assets
```

Move existing hosting files to a private backup first. Never upload Laravel's entire application into `public_html`. The prepared entrypoint assumes `quiz-backend` is a sibling of `public_html`. If hPanel supports setting a document root instead, point it to `quiz-backend/public` and use Laravel's original entrypoint there.

Connect over SSH, change into `quiz-backend`, copy `.env.hostinger.example` to `.env`, and edit its placeholders privately. Set `APP_URL` to your actual HTTPS backend origin, not `/api/v1`; fill in database and SMTP credentials. Keep optional sharing disabled until your policy and stated purpose are reviewed. Never commit `.env`.

```
cp .env.hostinger.example .env
chmod 600 .env
php artisan key:generate --force
php artisan migrate --force
php artisan quiz:seed-initial
php artisan admin:create admin@chillbreeze.info "Administrator"
```

The last command requests a password without echoing it. There is no default administrator password. Generate `APP_KEY` once on first deployment and retain it securely on updates: changing it prevents decryption of existing private records.

If migrations report insufficient privilege to create triggers, ask Hostinger whether this database user can create triggers under their MySQL configuration. Do not remove the ledger/audit protection to work around hosting restrictions. VPS deployment is the alternative.

## 3. Public images and application caches

For the split-directory layout above, run from `quiz-backend`:

```
ln -s ../quiz-backend/storage/app/public ../public_html/storage
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The symlink must resolve from `public_html` to the application storage directory. If hosting prohibits public symlinks, use a plan permitting them or configure a supported public storage provider; do not copy private storage into the web directory. Keep `storage` and `bootstrap/cache` writable by your PHP account (typically directories 755 or 775; never globally 777). For the alternative document-root layout use `php artisan storage:link` instead.

## 4. Cron and queues

Use hPanel Cron Jobs and your real absolute application path. Use the PHP binary verified above, which may have a versioned path on your plan.

Every minute:

```
php /home/HOSTINGER_USER/domains/chillbreeze.info/quiz-backend/artisan schedule:run >> /home/HOSTINGER_USER/quiz-scheduler.log 2>&1
```

Every minute, for shared hosting without persistent workers:

```
php /home/HOSTINGER_USER/domains/chillbreeze.info/quiz-backend/artisan queue:work --stop-when-empty --max-time=45 --tries=3 --timeout=30 >> /home/HOSTINGER_USER/quiz-queue.log 2>&1
```

Use `flock -n /home/HOSTINGER_USER/quiz-queue.lock` before the queue command if `flock` is available, to prevent overlapping workers. Respect Hostinger execution limits; use a VPS with supervised workers for larger traffic. Keep logs private and rotate them. Do not publish Laravel logs: they can contain authentication links.

## 5. Verify deployment

Visit `https://chillbreeze.info/up` and `/admin/login`. Register using the app or API; check email verification and password-reset delivery with your real SMTP configuration. Verify `/api/v1/questions/today` requires authentication, provides twenty seeded questions, and does not reveal correct answers. Answer once, check coins increase once, and submit again to confirm rejection. Test an uploaded question image. Confirm scheduler and queue execution in private logs and inspect failed jobs with `php artisan queue:failed`.

Verify `https://chillbreeze.info/.env` and `/composer.json` return 404/403 and never application files. Test two roles to confirm administrative restrictions. Before public release, complete the remaining checks in `release-status.md`, including device tests and the documented npm security findings.

## 6. Build and install the Android APK

In `mobile/.env`, set your actual addresses:

```
EXPO_PUBLIC_API_URL=https://chillbreeze.info/api/v1
EXPO_PUBLIC_POLICY_URL=https://chillbreeze.info/privacy
```

Publish your reviewed privacy policy at `https://chillbreeze.info/privacy` before distribution (for example `public_html/privacy/index.html`); that page is not supplied as a legally finalized policy. Templates are in `docs/privacy-template.md` and `docs/terms-template.md`.

These are public application configuration, never server credentials. They are embedded at build time; changing the backend address requires a new build. Set your permanent Android package ID in `app.json` before distributing publicly.

With Node.js 24 and an Expo account:

```
cd mobile
npm ci
npx eas-cli login
npx eas-cli build:configure
npx eas-cli build --platform android --profile preview
```

Confirm your Expo project association during configuration and retain your Android signing key. The preview profile explicitly produces a standalone, signed APK. Download it from the EAS build page and transfer it to your Android phone. Android may require allowing installation from that download source. Test login, verification, questions, rewards, logout, permission denial and explicit sharing on a physical device.

For Google Play use the production profile (AAB); store distribution requires your developer account, signing configuration, policy declarations and device validation. The hosting ZIP and JavaScript bundles are not APKs. A cloud build requires your Expo authentication; no account or signing credentials are present in this repository.

For a local test build, install Java 21 and a discoverable Java 17 toolchain, Android SDK 36, build-tools 35.0.0 and 36.0.0, NDK 27.1.12297006 and CMake 3.22.1; set `ANDROID_HOME` and run `scripts/build-test-apk.sh` from a Bash terminal. It builds an ARM64 standalone APK using the template test signing key. This is for sideload testing only; use an owned signing key/EAS for distribution. A build signed with a different key cannot update this test installation: uninstall the test app before installing your separately signed production app. It requires Android 7.0 or newer on an ARM64 device.

The current cloud APK attempt is blocked by HTTP 403 from the environment proxy for `repo.reactnative.dev`, the official React Native binary repository reached through Maven. Its domain addition is saved in the cloud environment draft; publish it in environment settings and retry the build in an environment using the updated network rules. No APK has been generated yet. EAS is an alternative after signing into your Expo account.

## 7. Backups and updates

Enable Hostinger database and file backups and confirm retention meets your needs. Back up the database, `.env`/`APP_KEY`, and storage privately with encryption and restricted access. Perform a restore test on a separate database. Do not put backups in `public_html`.

Before updating: back up, run `php artisan down`, upload application/dependencies while retaining `.env` and storage, run `php artisan migrate --force`, clear and rebuild caches, and run `php artisan up`. Restart persistent workers on a VPS. Do not re-create the database, replace APP_KEY, or overwrite stored user data.

## VPS alternative

Use the same Laravel application with PHP 8.4 FPM, MySQL, HTTPS Nginx with document root `quiz-backend/public`, a least-privilege runtime database user, a migration user allowed to create triggers, a once-per-minute scheduler cron, and supervised `queue:work` processes. Do not expose `php artisan serve`, Docker's development ports or MySQL to the public internet. See `deployment.md` for additional release requirements.
