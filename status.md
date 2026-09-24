# STATUS --- Wrongshock Product Evolution

## Overall

**Current status: P2.1B COMPLETE / Admin Sidebar UX Fix COMPLETE / P2.2 NOT STARTED / P1.8 NOT STARTED / M8 COMPLETE**

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

Current verified development DB: 3 users, cached balance total 2,060,800, 6
ledger entries, ledger net 2,060,800, 3 deposits, 7 deposit items, and 0
withdrawals. Reconciliation reports MATCH for all 3 users. P2.1A added one
legacy bank (`BS001`), one legacy admin assignment, and bank context to all
existing deposits without changing financial values or ledger rows.

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

### P2.1A --- Multi-Bank Architecture Foundation

Status: COMPLETE. Citizens remain global accounts with global cached balances
and ledger reconciliation. Waste banks and many-to-many staff assignments were
added; operational deposits, withdrawals, resources, dashboards, policies, and
services now use the authenticated admin's single active bank assignment.
Existing transactions were backfilled to the neutral legacy bank `BS001`, and
the existing admin was assigned to it. The ledger schema and entries were not
changed. Waste master data and prices remain global by deliberate transition
design. P2.1B role separation and bank administration are deferred.

P2.1A compatibility coverage passes on MySQL runtime and SQLite in-memory
tests. New isolation tests cover cross-bank listing, direct URLs, cancellation,
forged bank IDs, inactive banks, cross-bank citizen history, and withdrawals.

### P2.1B --- Roles & Bank Administration

Status: COMPLETE. Added the `super_admin` platform role while preserving
`admin` as a bank administrator and `user` as a citizen. The legacy active
`admin@gmail.com` account was promoted deterministically to `super_admin` and
removed from bank staff assignments. Super admins have no implicit bank
context; bank admins require exactly one active bank assignment.

Added platform-only Bank Sampah and Admin Bank Sampah resources, transactional
admin assignment and role-transition services, platform dashboard metrics,
global master-data authorization, and role-aware navigation. Bank admins retain
bank-scoped deposits, withdrawals, members, and dashboards. Citizen identity,
global balance, ledger semantics, and global pricing remain unchanged.
Bank switching, per-bank pricing, and Petugas remain deferred.

### Admin Sidebar UX Fix --- Expanded Navigation

Status: COMPLETE. The icon-only desktop behavior was caused by the explicit
Filament `sidebarCollapsibleOnDesktop()` setting. It was removed in favor of
Filament's native expanded desktop sidebar, with a supported `16.5rem` width.
Existing navigation groups, authorization, active states, responsive drawer
behavior, and approved admin dashboard styling remain intact. Bank admins now
see a compact non-interactive current-bank context in the sidebar footer;
platform admins retain the platform context.

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

### P1.5 --- User Withdrawal UX

Status: DEFERRED. A member-initiated withdrawal flow would be a new product
capability rather than a redesign of an existing user journey. The approved P1
scope remains read-only for balances and financial history; existing backend
withdrawal behavior is unchanged.

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

### P1.7 --- Mobile, Responsive & Accessibility Polish

Status: COMPLETE. Preserved the approved
P1.4.2/P1.6 design while hardening mobile wrapping, tablet topbar constraints,
large financial values, long identity/transaction content, filter loading and
wrapping, pagination semantics, keyboard focus, coarse-pointer touch targets,
decorative pointer behavior, and reduced motion. Source-level accessibility and
regression checks are complete. Developer manual browser QA passed for desktop,
390px mobile, and the 320px overflow check without regressions to the approved
visual design.

### P1.7.1 --- Authentication UI Redesign

Status: COMPLETE. User and admin login pages now use a responsive split-screen
Wrongshock eco-finance presentation while retaining separate routes, page
classes, role rules, and panel destinations. User login is cheerful and
community-oriented; admin login is more restrained and operational. Explicit
labels, password visibility controls, autocomplete, focus treatment, touch
targets, generic errors, loading behavior, and mobile layouts remain backed by
Filament's existing form and authentication behavior. Auth CSS loads only on
login pages. P1.8 Final User UI QA has not started.

