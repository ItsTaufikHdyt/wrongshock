# AGENTS --- Wrongshock Core Transaction Stabilization

## Mission

You are working on the Wrongshock Laravel application. Your current
mission is **only to stabilize the core financial/transaction domain**
according to `prd.md`.

Read these files before changing code: 1. `prd.md` 2. `status.md` 3.
This `agents.md` 4. Relevant source/migrations/tests

Treat `prd.md` as product requirements and `status.md` as the live
implementation record.

## Current Architecture Context

-   Laravel 12 monolith
-   Filament 3 / Livewire 3
-   Eloquent
-   Spatie Laravel Permission
-   Current deposit calculation is in `WasteDepositResource`.
-   Current deposit-create balance credit is in `WasteDepositObserver`.
-   Current application has admin and user roles.
-   Current financial flow has known integrity defects; do not preserve
    unsafe behavior merely for compatibility.

## Scope

Work only on: - deposit financial integrity; - transaction snapshots; -
ledger; - balance reconciliation; - cancellation/reversal/correction; -
withdrawal integrity; - unit/quantity consistency; - transaction
authorization; - tests for these domains; - necessary documentation.

Do NOT implement unless explicitly requested: - AI - pickup - GIS -
gamification - payment gateway - collector marketplace - government
dashboard - multi-bank tenancy - unrelated UI redesign

## Mandatory Workflow

### Before Coding

1.  Read `prd.md` and `status.md`.
2.  Inspect current implementation; do not assume audit notes are still
    current.
3.  Run `git status`.
4.  Identify the smallest milestone/task that can be completed safely.
5.  Inspect migrations/models/resources/observer/tests involved.
6.  State the intended change before editing.
7.  If a decision in `status.md` is unresolved and materially changes
    schema/business behavior, do not invent a business rule. Use the
    recommended default only when the PRD explicitly permits it;
    otherwise stop and report the decision needed.

### During Coding

1.  Make focused changes.
2.  Preserve existing data.
3.  Use Laravel conventions.
4.  Put financial business rules in explicit service/action classes, not
    Filament form callbacks.
5.  Use database transactions for multi-write financial operations.
6.  Add tests with each business rule.
7.  Avoid unrelated refactors.
8.  Do not add packages unless strictly necessary and explicitly
    approved.
9.  Do not expose or print secrets.

### After Coding

1.  Run targeted tests.
2.  Run broader relevant tests.
3.  Review diff.
4.  Verify no accidental secret/generated/vendor changes.
5.  Update `status.md` truthfully:
    -   checked items only when actually completed;
    -   tests actually run;
    -   files/migrations changed;
    -   remaining risks;
    -   next task.
6.  Do not claim runtime/production verification unless it was actually
    performed.

## Financial Invariants

These are non-negotiable.

### Invariant 1 --- Traceability

Every balance change must correspond to a ledger entry with a traceable
source/reason.

### Invariant 2 --- Atomicity

A financial operation must either fully succeed or fully fail.

For deposit posting, header/items/ledger/cached balance must not end in
a partially committed state.

### Invariant 3 --- Server Authority

Never trust client/Livewire-provided: - price - subtotal - total -
resulting balance

Resolve and calculate authoritative values server-side.

### Invariant 4 --- Historical Price

A posted transaction uses snapshot data. Changing the waste master later
must not rewrite historical economics.

### Invariant 5 --- No Destructive Financial Delete

Posted/cancelled financial transactions must not be hard-deleted through
normal business flow.

### Invariant 6 --- Reversal

Corrections to posted financial effects must be represented through
controlled reversal/correction, not silent mutation.

### Invariant 7 --- Idempotency

Repeating the same post/approve/cancel action must not duplicate its
credit/debit/reversal.

### Invariant 8 --- Balance Consistency

If `users.balance` is retained, it is a cache. It must equal the
ledger-derived balance after committed operations.

### Invariant 9 --- Units

Money calculations must use explicit, deterministic units and
decimal-safe quantity handling.

### Invariant 10 --- History Survival

Deleting/changing master data must not destroy the meaning or financial
evidence of historical transactions.

## Implementation Guidance

### Deposit Posting

