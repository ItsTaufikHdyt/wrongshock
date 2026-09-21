# STATUS --- Wrongshock Core Transaction Stabilization

## Overall

**Current status: P1.6 COMPLETE / M8 COMPLETE**

This document tracks implementation against `prd.md`. It must be updated
after every meaningful coding session.

## Source Baseline

Current source state: - Deposit creation and multi-item total calculation are
implemented through `DepositService`. - Balance credit on deposit post is
ledger-backed and atomic. - Posted deposit correction uses controlled
cancellation/reversal. - Ledger-backed withdrawal request, approval, and
rejection are implemented through `WithdrawalService`. - Price/unit/category
snapshots are persisted for posted deposits. - Reconciliation and opening
balance migration are implemented through `BalanceReconciliationService` and
`finance:reconcile`. - Canonical unit is kg and quantity precision is
decimal(12,3). - Domain coverage exists for deposit, cancellation, withdrawal,
 reconciliation, and authorization flows. - M8 documentation and cleanup are
 complete.

Current verified development DB: 2 users, cached balance total 2,017,800, 5
ledger entries, 2 opening-balance entries, ledger net 2,017,800, 2 deposits,
4 deposit items, and 0 withdrawals. Reconciliation reports MATCH for both
users.

## Milestones

### M0 --- Safety Baseline

Status: COMPLETE - \[x\] Create/verify DB backup procedure before schema/data
changes. Backup was created and validated before analysis.
- \[x\] Confirm effective production/development DB engine. Laravel runtime
uses MySQL and database `wrongshock`; server version is MySQL 8.0.46.
- \[x\] Inspect existing data volume and anomalies. Read-only inspection found
2 users, no deposits/items/withdrawals, and no invalid financial rows.
- \[x\] Document current balances vs deposits. Both users have a balance of
1,000,000 and no deposit history.
- \[x\] Resolve whether seeded/manual balances need opening-balance ledger
entries. Both users are migration candidates requiring an explicit opening
balance policy; no ledger or balance was changed.
- \[x\] Establish baseline tests for current critical flow where practical.
The existing suite ran successfully; no dedicated financial tests exist yet
and no business behavior was changed in M0.

## M0 Source Findings

- Actual source confirms `WasteDepositObserver::created()` directly increments
  `users.balance`; update/delete handlers are empty.
- `WasteDepositResource` calculates subtotal/total in form callbacks and still
  exposes edit/delete actions.
- `WasteDepositItem` fillable uses `deposit_id`, but the migration column and
  foreign key are `waste_deposit_id`.
- `WasteItem` accessors reference nonexistent `unit_price`, `name`, and
  `description` fields; the schema uses `price` and `category`.
- `Withdrawal` migration defines `pending/approved/rejected`, while the model
  class is named `Withdrawals` and its completed scope uses an undefined
  `completed` status.
- `db.env` is tracked in Git despite being listed in `.gitignore`; its content
  was not read or exposed.

## M0 Safety Analysis

### Database and Backup

- Docker Compose declares `wrongshock_mysql` using `mysql:8.0`, localhost-only
  port binding, and a persistent `.docker/db/data` volume. No container is
  currently running.
- The repository has a MySQL data directory and `wrongshock` schema directory,
  so existing data is possible, but row/table contents were not inspected.
- Before schema work, with the DB service running and credentials supplied via
  the environment, use a read-only dump command such as:
  `docker compose exec -T db mysqldump --single-transaction --routines
  --triggers "$MYSQL_DATABASE" > backups/wrongshock_YYYYMMDD_HHMMSS.sql`.
  Verify the dump exit status and file before proceeding. Do not print the
  credential or commit the dump. `backups/` is now ignored.

### Financial and Unit Analysis

- No read-only SQL analysis was run because the database could not be safely
  connected to. Counts, sums, reconciliation, orphan checks, invalid values,
  unit distribution, and withdrawal state distribution remain unknown.
- Seeder source uses only `Kilogram (Kg)` and integer `quantity` storage is
  used by the migration. Actual master/transaction unit data is unverified.
  Fractional quantity need is therefore unresolved; do not change schema in
  M0.
- Existing seeded balances are candidates for explicit opening-balance
  treatment. Do not backfill or alter them until runtime reconciliation and an
  approved policy are available.

### Cascade Dependency Map

- `districts` -> `sub_districts` (`CASCADE`) and `users` (`CASCADE` via direct
  foreign key).
- `sub_districts` -> `users` (`CASCADE`).
- `users` -> `waste_deposits` (`CASCADE`) and `withdrawals` (`CASCADE`).
- `waste_deposits` -> `waste_deposit_items` (`CASCADE`).
- `waste_items` -> `waste_deposit_items` (`CASCADE`).
- The user, deposit, deposit-item, withdrawal, and waste-item cascades can
  destroy financial history; no schema changes were made in M0.

### Security and Mutation Baseline

- Direct balance mutation found: `WasteDepositObserver` increments balance;
  `User.balance` is mass assignable and visible in admin/user resources.
- Admin and user panels use Spatie role middleware. Custom login applies
  `FilamentUser::canAccessPanel()` when implemented, checks `status == 1`,
  rate-limits login, and regenerates the session.
- No `canAccessPanel()` implementation was found on `User`; panel contract
  behavior therefore remains a security issue for later hardening.