### P1.7.2 --- Public Homepage Redesign

Status: COMPLETE. Replaced the default public homepage with a responsive,
read-only Wrongshock landing page for visitors and members. It includes a
public navbar, truthful hero, value propositions, current master-price cards,
actual deposit workflow, safe waste-preparation guidance, product benefits,
member CTA, and factual footer. No impact counters, testimonials, partners,
social links, contact details, or unsupported features are advertised.

The homepage reads at most eight `waste_items` rows using the authoritative
`category`, `output`, `unit`, and `price` fields, ordered by category and id.
Missing or empty master data renders a friendly empty state. No member,
balance, address, email, ledger, or transaction data is queried for the public
page. Authenticated users receive only a safe member CTA; admin access is not
made a public primary destination. P1.8 Final User UI QA remains NOT STARTED.

### P1.7.3 --- Public Registration UI Redesign

Status: COMPLETE. Replaced the generic Bootstrap/jQuery registration page with
a responsive Wrongshock onboarding screen that shares the public homepage and
user-login visual system. The one-page form is grouped into personal data,
location, and account security, with a cheerful local eco illustration, truthful
member benefits, accessible password visibility controls, field-level errors,
safe old-input behavior, and clear inactive-account activation messaging.

Registration semantics remain unchanged: `POST /storeRegister` is throttled at
6 requests per minute, CSRF remains required, passwords are hashed, users are
created with the `user` role, status `0`, balance `0`, and a generated member
number. District/subdistrict pairing is still validated server-side. The public
location endpoints return only id/name maps and the dependent select now has
loading, disabled, and failure states. P1.8 Final User UI QA remains NOT
STARTED.

### P1.7.4 --- Admin Panel & Operational Dashboard Redesign

Status: COMPLETE. Replaced the default admin dashboard widget set with a
Wrongshock operational overview while preserving Filament resources, routes,
authorization, and all financial services. The admin shell now uses a restrained
deep-green, mint, warm off-white, and yellow-accent system: branded sidebar,
resource groups, contextual topbar, admin identity, responsive cards, dense
tables, and operational empty states.

The dashboard reads authoritative data only. Primary KPIs are total member
accounts excluding admins, posted deposits this month, posted deposit value
this month, and members with a posted deposit this month. The six-month trend
uses posted deposit value grouped from `deposit_date`. The composition card uses
posted item `subtotal` grouped by `category_snapshot`, with the current master
category only as a legacy fallback. Quantity is intentionally not aggregated:
the master data supports multiple units and a combined Kg/gram value would be
misleading.

Recent deposits are limited to six and preserve stored historical totals and
statuses. Operational attention shows only existing actionable states: inactive
member accounts and pending withdrawals. The dashboard has no reconciliation
KPI, fake environmental metrics, notifications, or new workflow. Existing
resources remain reachable and are grouped as Anggota, Transaksi, and Master
Data with Indonesian labels. The former standalone dashboard charts remain in
the codebase but are no longer registered, avoiding their unfiltered and
unit-unsafe queries without deleting working resource functionality.

Authorization remains enforced by the existing active-user and admin-role
middleware. The dashboard is read-only, uses count/sum/grouped queries rather
than loading all transaction rows, and eager-loads recent member identity. The
snapshot fallback remains safe after a master waste item is deleted. P1.8 Final
User UI QA remains NOT STARTED.

### P1.7.4.1 --- Admin UX & Deposit Flow Refinement

Status: COMPLETE. Refined the approved P1.7.4 admin shell without redesigning
the dashboard. Navigation now presents only operational resources as Dashboard,
Anggota, Transaksi (Setoran and Penarikan), and Master Data (Jenis Sampah and
Wilayah through Kecamatan/Kelurahan). The internal Rincian Setoran resource
remains routable but is removed from the sidebar. Resource/page/action labels,
search placeholders, table headings, status text, form sections, save actions,
and detail labels now use consistent Indonesian terminology.

