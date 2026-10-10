# Development and Windows setup

Install Git, Docker Desktop (WSL2 on Windows), Node 24 LTS and npm. macOS is required for local iOS builds. Use the existing cloud checkout; tasks are already isolated, so do not create additional Git worktrees unless explicitly requested.

1. At the repository root, copy `.env.example` to `.env`; choose two strong distinct local MySQL passwords.
2. Build PHP: `docker compose build api`.
3. Install PHP packages: `docker compose run --rm --no-deps api composer install --prefer-dist --no-interaction`.
4. Copy `backend/.env.example` to `backend/.env`. Keep `APP_ENV=local`; set `MAIL_MAILER=log` for local development only. Never commit this file.
5. Generate the application encryption key: `docker compose run --rm --no-deps api php artisan key:generate`.
6. Start MySQL: `docker compose up -d db`. Then `docker compose run --rm migrate`.
7. Enable public question images: `docker compose run --rm api php artisan storage:link`.
8. Create an administrator: `docker compose run --rm api php artisan admin:create your-email@example.com "Your Name"`. The password prompt is hidden; no default credentials are seeded.
9. Start API: `docker compose up -d api`. Start the scheduler and queue with `docker compose up -d scheduler worker`.
10. In `mobile/`, run `npm ci`, copy `.env.example` to `.env`, set the API URL reachable from your simulator or device, then `npx expo start`. Public Expo variables must never contain secrets. A native development build is required to validate permission plugins fully.

The API port binds to the development machine's loopback interface. Physical-device testing requires a deliberately configured HTTPS development endpoint or a private-network port binding; do not expose a development server on the public internet. Expo Go alone is insufficient to certify native permission behavior.

## Verification

- PHP: `docker compose run --rm api php artisan test` (isolated in-memory SQLite).
- TypeScript: from `mobile/`, `npm run typecheck`.
- Native JS bundle: `npx expo export --platform android` and `--platform ios` (bundling is not a signed device build).
- Runtime: fetch `/up`, register a disposable test account, follow its verification email, fetch daily assignments, answer once, then confirm a second answer returns 409 and balance does not change.
- MySQL integration tests must run in a separate `quiz_test` database, never your development or production data. Use `phpunit.mysql.xml` and grant test DB privileges only in development. The test bootstrap refuses to migrate any database except SQLite `:memory:` or MySQL `quiz_test`. Run `docker compose run --rm migrate php vendor/bin/phpunit -c phpunit.mysql.xml` after creating that isolated test database.

## Cloud proxy troubleshooting

Keep TLS and artifact verification enabled. This environment requires the platform CA bundle mounted read-only into Composer containers; set `COMPOSER_CAFILE` and `GIT_SSL_CAINFO` to that mounted bundle. If GitHub API archives are denied, Composer's supported `--prefer-source` mode works over Git. The API domain requirement is saved in the environment draft.

For image builds behind the proxy, pass `--build-arg HTTPS_PROXY --build-arg HTTP_PROXY`, `--secret id=system-ca,src=/etc/ssl/certs/ca-certificates.crt`, and an `--add-host proxy:<current resolved proxy IPv4>` if builder DNS cannot resolve the proxy. The optional build secret supplies trusted certificates; it does not disable verification. Do not hard-code a proxy address for future environments.

Linux shell example: `DOCKER_CONFIG=/tmp/quiz-docker docker build --add-host "proxy:$(getent ahostsv4 proxy | awk 'NR==1{print $1}')" --build-arg HTTPS_PROXY --build-arg HTTP_PROXY --secret id=system-ca,src=/etc/ssl/certs/ca-certificates.crt -f backend.Dockerfile -t quiz-php .`

Cloud setup and startup are executable in `scripts/cloud-install.sh` and `scripts/cloud-start.sh`. The installer preserves existing `.env` files, encryption keys and configured content. Initial sample seeding runs only for an empty question environment. Never dump environment files or logs containing reset and verification links.
