# API v1

Base URL: `/api/v1`. Send `Accept: application/json`. Authenticated calls require `Authorization: Bearer <token>`. Passwords are never returned. Tokens expire after 30 days and are stored only as SHA-256 hashes on the server. Reauthenticate after expiration. Auth endpoints are limited to five requests per minute per IP; normal API traffic to sixty.

| Method | Path | Payload / result |
|---|---|---|
| POST | register | name, email, password, password_confirmation, terms=true, privacy=true, timezone (IANA); user + token |
| POST | login | email, password; user + token |
| POST | logout | Revokes current token; 204 |
| POST | password/forgot | email; generic acknowledgement |
| POST | password/reset | email, token, password, password_confirmation; revokes all API tokens |
| GET/PATCH | profile | User / update name, or request timezone and daily_schedule (HH:MM) changes effective after 24 hours and the active period |
| POST | account/deletion-request | Creates pending request; 202 |
| GET | categories | Active categories ordered by display order |
| GET | questions/today | data: array of daily assignments; requires verified email |
| GET | questions/{id} | Owned assignment; **id is assignment ID**, not question bank ID |
| POST | questions/{id}/answer | answer: string (option ID, or single-answer text); correctness, explanation, coins_awarded, balance |
| GET | coins/balance | balance (virtual coins) |
| GET | coins/transactions | Paginated ledger (30 per page) |
| GET | notifications | Paginated in-app announcements |
| GET | sharing/settings | enabled, disclosed purpose, policy version, retention days |
| POST | consents | purpose: contacts/location, policy_version, authorized_fields, granted=true |
| POST | contacts/share | contacts: [{name, phone}], at most 20 explicitly selected records |
| POST | location/share | latitude, longitude; server rounds to two decimal places |
| DELETE | contacts / location | Delete stored submissions and revoke consent; 204 |

Question responses contain `id`, `question_id`, `local_date`, `expires_at`, `question` and optional `result`. Initial responses never contain correct answers or explanations. Answers use snapshotted content even if an administrator changes the question later. A second submission returns 409; foreign assignment IDs return 404; expired assignments return 410; invalid option IDs return 422.

Daily periods begin at the user's stored daily schedule in their IANA timezone. Assignment creation is serialized on the user row and freezes even empty days. The periodic scheduler and lazy GET use the same service. A missed assignment expires at the next local period boundary, including DST transitions. Each category supplies up to its configured limit from eligible questions. Questions may recur for practice on different days, but a user earns question coins only once per question bank ID. The daily completion bonus remains available each day. Retries within an assignment never award additional coins.

## Question CSV

UTF-8 CSV header (exact order):

```csv
category_id,text,type,difficulty,options,correct_answer,explanation,coin_reward,publication_at,active
1,What is a synonym of happy?,multiple_choice,easy,Glad|Angry,Glad,Glad means happy.,10,2026-01-01 00:00:00,1
```

Options are separated by `|`; quoted CSV fields may contain commas. True/false uses `True|False`. Single-answer questions may leave options empty. Blank reward uses category defaults. Dates are UTC. Imports are limited to 500 rows / 2 MB and run in one transaction. A validation error rolls back the entire import.

## Provider-dependent features

Verification and reset mail use Laravel's configured mail transport. Development defaults to the log transport; production must use a real delivery provider. Reset links open a CSRF-protected web form; the mobile reset form also accepts a valid reset token. Announcements are persisted in-app. Optional queued Expo push is implemented but disabled by default. Configure EXPO_PUSH_ENABLED, EXPO_PROJECT_ID and, when enforcing Expo push security, EXPO_PUSH_ACCESS_TOKEN. Real delivery requires device testing and network access to exp.host. Provider responses are covered by mocked tests, which do not prove device delivery.