- Seed source contains known development credentials and 1,000,000 manual
  balances; credential values are intentionally omitted from this record.
- `db.env` is tracked despite its ignore rule and requires repository hygiene
  before production use. No dependency was upgraded; locked versions include
  Laravel 12.20.0, Filament 3.3.31, Livewire 3.6.3, and Spatie Permission
  6.20.0.

### Current Financial Mutation Flow

Filament Create -> `DepositService::post()` validates input and resolves
masters -> `DB::transaction` creates a posted deposit, item snapshots, one
`deposit_credit` ledger entry, and atomically increments the locked user's
cached balance. UI subtotal/total remain preview-only. The former
`WasteDepositObserver` registration and financial mutation were removed.
`DepositService::cancel()` locks the deposit and owner, validates the original
credit, creates one reversal debit, decrements cached balance, and marks the
deposit cancelled atomically. `WithdrawalService` owns request, approval, and
rejection; only approval creates a debit and decrements cached balance.

### M1 --- Schema & Model Consistency

Status: COMPLETE - \[x\] Fix `WasteDepositItem` foreign-key/fillable
mismatch. - \[x\] Fix `WasteItem` accessors referencing nonexistent
fields. - \[x\] Fix `Withdrawal` class/schema/fillable/status
inconsistencies. - \[x\] Decide canonical unit and decimal precision. -
\[x\] Change quantity storage to appropriate decimal representation. -
\[x\] Add deposit status/audit fields. - \[x\] Add deposit item snapshot
fields. - \[x\] Add ledger table/model. - \[x\] Review destructive
cascade FKs. - \[x\] Add appropriate uniqueness/idempotency constraints.

### M2 --- Transaction Domain Service

Status: COMPLETE - \[x\] Introduce authoritative deposit posting
service/action. - \[x\] Move subtotal/total calculation authority
server-side. - \[x\] Wrap header + items + ledger + cached balance in
`DB::transaction`. - \[x\] Remove create-only balance authority from
observer. - \[x\] Prevent direct generic balance edits in the deposit flow. -
\[x\] Add idempotent ledger creation per posted deposit.

### M3 --- Cancellation & Correction

Status: COMPLETE - \[x\] Disable hard delete for posted deposits. - \[x\]
Implement controlled cancellation. - \[x\] Require cancellation reason. -
\[x\] Create reversal ledger entry. - \[x\] Make reversal idempotent. -
\[x\] Define correction workflow. - \[x\] Ensure changing deposit owner
cannot corrupt balances.

### M4 --- Withdrawal

Status: COMPLETE - \[x\] Authoritative `WithdrawalService`. - \[x\]
Request workflow. - \[x\] Approval workflow. - \[x\] Rejection workflow. -
\[x\] Approval balance recheck. - \[x\] `withdrawal_debit` ledger. -
\[x\] Cached balance decrement. - \[x\] Approval idempotency. - \[x\]
Concurrency protection through withdrawal/user row locks. - \[x\] Immutable
financial history. - \[x\] Filament integration. - \[x\] Domain tests.

### M5 --- Reconciliation & Migration

Status: COMPLETE - \[x\] Reconciliation service. - \[x\] Ledger-derived
balance calculation using ledger direction. - \[x\] Mismatch report. - \[x\]
Opening balance candidate detection. - \[x\] Controlled opening balance
migration. - \[x\] Opening balance idempotency. - \[x\] Explicit cached-balance
repair from non-negative ledger totals. - \[x\] Integration financial
invariant test. - \[x\] Development dry-run. - \[x\] Development opening
balance migration. - \[x\] Post-migration verification. - \[x\] Second-run
idempotency verification. - \[x\] Seeder corrected to avoid new manual
balances. - \[x\] Historical snapshot tests remain passing.

### M6 --- Authorization & Integrity Hardening

Status: COMPLETE - \[x\] Verify role enforcement across Livewire requests. -
\[x\] Verify inactive users cannot retain unauthorized access. - \[x\]
Resolve Filament panel access contract/configuration. - \[x\] Ensure
members are owner-scoped. - \[x\] Ensure only authorized actors can
post/cancel/approve. - \[x\] Remove/guard unsafe resource actions.

### M7 --- Tests

Status: COMPLETE. Deposit posting, snapshot, fractional unit, idempotency,
cancellation, rollback, withdrawal, authorization, reconciliation, and full
suite tests all pass.

### M7.5 --- Dependency Security Remediation

Status: COMPLETE. Composer security advisories were remediated through
targeted, current-major updates. No financial source code or development
financial data was changed.

### M8 --- Documentation & Cleanup

Status: COMPLETE. README now documents the current Laravel/PHP/runtime stack,
Docker setup, transaction lifecycle, ledger semantics, reconciliation command,
frontend dependency limitation, and known risks. The existing code review
confirmed that the obsolete observer balance mutation and unsafe financial form
paths were already removed in earlier milestones; no additional cleanup was
needed.

### P1.1 --- User Design Foundation

Status: COMPLETE. User panel branding now uses Wrongshock and the approved
green primary palette. The custom user dashboard has a responsive surface/card
foundation, consistent Rupiah presentation, accessible table headings, and
textual deposit status badges. The misleading hardcoded gold balance was
removed without changing financial data or services.

### P1.2 --- User Navigation

