# Administrator guide

Sign in at `/admin/login` using an account created through `admin:create`. Seeders create roles, permissions and educational sample content; they never create credentials.

- Super Admin: all configured permissions.
- Question Manager: categories and questions.
- Support Manager: user status management.
- Privacy/Data Manager: consent-linked contact/location access and deletion processing.
- Read-only Analyst: reports.

Only users with `privacy.manage` can see private submissions. Every sensitive listing, record view and deletion is audited; decrypted views use no-store caching. No bulk private-data export endpoint exists. Account deletion removes profile, login history, tokens and private submissions and anonymizes the user; anonymous quiz/reward history remains to preserve the ledger. Production policy must disclose this retention choice and establish a legal retention duration.

Create categories with daily limits and rewards. Deactivate categories containing questions rather than deleting them. Question forms support multiple-choice, true/false and single-answer questions, UTC publication windows, explanations and reward overrides. Upload JPEG/PNG/WebP images separately. Question edits affect future assignments; existing assignment snapshots preserve their original content and grading.

Reward adjustments require a reason, a unique reference, a permitted administrator and sufficient balance. The ledger cannot be edited or deleted through SQL UPDATE/DELETE triggers. Corrections use a new compensating transaction. Daily completion bonus settings apply at the time of completion.

Run the scheduler continuously in development and every minute through cron in production. No background location or automatic contact uploads occur. Sharing is disabled by default; enable only after configuring a legitimate purpose and approved policy.

Manual assignment replacement accepts an unanswered, unexpired assignment ID, a replacement question ID in the same active category and a required reason; the change is audited. Revisions are available from each question. Current operational limits: advanced abuse analytics and admin role-editor UI are still pending. Basic reports flag unusually large reward entries for review; a flag is not proof of abuse. Expo push integration is implemented but requires project configuration and device validation. The seeded roles are configured through database seeders. Do not give production staff direct unrestricted database access as a substitute for these features.