Setoran form audit found the preview bug at the repeater boundary: total was
only calculated in the repeater's `afterStateUpdated`, so child field changes
could update row state without recalculating the parent total. The form now
uses live select/quantity fields, a single preview calculator, and repeater
callbacks for add/remove/hydration. It displays actual master unit, current
preview price, half-up decimal subtotal, and total immediately. Blank/unknown
preview values resolve safely to zero, and mobile repeater columns collapse
from one to five columns responsively.

Preview `price`, `subtotal`, and `total_amount` are not dehydrated as financial
truth. `DepositService` remains unchanged and independently resolves master
items, unit price, quantity precision, snapshots, subtotal, total, ledger credit,
and cached balance. Posted deposits remain non-editable; draft edit routes
remain limited to their existing workflow. P1.8 Final User UI QA remains NOT
STARTED.

The approved detail action also received a read-side bugfix. Opening the
Filament `Lihat Detail` modal could hydrate the Setoran repeater with `null`
while its callbacks required `array`, causing a `TypeError`. Hydration and
update callbacks now treat an empty state as an empty list. The detail
infolist renders snapshot name, unit, price, subtotal, decimal quantity, stored
total, mapped status, cancellation reason, and a safe no-item state without
consulting current master prices. Posted/cancelled/draft behavior and admin
authorization remain unchanged.

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

### 2026-09-24 — P2.1B Roles & Bank Administration

- Re-verified the P2.1A baseline before changes: 3 users, cached balance and
  ledger net `2,060,800`, 6 ledger entries, 3 deposits, 7 deposit items, 0
  withdrawals, 3 MATCH, 0 MISMATCH, 1 bank, and 1 staff assignment.
- Added idempotent `super_admin` role migration. The active legacy account
  `admin@gmail.com`, already identified by the existing seeded identity and
  `admin` role, was promoted to `super_admin`; its `admin` role and bank staff
  assignment were removed. No other account was promoted.
- Refined `WasteBankContext`: bank admins require exactly one active bank;
  super admins never resolve to an implicit bank. Added transactional
  `AdminMembershipService` for bank assignment and user/admin/super-admin
  transitions.
- Added platform-only `WasteBankResource` and `WasteBankStaffResource`.
  Bank deactivation is supported; destructive bank deletion is unavailable.
  Assignment uniqueness, active-bank validation, and global admin-only
  management are enforced.
- Added a platform dashboard labelled `Ringkasan Platform Wrongshock` and
  role-aware navigation/topbar context. Bank admins retain the existing
  current-bank dashboard and operational resources.
- Restricted WasteItem, District, and SubDistrict management to
  `super_admin`. Bank admins can still use global waste items through the
  authorized deposit workflow without receiving master-data management UI.
- Added role, panel, resource, master-data, assignment, transition, platform
  dashboard, and inactive-account tests. Full regression passed with 154 tests
  and 811 assertions, 0 failures, and 0 skipped. Financial values and ledger
  rows remained unchanged.

### 2026-09-24 — Admin Sidebar UX Fix

- Audited the Filament panel and confirmed `sidebarCollapsibleOnDesktop()` was
  the root cause of the icon-focused desktop state; no custom CSS forced the
  collapse.
- Removed desktop collapsibility, set the supported sidebar width to
  `16.5rem`, and retained Filament's native responsive drawer behavior.
- Added a compact role-aware sidebar footer context for the current bank or
  platform administration without adding bank switching.
- Added sidebar configuration/label regression coverage. Focused admin tests
  passed: 8 tests and 31 assertions. No financial service, schema, ledger, or
  balance behavior changed.

### 2026-09-22 — P2.1A Multi-Bank Architecture Foundation

- Added `waste_banks` with stable unique codes, reusable district/subdistrict
  relations, optional coordinates, and active/inactive lifecycle.
- Added many-to-many `waste_bank_staff` assignments with duplicate protection.
  Existing `admin` role semantics remain unchanged; the assignment supplies
  operational bank context. No bank switcher or new role hierarchy was added.