Status: COMPLETE. The user panel now uses the custom Beranda as its only
effective dashboard destination. The default Filament dashboard and technical
widgets were removed from the user panel. User profile navigation is labeled
Profil without an admin-style navigation group; authentication, role checks,
and profile ownership scoping remain unchanged.

### P1.3 --- User Dashboard Redesign

Status: COMPLETE. The user Beranda now uses a mobile-first consumer layout
with greeting, member number, balance hero, safe profile/recent-deposit actions,
and one recent-deposit card list. Deposit presentation uses historical snapshot
fields with legacy fallbacks, readable Rupiah/quantity/date formatting, textual
status labels, cancelled-state messaging, and a friendly empty state. No
financial services, ledger semantics, authorization rules, or development
financial data were changed.

### P1.4 --- User Deposit History

Status: COMPLETE. Added read-only user-facing list and detail pages for deposit
history at `/user/setoran` and `/user/setoran/{depositId}`. History is scoped to
the authenticated user, paginated, newest-first, filterable by status, and uses
historical snapshots with safe legacy fallbacks. Deposit creation/edit/delete/
cancellation actions are not exposed. Dashboard navigation now links to the
dedicated history page; no financial service, ledger semantics, authorization
rules, or development financial data were changed.

### P1.4.1 --- User Visual Refresh & Contrast Fix

Status: COMPLETE. Refined the user-facing dashboard, deposit history, and
deposit detail pages with the approved cheerful eco-finance palette, stronger
contrast, wider desktop content, richer cards, clearer status badges, friendly
empty states, and CSS-only decorative elements. The user panel navigation and
admin panel behavior remain unchanged. No business logic, financial data,
domain service, ledger, balance, or database schema was changed.

### P1.4.2 --- Reference-Matched User UI Rebuild

Status: COMPLETE / VISUAL APPROVED. Rebuilt the user panel shell and
user-facing pages around the approved desktop composition: a 248px branded
sidebar, 80px identity topbar, wide content canvas, local illustrated eco
heroes, truthful history summary
cards, structured transaction cards, and environmental footer banners. Custom
shell CSS is loaded only by `userPanel`, so the admin panel is unaffected. No
domain service, authorization, financial semantics, schema, or persisted data
was changed.

### P1.6 --- User Profile Redesign

Status: COMPLETE. Replaced the user panel's one-record CRUD resource with a
direct `/user/profile` page using the approved P1.4.2 shell and card system.
Users can update their name, unique email, dependent district/subdistrict,
address, optional confirmed password, and optional profile photo. The update
boundary explicitly excludes member number, balance, status, roles, and all
financial fields. Profile photos use the public disk under a per-user directory
with JPEG/PNG/WebP and 2 MB limits; owned replaced files are removed after
commit while legacy paths are preserved safely. No financial data, domain
service, schema, or admin panel was changed.

## Critical Rules During Implementation

-   Never "fix" historical financial data by deleting records.
-   Never directly increment/decrement balance outside the approved
    ledger/balance service.
-   Never trust subtotal/total sent by UI.
-   Never recalculate historical posted transactions from current master
    price.
-   Never hard-delete a posted financial transaction.
-   Never backfill data by assumption; flag unverifiable opening
    balances.
-   Every schema/data migration must be reviewed for reversibility and
    data preservation.

## Blockers / Decisions Needed

-   [x] Canonical weight unit: kg.
-   [x] Quantity precision: `DECIMAL(12,3)`.
-   [x] `users.balance` remains a cached balance; ledger is the future source
    of truth.
-   [x] Exact correction workflow: cancel + replacement.
-   [x] Current seeded/manual balances were approved as opening-balance
    candidates and represented by M5 opening ledger entries.
-   [x] Initial audited baseline had no deposits; current verified development
    data is documented in the source baseline above and remains read-only.

## Current Known Risks

1.  Cancellation is blocked when cached balance is below the reversal amount.
2.  Pending withdrawals do not reserve balance; approval rechecks the cached
    balance under the user row lock.
3.  Request-level idempotency keys are not implemented.
4.  Future ledger types require the same direction-based reconciliation rules.

## Session Log

Add entries in reverse chronological order.

### 2026-09-21 — P1.6 User Profile Redesign

- Added direct authenticated `/user/profile` architecture based on Filament's
  transaction-aware profile page; removed the user-facing one-record resource
  and retained its two URLs as authenticated redirects.
- Added the approved Wrongshock visual language to the profile hero, identity,
  initials/photo avatar, personal data, address, security, and save areas.
- Added optional profile upload with preview, public per-user storage,
  JPEG/PNG/WebP allow-list, 2 MB limit, and after-commit cleanup restricted to
  files owned by that user's profile directory.
- Added explicit writable-field allow-list, owner policy authorization, unique
  email validation, dependent location controls and server-side pairing rule,
  optional confirmed password hashing, and friendly success feedback.
- Focused P1.6 coverage: 10 tests passed, 74 assertions. Full regression: 98
  tests passed, 451 assertions. Composer audit: 0 advisories.
- Development financial baseline remained unchanged at 2 users, cached and
  ledger net 2,017,800, 5 ledger entries, 2 deposits, 4 deposit items, and 0
  withdrawals; reconciliation remained 2 MATCH and 0 MISMATCH.
- Browser desktop/mobile verification was unavailable and was not claimed.