Prefer an explicit service/action such as: `DepositService::post(...)`

It should: - validate input; - load master items; - normalize
quantities/units; - create snapshots; - compute subtotals and total; -
execute persistence inside `DB::transaction`; - create one ledger
credit; - update cached balance if retained; - prevent duplicate
posting.

Do not keep `WasteDepositObserver` as an independent second source of
balance mutation after the service becomes authoritative.

### Posted Deposit Editing

Do not allow ordinary edit semantics to silently alter a posted deposit.

Preferred first stable workflow: 1. cancel original deposit with reason;
2. create reversal; 3. create a replacement deposit if correction is
required.

Draft editing is acceptable if a draft has never affected the ledger.

### Ledger

Ledger should be append-oriented. Do not update/delete historical ledger
entries as a routine correction mechanism.

Use database constraints to prevent duplicate financial effects when
possible.

### Withdrawal

Approval is the financial event. - pending: no debit; - approved:
exactly one debit; - rejected: no debit.

Approval must validate available balance inside the transaction.

### Existing Data

Existing data is potentially inconsistent.

Never assume: `current balance == sum(existing deposits)`

Known seed/manual balances may exist without deposit history.

Migration/backfill rules: - derive only what can be proven; - create
explicit opening-balance migration entries when legitimate balances must
be preserved; - annotate migration origin; - report unverifiable
cases; - never fabricate historical deposits.

## Database Rules

-   Prefer additive migrations before destructive changes.
-   Backups are required before data migration in a real environment.
-   Use `DECIMAL` for fractional weight, not float/double.
-   Review every financial FK cascade.
-   Add indexes/unique constraints that enforce business invariants.
-   Migration down methods must be considered, but never make rollback
    more destructive than necessary.

## Testing Rules

No financial task is complete without tests.

At minimum cover: - correct deposit total; - ledger credit exactly
once; - snapshot persistence; - master price changes do not alter
history; - fractional quantities; - transaction rollback; - cancellation
reversal exactly once; - posted hard-delete prevention; - insufficient
withdrawal balance; - withdrawal debit exactly once; - authorization; -
reconciliation.

Use factories/fixtures consistent with the actual schema. Fix the
existing `UserFactory` mismatch when needed for reliable domain tests.

## Security Rules

-   Never expose `.env`, `db.env`, tokens, passwords, keys, or seeded
    credentials in output.
-   Do not weaken CSRF/auth/rate limits to make tests pass.
-   Do not bypass authorization in production code for convenience.
-   Treat user IDs and transaction IDs as untrusted input.
-   Verify ownership/role server-side.
-   Do not rely only on hidden Filament UI actions for authorization.

## Code Quality

Prefer: - small explicit domain methods; - descriptive names; - typed
parameters/returns where consistent with project style; - Laravel
validation; - Eloquent relationships; - DB constraints for critical
invariants; - tests that express business behavior.

Avoid: - financial logic duplicated between resource callbacks,
observers, and models; - magic status strings scattered across files; -
silent catch blocks; - direct `User::increment('balance', ...)` from
arbitrary UI code; - giant unrelated refactors.

## Status Reporting Format

After each task, update `status.md` and report:

### Completed

-   ...

### Files Changed

-   ...

### Tests

-   command:
-   result:

### Remaining

-   ...

### Risks/Decisions

-   ...

### Next

-   ...

Do not mark a milestone DONE if only part of it is implemented.

## Stop Conditions

Stop and report instead of guessing if: - existing production data
cannot be safely mapped; - a migration may destroy financial history; -
canonical unit semantics cannot be determined; - current balances cannot
be reconciled and there is no approved opening-balance policy; -
requested behavior conflicts with `prd.md`; - tests reveal unrelated
corruption that changes the safety of the planned migration.

## Definition of Success

A future agent should be able to read `prd.md` + `status.md` + this file
and continue the stabilization work without relying on chat history.

The core is considered stable only when: - all financial effects are
ledger-backed; - deposit lifecycle is safe; - withdrawal lifecycle is
safe; - historical price/unit data is preserved; - balances reconcile; -
destructive historical deletion is prevented; - authorization is
enforced; - domain tests pass.