- Added a neutral legacy bank `BS001` named `Wrongshock Bank Sampah Utama`.
  Existing admins were assigned to it, existing deposits and withdrawals were
  backfilled to it, and all financial IDs, amounts, snapshots, timestamps, and
  ledger rows were preserved.
- Added bank ownership to deposits and withdrawals. The ledger remains global
  and unchanged; `users.balance` remains global and reconciliation remains
  per-user across all banks.
- Added `WasteBankContext`, requiring an active admin with exactly one active
  bank assignment for operational work. Services reject forged cross-bank
  context and inactive-bank new transactions.
- Scoped deposit/withdrawal resources, member visibility, dashboards, and
  operational analytics to the current bank. Citizen dashboards and history
  remain global to the authenticated citizen and now display bank identity.
- Added idempotent `WasteBankSeeder`, a `WasteBankFactory`, and focused
  multi-bank isolation tests. P2.1B role separation, bank administration,
  per-bank pricing, settlement, switching, and government features remain
  deferred.
- Baseline before migration: 3 users, cached balance 2,060,800, 6 ledger
  entries, ledger net 2,060,800, 3 deposits, 7 items, 0 withdrawals, and
  3 MATCH / 0 MISMATCH. After migration the values remain identical.

### 2026-09-22 — P1.7.4.1 Admin UX & Deposit Flow Refinement

- Audited current admin provider/navigation, resource labels, form/page actions,
  table headings, breadcrumbs/default Filament terminology, and the Setoran
  form/create/edit flow. Confirmed `DepositService` is authoritative and that
  the existing form callbacks were only preview logic.
- Standardized visible terminology: Pengguna became Anggota, Setor Limbah
  became Setoran, Daftar Harga Limbah became Jenis Sampah, Output became Hasil
  Pengolahan, Diajukan became Tanggal Permintaan, Diproses became Tanggal
  Diproses, and default Create/Edit/View/Delete/Search labels now use natural
  Indonesian labels. Master mapping is `category` = Kategori Sampah,
  `output` = Hasil Pengolahan, `unit` = Satuan, and `price` = Harga per Satuan.
- Refined Setoran into Informasi Setoran, Detail Sampah, and Ringkasan. The
  repeater action is `Tambah Jenis Sampah`; rows show Jenis Sampah, Jumlah,
  Satuan, Harga per Satuan, and Subtotal. Remove has an accessible Indonesian
  tooltip. Saved values remain service-authoritative and preview fields are
  explicitly non-dehydrated.
- Fixed immediate preview behavior for select, quantity, add, remove, change,
  decimal quantity, blank quantity, and actual master units. Added an Infolist
  for Setoran detail using historical item snapshots and clear cancellation
  information.
- Added `AdminDepositFormTest` with helper and direct Livewire coverage. The
  live form now proves quantity changes update subtotal and total without
  adding another row. Existing DepositService tests continue to protect
  manipulated-preview/server-authority behavior.
- Focused refinement suite passed with 4 tests and 13 assertions. Full
  regression passed with 133 tests and 707 assertions, 0 failures, and 0
  skipped. Blade cache, Pint, and `git diff --check` passed. Composer audit
  reports 0 advisories; the package index had a network timeout but local
  advisory data was clean.
- Financial DB before and after remained: 3 users, cached balance
  2,017,800, 5 ledger entries including 2 opening balances, ledger net
  2,017,800, 2 deposits, 4 deposit items, 0 withdrawals, and 3 MATCH / 0
  MISMATCH. Financial Data Changed: NO.
- Browser verification at 1440px, 1024px, 768px, and 390px is NOT VERIFIED
  because browser tooling is unavailable. Blade rendering, focused tests, and
  responsive source rules pass. No manual financial transaction was created.

### 2026-09-22 — Admin Deposit Detail Bugfix

- Reproduced `ADMIN -> SETORAN -> Lihat Detail` through the actual Filament
  table action. The action uses a read-only modal/infolist; there is no separate
  detail route. Posted multi-item data reproduced a `TypeError` at
  `WasteDepositResource.php:111`: the repeater `afterStateHydrated` callback
  required `array`, but Filament supplied `null` during modal hydration.