### 2026-09-21 — P1.4.2 Reference-Matched User UI Rebuild

- Added a panel-scoped Wrongshock shell with consumer sidebar branding,
  high-contrast active navigation, page-aware topbar titles, member identity,
  and a lower environmental message.
- Added a lightweight local eco-community SVG and used it in large responsive
  heroes on Beranda and Riwayat Setoran; no external image or package was added.
- Rebuilt Beranda around the illustrated greeting, richer balance composition,
  quick actions, structured recent transactions, and environmental banner.
- Rebuilt Riwayat Setoran with posted-only count/value summaries, a truthful
  cancelled count, segmented filters, reference-style date blocks, item rows,
  detail actions, totals, and cancellation notices.
- Rebuilt Setoran Detail with the same shell, summary, item hierarchy, total,
  cancellation treatment, and environmental banner.
- All presentation CSS is loaded only through `UserPanelPanelProvider`; admin
  styling and behavior were not modified.
- Focused user-panel regression: 16 tests passed, 84 assertions. Browser visual
  match was not verified because browser tooling was unavailable.
- Full regression: 88 tests passed, 377 assertions. Composer audit: 0
  advisories. Post-change reconciliation remained 2 MATCH and 0 MISMATCH; all
  verified financial counts and totals remained unchanged.

### 2026-09-21 — P1.4 User Deposit History

- Added `DepositHistory` at `/user/setoran` with ten-record pagination, newest-
  first ordering, and simple Semua/Berhasil/Dibatalkan/Draft filters.
- Added read-only `DepositDetail` at `/user/setoran/{depositId}` with ownership-
  scoped lookup, historical item details, totals, status, and cancellation
  reason messaging.
- Reused a small `UserDepositPage` presentation/query foundation across
  Beranda, list, and detail pages to keep money, date, quantity, status, and
  snapshot fallback display consistent.
- Updated Beranda's `Lihat riwayat` action to use the dedicated history route.
- Added focused history tests for ownership, pagination, filtering, snapshots,
  deleted master items, cancellation, empty states, detail access, and
  non-mutation. Focused tests passed: 7 tests, 44 assertions.
- Full regression: 88 tests passed, 377 assertions. Composer audit: 0
  advisories. Development financial data remained read-only.

### 2026-09-21 — P1.4.1 User Visual Refresh & Contrast Fix

- Refreshed Beranda with a high-contrast green balance hero, friendly greeting,
  action cards, richer recent-deposit cards, and a clearer empty state.
- Refreshed Riwayat Setoran with a lightweight CSS-only hero, accessible status
  pills, cleaner transaction cards, and safer pagination overflow handling.
- Refreshed Setoran Detail with matching summary, item, total, and cancellation
  surfaces; status colors now use dark text on light backgrounds.
- Standardized posted, cancelled, and draft badge colors through the existing
  presentation helper without changing status semantics or queries.
- Focused user-panel tests passed: 16 tests, 84 assertions. Full regression
  passed: 88 tests, 377 assertions. Composer audit: 0 advisories.
- Browser verification was not available; desktop/mobile visual inspection is
  still required before P1.6.

### 2026-09-21 — P1.3 User Dashboard Redesign

- Replaced the overlapping transaction/activity tables with one mobile-first
  `Setoran Terbaru` card list limited to five deposits.
- Kept dashboard data scoped to the authenticated user and changed the query to
  one eager-loaded deposit collection with only required item fields.
- Displayed historical name, category, unit, price, quantity, and subtotal
  snapshots before legacy master-data fallbacks; current master price is never
  used for historical presentation.
- Added Indonesian-friendly compact dates, canonical three-decimal quantity
  display, consistent Rupiah formatting, and explicit posted/cancelled/draft
  wording.
- Added cancelled-deposit reason messaging without presenting the amount as
  active income. Added safe handling for empty deposits, missing items, missing
  snapshots, and deleted master relations.
- Added only valid quick actions: recent-deposit anchor and authenticated
  user's profile URL. No P1.4 history page or future feature was added.
- Focused dashboard tests: 6 passed, 31 assertions. Full suite: 81 passed,
  333 assertions. Composer audit: 0 advisories.
- Development financial data remained read-only during the redesign.

### 2026-09-21 — P1.1/P1.2 User Foundation & Navigation

- Replaced the user panel's default Filament dashboard with the existing custom
  dashboard as the single home destination from `/user`.
- Removed user-panel `AccountWidget` and `FilamentInfoWidget` registration;
  admin panel widgets were unchanged.
- Added Wrongshock user-panel branding and `#2F7D5A`-based Filament primary
  colors.
- Removed the misleading hardcoded gold balance calculation and presentation.
- Removed fixed square dashboard cards and the unlabelled chart presentation;
  retained the existing deposit/activity data without changing financial
  semantics.
- Added consistent `Rp1.000.000` presentation, responsive table wrappers,
  snapshot-first item labels, textual deposit status badges, semantic table
  headings, and accessible image alt text.
- Relabeled the user resource to Profil and removed its empty create action;
  ownership query remains restricted to the authenticated user.
- Added three navigation/access regression tests. Focused tests passed: 3
  tests, 9 assertions. No financial service or database data was changed.

### 2026-09-21 — M8 Documentation & Cleanup

