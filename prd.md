# PRD --- Wrongshock Core Transaction Integrity

## 1. Document Information

-   Product: Wrongshock
-   Scope: Core transaction stabilization
-   Status: Proposed
-   Primary stack: Laravel 12, Filament 3, Livewire 3, Eloquent
-   Objective: Make waste deposits, balances, withdrawals, pricing, and
    transaction history financially consistent and auditable before
    adding GovTech/AI features.

## 2. Background

Wrongshock currently supports member registration, waste master/pricing,
admin-created multi-item waste deposits, member balances, and basic
dashboards. The current deposit flow calculates `price × quantity`,
stores a deposit, then credits `users.balance` through
`WasteDepositObserver`.

The audit identified critical integrity gaps: - Editing a deposit does
not reconcile the credited balance. - Deleting a deposit does not
reverse the credited balance. - Changing the deposit owner does not move
the credit. - There is no immutable financial ledger/reversal
mechanism. - Deposit items do not store a complete price/unit/category
snapshot. - Waste/history records are vulnerable to destructive cascade
deletion. - Quantity and units are inconsistent (e.g. gram/kg while
quantity is integer). - Deposit totals are not authoritatively
recomputed during persistence. - Header, detail, and balance mutation
are not protected by one database transaction. - Withdrawal exists only
as incomplete schema/model scaffolding.

## 3. Product Goal

Create a trustworthy transaction core where every monetary balance
change can be traced to a business transaction, corrected without
destroying history, and verified by automated tests.

## 4. Non-Goals

This phase does NOT implement: - AI waste recognition - Pickup/routing -
GIS/maps - Gamification - Payment gateway - Sales to collectors -
Government dashboard - Multi-bank tenancy - Mobile application

These features must not be mixed into the core stabilization phase.

## 5. Principles

1.  `users.balance` must never be the sole source of financial truth.
2.  Every balance mutation must have a traceable ledger entry.
3.  Historical transactions must not change when master
    prices/categories change.
4.  Business transactions must not be hard-deleted after affecting a
    balance.
5.  Monetary calculations must happen server-side.
6.  Related writes must be atomic.
7.  Corrections must be explicit and auditable.
8.  Unit/quantity rules must be deterministic.

## 6. Actors

### Admin

-   Creates and manages deposits in the current application.
-   Can correct/cancel a deposit through controlled business actions.
-   Can process withdrawals once the withdrawal flow is implemented.
-   Must not directly manipulate member balances.

### Member/User

-   Owns a balance and transaction history.
-   Can view deposits, withdrawals, and balance movements.
-   In this phase, creation of deposits remains admin-operated unless
    existing behavior requires otherwise.

## 7. Target Domain Model

### 7.1 Waste Deposit

Recommended fields: - `id` - `user_id` - `deposit_date` - `status`
(`draft`, `posted`, `cancelled`) - `total_amount` - `posted_at` -
`cancelled_at` - `created_by` - `updated_by` - timestamps

Rules: - Only `posted` deposits affect financial balance. - A posted
deposit cannot be hard-deleted. - Correction of a posted deposit must
preserve audit history.

### 7.2 Waste Deposit Item

Keep the relation to `waste_item_id`, but persist transaction
snapshots: - `waste_deposit_id` - `waste_item_id` -
`waste_name_snapshot` - `category_snapshot` - `unit_snapshot` -
`unit_price_snapshot` - `quantity` - `subtotal`

Rule: `subtotal = unit_price_snapshot × normalized quantity`

The historical item must remain understandable even if the waste master
changes later.

### 7.3 Ledger

Create a dedicated balance ledger such as `account_ledger_entries`.

Minimum fields: - `id` - `user_id` - `type` (`deposit_credit`,
`withdrawal_debit`, `deposit_reversal`, `withdrawal_reversal`,
`adjustment`) - `amount` - `direction` (`credit`, `debit`) or signed
amount - polymorphic/source reference (`reference_type`, `reference_id`)
or equivalent explicit foreign keys - `description` - `created_by` -
`created_at`