- Made repeater hydration and update callbacks null-safe without changing
  `DepositService`, transaction architecture, authorization, or status rules.
- Kept detail values snapshot-first: historical name, unit, unit price,
  subtotal, quantity, and saved deposit total. Added natural quantity and Rupiah
  formatting, mapped status text, cancellation reason display, deleted-master
  safety, and `Detail item setoran tidak tersedia.` for empty history.
- Added seven focused detail tests with 61 assertions covering admin access,
  denied user/inactive/revoked admin access, posted/cancelled/draft states,
  multiple items, historical price changes, deleted masters, empty items,
  decimal quantity, and zero financial side effects.
- Financial DB before and after this bugfix remained: 3 users, cached balance
  2,060,800, 6 ledger entries including 2 opening balances, ledger net
  2,060,800, 3 deposits, 7 deposit items, 0 withdrawals, and 3 MATCH / 0
  MISMATCH. Financial Data Changed: NO.
- Full regression after the detail fix passed with 140 tests and 768
  assertions, 0 failures, and 0 skipped. Blade cache, Pint, and
  `git diff --check` passed. Composer audit reports 0 advisories.
- Browser verification at 1440px and 390px is NOT VERIFIED because browser
  tooling is unavailable. P1.8 remains NOT STARTED.

### 2026-09-22 — P1.7.4 Admin Panel & Operational Dashboard Redesign

- Audited the existing `AdminPanelPanelProvider`, six discovered resources,
  three legacy chart widgets, panel middleware, resource navigation, and
  `User::canAccessPanel`. The old dashboard was the default Filament page plus
  account/info widgets and legacy charts. The legacy charts were not suitable:
  they did not consistently filter posted status, one labeled mixed units as
  Kg, and one joined current waste master categories for historical data.
- Added `AdminDashboard`, a custom Filament dashboard page with a compact
  welcome block, four truthful KPIs, six-month posted-value trend, snapshot
  category composition, member summary, recent deposits, and operational
  attention. The chart is a native accessible HTML/CSS bar view, so no second
  chart library or CDN dependency was added.
- Added scoped `public/css/admin-panel.css` and admin render hooks for brand,
  topbar title, admin identity, sidebar note, and shell styling. Existing
  resources remain operational; only navigation labels/groups and shared visual
  presentation changed. User-panel, public, and admin-login styles remain
  isolated.
- Grouped existing navigation as Dashboard, Anggota, Transaksi (Setoran,
  Rincian Setoran, Penarikan), and Master Data (Jenis Sampah, Kecamatan,
  Kelurahan). No unimplemented feature or quick action was added.
- Added `AdminDashboardTest` covering authorized access, ordinary/inactive/
  revoked denial, member exclusion of admins, posted-only KPI semantics,
  snapshot safety after master deletion, mixed-unit safety, pending/inactive
  attention, and read-only behavior.
- Focused admin suite passed with 7 tests and 26 assertions. Full regression
  passed with 129 tests and 694 assertions, 0 failures, and 0 skipped. Blade
  cache, Pint, and `git diff --check` passed. Composer audit reports 0
  advisories.
- Financial values remained unchanged: cached balance and ledger net are
  2,017,800, ledger entries are 5 including 2 opening balances, deposits are 2,
  deposit items are 4, withdrawals are 0, and reconciliation is 3 MATCH / 0
  MISMATCH in the current database snapshot. The database contains one
  additional zero-balance inactive member from the existing development state;
  no dashboard request created or changed financial records.
- Browser verification at 1440px, 768px, and 390px is NOT VERIFIED because
  browser tooling is unavailable. Automated admin/user authorization and
  server-rendered smoke checks pass. P1.8 remains NOT STARTED.

### 2026-09-22 — P1.7.3 Public Registration UI Redesign

- Audited registration architecture before editing: public `GET /register`,
  `POST /storeRegister` through `RegisterController::register`, async district
  and subdistrict maps, server validation, generated Bontang member number,
  user role assignment, inactive status, zero balance, hashed password,
  redirect back to `/register`, CSRF, and `throttle:6,1`.