- Replaced stale Laravel 10/PHP 8.1 README claims with the current Laravel 12,
  PHP 8.2, Filament 3, Livewire 3, MySQL 8, and SQLite test setup.
- Documented deposit posting/cancellation, withdrawal approval/rejection,
  append-only ledger behavior, reconciliation, Docker ports, and credential
  handling.
- Documented the absence of an npm lockfile and the known transaction
  limitations without adding frontend dependencies or changing financial data.
- Baseline validation: 72 tests passed, 293 assertions, 0 failed, 0 skipped;
  Composer audit reported 0 advisories.
- Development database remained unchanged: 2 users, cached balance 2,000,000,
  2 opening-balance ledger entries, ledger net 2,000,000, zero deposits,
  deposit items, and withdrawals.

### 2026-09-21 — M7.5 Dependency Security Remediation

- Baseline: 72 tests passed, 293 assertions, 0 failed, 0 skipped. Development
  DB matched the expected M7 state before Composer changes.
- Composer audit before: 52 advisories across 18 packages: 1 critical,
  14 high, 31 medium, 5 low, and 1 advisory without a severity value.
- Targeted UI update: `filament/filament` 3.3.31 -> 3.3.55 and
  `livewire/livewire` 3.6.3 -> 3.8.9. The resolver also updated compatible
  Laravel, Symfony, Guzzle, CommonMark, and supporting packages within the
  existing Laravel 12 / Filament 3 / Livewire 3 majors.
- Key production updates: Laravel 12.20.0 -> 12.69.2; Guzzle 7.9.3 ->
  7.15.5; Guzzle PSR-7 2.7.1 -> 2.13.1; CommonMark 2.7.0 -> 2.10.1;
  Symfony 7.3.x -> 7.4.x.
- Dev updates: PHPUnit 11.5.25 -> 11.5.56; PsySH 0.12.9 -> 0.12.24;
  Symfony YAML 7.3.1 -> 7.4.18.
- Composer audit after: 0 advisories. Both plain and JSON audit commands
  passed.
- Composer validation: `composer.json` is valid.
- Regression: 72 tests passed, 293 assertions, 0 failed, 0 skipped after
  each dependency group.
- Filament/Livewire verification: both panels boot; admin and user login
  endpoints returned HTTP 200; resource classes and deposit-create page load;
  route registration includes deposit, cancellation-related resource paths,
  and withdrawal resource paths. No browser/manual verification was claimed.
- Development DB before and after: 2 users, cached balance 2,000,000, 2
  ledger entries, 2 opening balances, ledger net 2,000,000, 2 MATCH,
  0 MISMATCH, 0 deposits, 0 items, and 0 withdrawals.
- Migration status was unchanged; no package migrations were published or
  run. Existing Vite build could not run because the container has no
  `node_modules/.bin/vite`; npm dependencies were not changed.
- `composer update` was targeted to the UI stack and then the remaining
  dev/security packages; unrestricted update was not used.
- Financial data changed: NO. M8 remains intentionally not started.
- M8 readiness: YES from the dependency-security gate; frontend build setup
  and documentation/cleanup remain M8 work and were not started here.

### 2026-09-21 — M7 Final Core Validation

- Status before: M6 COMPLETE / M7 prematurely marked complete.
- Added explicit full lifecycle coverage: opening balance, deposit, cancel,
  replacement, pending withdrawal, approval, and per-operation reconciliation.
- Added rejected-withdrawal reconciliation coverage, revoked-admin zero-mutation
  coverage, and quantity boundary coverage for 0.001, 0.250, 1.125, and
  999.999 kg.
- Full suite: 72 passed, 293 assertions, 0 failed, 0 skipped.
- Development database read-only verification remained unchanged: 2 users,
  cached total 2,000,000, 2 ledger entries, 2 opening balances, ledger net
  2,000,000, 2 MATCH, 0 MISMATCH, 0 deposits, 0 items, and 0 withdrawals.
- Concurrency was reviewed but no unreliable fake concurrent test was added;
  withdrawal and cancellation paths retain row-lock guarantees from M4/M3.
- Composer audit advisories were captured without dependency changes. A
  critical Livewire advisory affects the installed 3.6.3 version.
- M7 core validation is complete. M8 remains intentionally not started.
- Ready for M8: NO until the Livewire critical advisory and the remaining
  framework dependency advisories are remediated or explicitly accepted.

### 2026-09-21 — M6 Authorization & Integrity Hardening

- Status before: M5 COMPLETE / M6 NOT STARTED.
- Added active panel access checks, role middleware, and inactive-user session
  enforcement.
- Added owner-scoped policies and restricted financial service mutations to
  active admin actors.
- Guarded transaction model mass assignment, scoped the user resource, and
  hardened registration validation and throttling.
- Added 14 authorization tests covering panel access, ownership, forged IDs,
  unauthorized financial actions, registration, and balance protection.
- Full suite: 69 passed, 245 assertions, 0 failed, 0 skipped.
- `composer audit` still reports dependency advisories; upgrades remain a
  separate task.
- Pint check on the app/test tree reports 40 pre-existing and touched-file
  style issues; the new authorization test itself is clean.

### 2026-09-21 — M5 Reconciliation & Migration

- Status before: M4 COMPLETE / M5 NOT STARTED.
- Backup: `backups/wrongshock_before_m5_20260921_132700.sql`, 27,547 bytes,
  non-empty and ignored by Git.