Rules: - Ledger entries are append-only in normal business flow. -
Posted deposits create a credit. - Cancelled/corrected posted deposits
create reversal entries rather than deleting old financial evidence. -
Approved withdrawals create debits. - Rejected withdrawals do not affect
balance. - Direct arbitrary writes to `users.balance` are forbidden
outside the balance/ledger domain service.

### 7.4 Balance

Preferred source of truth: `balance = SUM(credits) - SUM(debits)`

`users.balance` may remain as a cached balance for performance, but: -
it must be updated in the same DB transaction as the ledger; - it must
be reconcilable from ledger entries; - a reconciliation command/test
must detect mismatch.

### 7.5 Withdrawal

Normalize the existing withdrawal implementation.

Target statuses: - `pending` - `approved` - `rejected` - optionally
`cancelled`

Rules: - Requested amount must be positive. - Approval must verify
sufficient available balance. - Approval creates exactly one ledger
debit. - Repeated approval must not duplicate the debit. - Rejection
does not debit balance. - Status transitions are controlled. - Existing
model/schema mismatches must be fixed.

## 8. Quantity and Unit Rules

The current integer quantity is not sufficient if kg values can be
fractional.

Required decision: - Store weight using `DECIMAL`, not floating point. -
Define canonical units.

Recommended: - For weight-based waste, canonical persistence unit =
`kg`. - Example precision: `DECIMAL(12,3)` to support grams represented
as kg. - If master data allows `gram`, convert to canonical kg before
monetary calculation OR explicitly define a conversion table. - UI must
display the correct unit from the transaction snapshot.

No implementation should proceed with ambiguous unit semantics.

## 9. Price Snapshot Rules

When a deposit is posted: 1. Read the current master waste item. 2. Copy
its relevant name/category/unit/price into deposit item snapshot fields.
3. Calculate subtotal from the snapshot price. 4. Calculate deposit
total from persisted/revalidated items. 5. Never recalculate an old
posted transaction using a newly changed master price.

## 10. Transaction Lifecycle

### Create/Post Deposit

1.  Admin selects member.
2.  Admin selects waste item(s) and quantities.
3.  Backend validates member and items.
4.  Backend resolves current master prices.
5.  Backend creates immutable snapshots.
6.  Backend computes subtotals.
7.  Backend computes total.
8.  In one DB transaction:
    -   create deposit/header;
    -   create items;
    -   mark/post deposit;
    -   create ledger credit;
    -   update cached balance if retained.
9.  Commit.
10. Return success.

### Correct Posted Deposit

Preferred behavior: - Do not silently overwrite financial history. -
Reverse the previous financial effect. - Persist a corrected version or
explicit correction record. - Apply the corrected financial effect. -
Preserve who corrected it, when, and why.

A simpler acceptable first implementation: - Controlled `cancel` action
creates reversal. - Admin creates a replacement deposit. This is
preferred over unrestricted editing of posted deposits.

### Cancel Deposit

1.  Require posted deposit.
2.  Require reason.
3.  In one DB transaction:
    -   mark deposit cancelled;
    -   append deposit reversal ledger entry;
    -   update cached balance if retained.
4.  Must be idempotent: a second cancellation must not create another
    reversal.

### Delete Deposit

-   Draft deposits may be deleted if they have never affected balance.
-   Posted/cancelled deposits must not be hard-deleted through normal
    UI.

## 11. Master Data Deletion Rules

Historical financial records must survive changes to: - waste item
master - user status - district/subdistrict master

Required: - Review destructive `ON DELETE CASCADE` behavior. - Prefer
restrict, nullable references where appropriate, soft deletion, or
historical snapshots. - User/account deletion must not erase financial
history.

## 12. Service Boundary

Move financial business rules out of Filament callbacks/observers into
an explicit application/domain service.

Suggested responsibilities (names are illustrative): -
`DepositService::post(...)` - `DepositService::cancel(...)` -
`WithdrawalService::approve(...)` - `WithdrawalService::reject(...)` -
`LedgerService::credit(...)` - `LedgerService::debit(...)` -
`BalanceService::reconcile(...)`

Filament resources should collect input and invoke domain actions; they
should not be the authority for financial calculations.

## 13. Idempotency and Duplicate Protection