- Replaced the Bootstrap/Font Awesome/jQuery view with a split desktop layout
  and compact mobile layout using the same Wrongshock brand, palette, radius,
  focus ring, and approved local `eco-community.svg` illustration as homepage
  and authentication. No multi-step wizard or new registration architecture
  was introduced.
- Grouped fields into Informasi Pribadi, Lokasi, and Keamanan Akun. Added
  explicit Indonesian labels, `name`/`email`/`street-address`/`new-password`
  autocomplete, accessible password toggles, semantic required inputs, visible
  errors, top-level error summary, safe old-input retention, and no password
  repopulation.
- Replaced jQuery location loading with native fetch while preserving the
  existing public endpoints. District changes disable and reload Kelurahan;
  loading and request failure states are readable and non-technical.
- Corrected success copy to state that the new account waits for manager
  activation. Homepage and user-login registration links now point to the real
  named `/register` route; registration links back to `/` and `/user/login`.
- Added seven focused registration tests covering rendering, successful hashed
  inactive member creation, duplicate/required/region validation, safe old
  input, protected fields, CSRF, public location privacy, and throttling.
- Full regression passed with 122 tests and 668 assertions, 0 failures, and 0
  skipped. Blade compilation, Pint, and `git diff --check` passed. Composer
  audit reports 0 advisories.
- Financial baseline remained unchanged at 2 users, cached and ledger net
  2,017,800, 5 ledger entries including 2 opening balances, 2 deposits, 4
  deposit items, and 0 withdrawals; reconciliation remained 2 MATCH and 0
  MISMATCH. Authenticated user UI and admin UI were not redesigned.
- Browser verification at 1440px, 390px, and 320px is NOT VERIFIED because
  browser tooling is unavailable. Source responsive checks and HTTP rendering
  tests pass. P1.8 remains NOT STARTED.

### 2026-09-22 — P1.7.2 Public Homepage Redesign

- Audited the previous homepage: it was a Bootstrap/CDN-based Laravel starter
  composition with an oversized photo hero, dummy WhatsApp/social links,
  unverified author/testimonial-like cards, an admin login dropdown, and no
  authoritative public price information. Those claims and obsolete landing
  assets were removed.
- Rebuilt `/` as a public Wrongshock page with a sticky responsive navbar,
  hero and local approved eco illustration, three truthful value cards, four
  actual operational steps, current waste-price cards, practical preparation
  tips, product-benefit explanation, CTA, and factual footer.
- Added native `<details>` mobile navigation with keyboard/focus support and a
  small close-on-link script. No frontend dependency, remote image, base64
  content, carousel, or heavy animation was added.
- Public prices are read-only and limited to eight deterministic rows. The
  route checks for a missing `waste_items` table so tests and empty deployments
  receive the same safe empty state instead of a server error.
- Added SEO title/description/theme metadata, skip navigation, semantic main
  sections, one H1, H2 section hierarchy, visible focus states, touch-sized
  controls, decorative `aria-hidden` elements, alt text for the meaningful hero
  illustration, and reduced-motion CSS.
- Added five public homepage tests covering public access, real price values,
  empty master data, privacy, authenticated user/admin CTAs, no fake metrics,
  and deterministic eight-row limits.
- Deleted the obsolete landing-only stylesheet and twelve unreferenced legacy
  image assets. The authenticated user panel, admin panel, auth backend,
  financial services, schema, and persisted financial data were not changed.
- P1.7.2 browser visual verification at 1440px, 390px, and 320px is NOT
  VERIFIED because browser tooling is unavailable. Source breakpoints,
  Blade compilation, HTTP rendering, and regression tests pass: 115 tests and
  588 assertions, 0 failures, and 0 skipped. Composer audit reports 0
  advisories.

### 2026-09-22 — P1.7.1 Authentication UI Redesign

- Before architecture: `/user/login` used
  `App\Filament\UserPanel\Pages\Auth\Login`, which extends the shared
  `App\Filament\Pages\Auth\Login`; `/admin/login` used the shared class
  directly. Both panels used the session-based `web` guard and `users` provider,
  but `User::canAccessPanel()`, active-status enforcement, and panel-specific
  role middleware kept access and destinations separate.