- Pre-migration reconciliation: User 1 and User 2 each had cached balance
  1,000,000, ledger net 0, and difference 1,000,000; both were classified as
  `OPENING_BALANCE_CANDIDATE`. There were no deposits, items, withdrawals, or
  ledger entries.
- Services added: `BalanceReconciliationService` with ledger-derived report,
  conservative candidate detection, idempotent opening creation, and explicit
  non-negative ledger-backed cache repair.
- Commands added: `finance:reconcile` is read-only by default; mutation
  requires `--create-opening-balances` or `--repair-cache`.
- Tests added: 12 M5 tests covering match, candidate detection, zero balance,
  history review, read-only reporting, idempotency, repair safety, negative
  ledger handling, and the full deposit/cancellation/withdrawal invariant.
- Tests run: `docker compose exec -T app php artisan test
  tests/Feature/BalanceReconciliationTest.php`; `docker compose exec -T app
  php artisan test`.
- Test result: Targeted 12 passed, 34 assertions; full suite 55 passed, 191
  assertions; 0 failed, 0 skipped. Tests remain isolated on SQLite `:memory:`.
- Dry run result: Exactly two development candidates, User 1 and User 2;
  cached total 2,000,000 and ledger entries 0 before migration.
- Migration executed: `php artisan finance:reconcile
  --create-opening-balances`.
- Opening balances created: 2 entries totaling 2,000,000. Cached balance
  changed: NO; existing `users.balance` values remained 1,000,000 each.
- Post-migration verification: 2 users; cached total 2,000,000; 0 deposits;
  0 deposit items; 0 withdrawals; 2 ledger entries; 2 opening balance
  entries totaling 2,000,000. Both users reconcile as `MATCH` with difference 0.
- Idempotency verification: Second migration run created 0 entries, skipped 2;
  ledger count remained 2 and cached total remained 2,000,000.
- Seeder changes: `UserSeeder` now creates new users with balance 0; it was
  not rerun against development data.
- Files changed: `app/Services/BalanceReconciliationService.php`,
  `app/Console/Commands/ReconcileFinance.php`,
  `database/seeders/UserSeeder.php`, `tests/Feature/BalanceReconciliationTest.php`,
  `status.md`.
- Known limitations: M6 authorization hardening, request-level idempotency,
  future ledger-type policy, and broader reconciliation UI remain.
- Next recommended task: M6 — Authorization & Integrity Hardening.

### 2026-09-21 — M4 Withdrawal

- Status before: M3 COMPLETE / M4 NOT STARTED.
- Work completed: Added `WithdrawalService` as the only withdrawal lifecycle
  authority, protected submitted withdrawal deletion, and added an admin
  Filament resource for request, approval, rejection, and history.
- Files changed: `app/Services/WithdrawalService.php`,
  `app/Models/Withdrawal.php`, `app/Filament/Resources/WithdrawalResource.php`,
  `app/Filament/Resources/WithdrawalResource/Pages/ListWithdrawals.php`,
  `app/Filament/Resources/WithdrawalResource/Pages/CreateWithdrawal.php`,
  `tests/Feature/WithdrawalServiceTest.php`, `status.md`.
- Service added: `WithdrawalService::request()`, `approve()`, and `reject()`.
- Request behavior: Validates positive integer Rupiah amounts and current
  cached balance, creates `pending`, and never mutates balance or ledger.
- Approval behavior: Locks withdrawal then user, rechecks cached balance,
  creates one debit, decrements cached balance, and marks `approved` atomically.
- Rejection behavior: Only `pending` requests can be rejected; reason and
  processed date are stored without financial mutation.
- Ledger behavior: Approval creates exactly one `withdrawal_debit` with the
  withdrawal polymorphic reference and approval actor; no ledger is created by
  request or rejection. `withdrawal_reversal` remains future scope.
- Balance validation: M4 uses `users.balance` as the available cached balance;
  pending requests do not reserve it. Ledger-only reconciliation remains M5.
- Concurrency: Consistent withdrawal-then-user row locks serialize approvals
  for the same user and prevent negative cached balances.
- Idempotency: Explicit status and existing-debit checks reject repeated
  approval/rejection instead of relying only on the unique ledger constraint.
- Filament changes: Admin-only withdrawal list/create resource with pending
  approve/reject actions, confirmation, rejection reason, and processed dates.
  Financial callbacks call the service only.
- Authorization: Admin panel role middleware plus explicit admin checks on
  actions; broader authorization hardening remains M6.
- Tests added: 17 withdrawal domain tests covering request validation,
  approval/rejection lifecycle, balance recheck, multiple pending requests,
  rollback, integer money, idempotency, and delete protection.
- Tests run: `docker compose exec -T app php artisan test
  tests/Feature/WithdrawalServiceTest.php`; `docker compose exec -T app php
  artisan test`.
- Test result: Targeted 17 passed, 46 assertions; full suite 43 passed, 157
  assertions; 0 failed, 0 skipped. Tests use isolated SQLite `:memory:`.
- Development DB verification: 2 users; total balance 2,000,000; 0 deposits;
  0 deposit items; 0 withdrawals; 0 ledger entries.
- Financial data changed: NO. No M5 opening-balance entries or reconciliation
  writes were performed.