Required: - A source transaction may generate its financial effect only
once. - Add DB-level uniqueness where practical, e.g. unique ledger
reference + type. - Repeated Livewire submission must not duplicate a
credit/debit. - Cancellation/reversal must be idempotent.

## 14. Validation

At minimum: - user exists and is eligible for transaction; - quantity \>
0; - unit is supported; - waste item exists/active if such state is
introduced; - price \>= 0; - total equals authoritative sum of item
subtotals; - withdrawal amount \> 0; - withdrawal amount \<= available
balance at approval time; - status transition is valid.

## 15. Authorization

-   Only authorized admin/operator actions may post/cancel/correct
    deposits.
-   Members may only see their own financial records.
-   Direct balance editing must be removed from generic forms.
-   Role/status checks must remain effective across Livewire requests.
-   Existing panel access/FilamentUser issue must be addressed as part
    of core security hardening.

## 16. Auditability

Record where applicable: - actor/user who performed action; -
timestamp; - source transaction; - previous/new status; -
correction/cancellation reason.

The system must be able to answer: "Why does this member have this
balance?"

## 17. Reconciliation

Provide a safe read/reconcile mechanism that can: - compute
ledger-derived balance per user; - compare it to cached
`users.balance`; - report mismatches; - optionally repair cached balance
only through an explicit controlled command.

Before migration to the new ledger, existing balances and historical
deposits must be analyzed. Do not assume seeded/manual balances can
automatically be reconstructed from deposits.

## 18. Migration Strategy

1.  Back up database.
2.  Add new columns/tables without destroying current records.
3.  Fix model/schema mismatches.
4.  Introduce ledger and snapshot fields.
5.  Define treatment of existing historical deposits.
6.  Backfill only when derivation is reliable.
7.  Flag unreconciled opening balances explicitly (e.g. opening balance
    ledger entry with migration note) rather than inventing deposits.
8.  Replace create-only observer logic with transaction service.
9.  Disable unsafe edit/delete behavior for posted deposits.
10. Add tests before removing legacy paths.

## 19. Required Automated Tests

### Deposit

-   posting a deposit creates correct items and total;
-   posting creates exactly one ledger credit;
-   posting updates cached balance correctly if cache is retained;
-   fractional quantity calculation is correct;
-   current master price is snapshotted;
-   changing master price does not change historical transaction;
-   repeated post does not duplicate credit;
-   cancel creates exactly one reversal;
-   repeated cancel does not duplicate reversal;
-   posted deposit cannot be hard-deleted;
-   correction preserves financial consistency;
-   transaction rollback leaves no partial header/item/ledger/balance
    state.

### Withdrawal

-   request validates positive amount;
-   approval with sufficient balance creates one debit;
-   approval with insufficient balance fails;
-   repeated approval does not duplicate debit;
-   rejection does not affect balance.

### Authorization

-   member cannot view another member's transactions;
-   unauthorized user cannot post/cancel/approve financial transactions;
-   inactive/revoked users cannot retain inappropriate panel access.

### Reconciliation

-   ledger-derived balance equals cached balance;
-   deliberate mismatch is detected.

## 20. Acceptance Criteria

Core transaction stabilization is DONE only when: - \[ \] No direct
uncontrolled balance mutation remains. - \[ \] Every balance change has
a ledger source. - \[ \] Deposit create/post is atomic. - \[ \] Posted
deposit edit/delete cannot corrupt balance. - \[ \]
Cancellation/reversal is implemented and idempotent. - \[ \]
Price/unit/category snapshots preserve transaction history. - \[ \]
Fractional weights and units have deterministic semantics. - \[ \]
Withdrawal model/schema and lifecycle are consistent. - \[ \] Historical
records are protected from destructive master deletion. - \[ \]
Reconciliation is available. - \[ \] Domain transaction tests pass. - \[
\] Authorization tests pass. - \[ \] Existing data
migration/reconciliation is documented. - \[ \] README/current
architecture documentation is updated after implementation.

## 21. Definition of Done

No GovTech/AI feature should be treated as dependent on the financial
core until all acceptance criteria above are satisfied or an exception
is explicitly documented.