- Existing authentication authority remains unchanged: five-attempt Livewire
  throttling, credential verification, remember-me state, session regeneration,
  CSRF-protected Filament logout, and panel-specific login/logout responses.
- Redesigned both login screens with one scoped view system and distinct user
  and admin variants. Desktop uses a substantial split-screen composition;
  mobile prioritizes the form while retaining a compact Wrongshock brand and
  environmental illustration.
- Reused the approved local `eco-community.svg`; no dependency, remote image,
  base64 asset, registration route, or new product capability was added.
- User login uses cheerful mint/sky/yellow accents and community copy. Admin
  login uses deep green, white, and soft mint with an explicit Area Pengelola
  context and only existing operational capabilities in its copy.
- Added explicit Indonesian labels and generic authentication errors,
  `email`/`current-password` autocomplete, Indonesian password-toggle names,
  visible focus, approximately 44px controls, wrapping validation, reduced
  motion handling, and decorative elements ignored by assistive technology.
- Focused auth/authorization/navigation regression passed with 28 tests and 154
  assertions. Full regression passed with 110 tests and 554 assertions. Blade
  compilation, Pint, and `git diff --check` passed. Composer audit reported 0
  advisories using local cache after Packagist timed out.
- Development financial baseline remained unchanged at 2 users, cached and
  ledger net 2,017,800, 5 ledger entries including 2 opening balances, 2
  deposits, 4 deposit items, and 0 withdrawals. Reconciliation remained 2
  MATCH and 0 MISMATCH.
- Auth CSS isolation tests confirm it is absent from authenticated Beranda and
  admin dashboard responses. No authenticated user page, admin dashboard,
  resource, financial service, schema, or persisted financial data changed.
- Browser tooling was unavailable, so visual verification at 1440px, 390px,
  and 320px is NOT VERIFIED. Source breakpoints and render regressions pass.
- P1.7.1 is COMPLETE. P1.8 Final User UI QA remains NOT STARTED.

### 2026-09-21 — P1.7 Manual Browser QA Confirmation

- Developer manual QA passed on desktop for Login, Beranda, Riwayat Setoran,
  Detail Setoran, and Profil.
- Mobile QA passed at 390px, including drawer/navigation, hero, balance, quick
  actions, filters, transaction cards, pagination, detail, profile upload and
  preview, save/validation behavior, focus visibility, and page overflow.
- The 320px overflow check passed, and the approved P1.4.2/P1.6 visual language
  remained intact. No implementation changes were required.
- P1.7 status is COMPLETE and ready for P1.8 Final User UI QA.

### 2026-09-21 — P1.7 Mobile, Responsive & Accessibility Polish

- Kept the approved visual composition and corrected narrow-screen wrapping for
  long names, member numbers, balances, transaction values, profile identity,
  and cancellation messages without blanket overflow suppression.
- Made mobile filters wrap, constrained tablet topbar identity, increased key
  coarse-pointer targets to approximately 44px, and strengthened keyboard focus
  indicators using the approved green palette.
- Corrected filter and pagination semantics, added filter loading announcement
  and busy state, retained pagination with deposit-list scroll targeting, and
  removed low-value unnamed article landmarks.
- Expanded reduced-motion handling and prevented decorative leaves from
  intercepting pointer input.
- Added render regressions for long identity, `Rp9.999.999.999`, long waste
  names, large quantity/value, and long cancellation reasons.
- Focused user regression: 28 tests passed, 175 assertions. Full regression:
  100 tests passed, 468 assertions. Composer audit: 0 advisories.
- Development financial baseline remained unchanged at 2 users, cached and
  ledger net 2,017,800, 5 ledger entries, 2 deposits, 4 deposit items, and 0
  withdrawals; reconciliation remained 2 MATCH and 0 MISMATCH.
- Subsequent developer manual browser QA passed at desktop, 390px, and 320px;
  see the confirmation entry above.

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