- Known limitations: No pending-balance reservation, request-level
  idempotency key, withdrawal reversal, member withdrawal statement UI, or
  full authorization hardening.
- Next recommended task: M5 — Reconciliation & Migration.

### 2026-09-21 — M3 Cancellation & Correction

- Status before: M2 COMPLETE / M3 NOT STARTED.
- Work completed: Added transactional `DepositService::cancel()`, required
  reasons, original-credit validation, owner/balance locking, reversal ledger
  entries, cached-balance decrement, cancelled status, and hard-delete guards.
- Correction workflow: cancel the posted deposit, then post a replacement;
  original item snapshots and ledger history remain immutable.
- Filament changes: Added an admin-only cancellation action with required
  reason, confirmation, and cancelled-history display.
- Financial invariants: Cancellation is single-use, rejects drafts and missing
  or inconsistent credits, rejects insufficient cached balance, and rolls back
  all changes on failure.
- Test safety: PHPUnit env overrides now force SQLite `:memory:` despite the
  Docker app's `APP_ENV=local`; a regression test asserts the SQLite driver and
  in-memory database.
- Tests run: `docker compose exec -T app php artisan test`.
- Test result: 26 passed, 111 assertions, 0 failed, 0 skipped.
- Development DB verification: 2 users; total balance 2,000,000; 0 deposits;
  0 deposit items; 0 withdrawals; 0 ledger entries. No net financial data
  changed; temporary test artifacts from the earlier unsafe run were removed
  and the original balances restored.
- Known limitations: Withdrawal approval, opening-balance migration,
  reconciliation, request-level idempotency, and authorization hardening
  remain for later milestones.
- Next recommended task: M4 — Withdrawal.

### 2026-09-21 — M2 Transaction Domain Service

- Status before: M1 COMPLETE / M2 NOT STARTED.
- Work completed: Added `DepositService::post()` as the sole application
  posting path, moved authoritative validation/calculation into the service,
  added atomic deposit/item/ledger/cached-balance persistence, removed the
  observer financial mutation, integrated Filament create handling, and blocked
  posted edit/delete through the resource/model path.
- Files changed: `app/Services/DepositService.php`,
  `app/Providers/AppServiceProvider.php`, deleted
  `app/Observers/WasteDepositObserver.php`, `app/Models/WasteDeposit.php`,
  `app/Filament/Resources/WasteDepositResource.php`,
  `app/Filament/Resources/WasteDepositResource/Pages/CreateWasteDeposit.php`,
  `app/Filament/Resources/WasteDepositResource/Pages/EditWasteDeposit.php`,
  `tests/Feature/DepositServiceTest.php`, `status.md`.
- Services added: `DepositService::post()`.
- Observer changes: Financial observer removed and unregistered; repository
  search found only the service's balance increment.
- Filament changes: Repeater no longer auto-persists relationships; create
  delegates to the service. Preview values are ignored as financial authority;
  posted edit/delete actions are unavailable.
- Financial calculation: Decimal quantity is converted to scaled thousandths;
  integer multiplication uses ROUND HALF UP to Rupiah. Supported unit is
  exactly `Kilogram (Kg)`. Waste name maps from `category`; category snapshot
  maps from the actual `output` field.
- Transaction boundary: User row is locked with `lockForUpdate()` and deposit,
  items, ledger, and cached balance update run inside `DB::transaction`.
- Ledger behavior: One `deposit_credit` references the posted deposit; the M1
  unique reference/type constraint prevents a second credit for that deposit.
- Concurrency handling: User row lock plus Eloquent atomic `increment` keeps
  concurrent cached balance updates serialized.
- Idempotency: Enforced per source deposit by the database unique constraint;
  request-level deduplication requires an explicit idempotency key and remains
  outside M2.
- Tests added: `tests/Feature/DepositServiceTest` covering success, multiple
  items, server price authority, snapshots, fractions/rounding, invalid input,
  unsupported units, rollback, one-credit invariant, and posted deletion.
- Tests run: `docker compose exec -T app php artisan test`.
- Test result: 14 passed, 55 assertions, 0 failed, 0 skipped.
- Development DB verification: 2 users; total balance 2,000,000; 0 deposits;
  0 deposit items; 0 withdrawals; 0 ledger entries. Tests used isolated
  SQLite `:memory:` from `phpunit.xml`.
- Financial data changed: NO. Existing balances were not reset and no opening
  balance entries were created.
- Known limitations: Cancellation/reversal, withdrawal approval, opening
  balance migration, reconciliation, and authorization hardening remain for
  later milestones. Generic user creation still accepts explicit initial
  balance values for seed/registration compatibility; runtime deposit credit
  is service-only.
- Next recommended task: M3 — Cancellation & Correction.

### 2026-09-21 — M1 Schema & Model Consistency

- Status before: M0 COMPLETE / M1 NOT STARTED.
- Work completed: Added the additive transaction schema foundation, normalized
  affected models, hardened financial foreign keys, and added schema/model
  contract coverage. No M2 business flow was implemented.
- Files changed: `database/migrations/2026_09_21_000001_add_transaction_schema_foundation.php`,
  `app/Models/LedgerEntry.php`, `app/Models/User.php`,
  `app/Models/WasteDeposit.php`, `app/Models/WasteDepositItem.php`,
  `app/Models/WasteItem.php`, `app/Models/Withdrawal.php`,
  `tests/Unit/TransactionSchemaModelsTest.php`, `status.md`.
- Migrations created: `2026_09_21_000001_add_transaction_schema_foundation`.
- Schema changes: Deposit lifecycle fields and default `draft`; nullable actor
  fields; item snapshots; nullable master reference; quantity `DECIMAL(12,3)`;
  `account_ledger_entries` with opening balance support and integer money.
- FK changes: Administrative and financial owner relationships use `RESTRICT`;
  actor references use `SET NULL`; waste master reference uses `SET NULL`;
  deposit-to-item composition remains `CASCADE`.
- Models changed: Fixed deposit item foreign key; normalized `Withdrawal`,
  statuses, dates, and casts; removed invalid `WasteItem` accessors; added
  deposit lifecycle casts/relations; added `LedgerEntry` and user relations.
- Tests added: `TransactionSchemaModelsTest` for relationship, casts, status,
  snapshot, and ledger model contracts.
- Tests run: `docker compose exec -T app php artisan test`.
- Test result: 3 passed, 12 assertions, 0 failed, 0 skipped.
- Database verification: Migration applied successfully. Users: 2; total
  balance: 2,000,000; deposits: 0; deposit items: 0; withdrawals: 0; ledger
  entries: 0. Verified lifecycle, snapshot, quantity, ledger, indexes, and FK
  metadata through MySQL read-only queries.
- Financial data changed: NO. No opening balance entries were created and
  `users.balance` was not modified.
- Remaining issues: Financial posting, ledger writes, cancellation/reversal,
  withdrawal approval, reconciliation, and authorization remain for later
  milestones. The nullable reference columns in the unique ledger constraint
  require future services to provide source references for idempotency.
- Next recommended task: M2 — Transaction Domain Service.

### 2026-09-21 — M0 Database Completion

- Status before: M0 INCOMPLETE / BLOCKED.
- Work completed: Started/verified the existing Docker environment, confirmed
  the existing MySQL bind volume, created a pre-analysis backup, ran read-only
  financial/integrity/unit/cascade queries, ran the application test suite,
  and verified the effective Laravel database connection.
- Files changed: `status.md`; backup file is ignored under `backups/`.
- Database inspected: MySQL 8.0.46, database `wrongshock`; 2 users, 0
  waste deposits, 0 deposit items, 0 withdrawals. No writes were executed.
- Backup status: Success; `backups/wrongshock_before_core_transaction_20260921_095503.sql`,
  25K (24,790 bytes), non-empty and ignored by Git.
- Tests run: `docker compose exec -T app php artisan test`.
- Test result: 2 passed, 2 assertions, 0 failed, 0 skipped.
- Financial anomalies: No deposit/item/withdrawal integrity anomalies. Both
  users have balance 1,000,000 with deposit-derived balance 0, difference
  +1,000,000 each; classified `NO_DEPOSIT_BUT_HAS_BALANCE` and requiring
  opening-balance review.
- Unit findings: 12 master items, only `Kilogram (Kg)`, price range 300 to
  3,000. No existing quantity rows; fractional usage cannot be inferred from
  current data. Quantity schema remains `BIGINT` with scale 0.
- Cascade risks: Actual foreign keys remain `CASCADE`. Deleting district 1
  would cascade to 2 users and their balances; deleting subdistricts 1 and 2
  would each cascade to 1 user. Current deposit/item/withdrawal history is
  empty, so those child counts are currently zero.
- Remaining issues: Opening-balance policy, canonical unit/precision, cached
  balance policy, and all M1 schema decisions remain unresolved. `db.env`
  remains tracked in Git and was not altered.
- Next recommended task: Stop after M0; resolve the listed decisions before
  starting M1.

### 2026-09-21 — M0 Safety Baseline

- Status before: NOT STARTED / AUDITED; M0 TODO.
- Work completed: Read all source-of-truth documents; inspected source,
  migrations, seeders, dependency lock, Docker configuration, Git state, and
  financial mutation paths; performed safe environment checks.
- Files changed: `status.md`; `.gitignore` only to ignore future database
  backups.
- Database inspected: No rows. Effective runtime connection and data contents
  could not be verified because PHP, Composer, SQL clients, and the DB
  container are unavailable. No database command was executed.
- Backup status: No backup created. Procedure documented above; blocked until a
  controlled DB runtime and credential source are available.
- Tests run: None. `php artisan test` was not runnable because `php` is not
  installed.
- Test result: Not assessed.
- Financial anomalies: Runtime unknown. Source-level risks include direct
  observer credit, editable/deletable deposits, no ledger, manual seeded
  balances, and incomplete withdrawal implementation.
- Unit findings: Seeder labels all master units `Kilogram (Kg)`; actual data
  distribution and fractional usage are unknown. Quantity semantics remain
  unresolved.
- Cascade risks: District/subdistrict/user/deposit/deposit-item/waste-item/
  withdrawal cascades can remove financial history.
- Remaining issues: Safe DB access, backup, read-only data analysis, balance
  reconciliation, runtime test baseline, and credential tracking cleanup.
- Next recommended task: Resolve M0 blockers first; only then begin M1.

## Completion

Core stabilization is complete only when every acceptance criterion in
`prd.md` is checked and the test suite verifies financial integrity.
