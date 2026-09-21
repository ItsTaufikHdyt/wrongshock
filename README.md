# Wrongshock

Wrongshock is a Laravel application for managing waste deposits, member
balances, and withdrawals. Financial changes are posted through application
services and recorded in an append-oriented ledger.

## Stack

- PHP 8.2+
- Laravel 12
- Filament 3
- Livewire 3
- MySQL 8 for the Docker development environment
- SQLite in-memory databases for automated tests
- Vite and Tailwind CSS for frontend assets

## Core Rules

- `users.balance` is a cached balance; `account_ledger_entries` is the
  reconciliation source.
- Deposit posting creates a ledger credit and updates the cached balance in
  one database transaction.
- Posted deposits are cancelled, not deleted. Cancellation creates a reversal
  ledger entry and restores the transaction's prior effect.
- Deposit items store the price snapshot used at posting time. Historical
  totals are not recalculated from the current waste-item price.
- Withdrawal requests start as `pending`. Approval checks the balance while
  holding the user row lock, then records a ledger debit. Rejection has no
  balance effect.
- Posted financial records and withdrawal history cannot be hard-deleted.
- Subtotals and totals supplied by the UI are not trusted by the domain
  services.

## Requirements

Use Docker for the supported development setup. For a host-only setup, use
PHP 8.2+, Composer, Node.js/npm, and MySQL 8, then configure the equivalent
Laravel environment values.

## Docker Setup

1. Copy the application environment file and generate an application key:

   ```sh
   cp .env.example .env
   docker compose build
   docker compose run --rm app php artisan key:generate
   ```

2. Create `db.env` in the project root. It is intentionally ignored by Git:

   ```dotenv
   MYSQL_ROOT_PASSWORD=change-me
   MYSQL_DATABASE=wrongshock
   MYSQL_USER=wrongshock
   MYSQL_PASSWORD=change-me
   ```

   Use matching MySQL values in `.env`, for example `DB_HOST=db`,
   `DB_DATABASE=wrongshock`, `DB_USERNAME=wrongshock`, and
   `DB_PASSWORD=change-me`.

3. Start the services:

   ```sh
   docker compose up -d
   docker compose exec app php artisan migrate --seed
   ```

   The application is available at `http://localhost`. The optional PHP
   development server port is `http://localhost:8002`, Vite uses port `5173`,
   and Adminer uses `http://localhost:8080`.

Do not commit `.env`, `db.env`, database volumes, or credentials. Change all
development seed credentials before using the application outside a local
environment.

## Local Commands

Run commands inside the app container:

```sh
docker compose exec app php artisan test
docker compose exec app composer audit
docker compose exec app php artisan finance:reconcile
```

`finance:reconcile` reports cached balances versus ledger net totals. The
mutation modes are explicit and should be reviewed before use:

```sh
docker compose exec app php artisan finance:reconcile --create-opening-balances
docker compose exec app php artisan finance:reconcile --repair-cache
```

Opening balances are created only for users with a positive cached balance and
no existing financial history. Mismatches that cannot be proven safe are
reported for review instead of being silently repaired.

Install frontend dependencies only when frontend work is required, then run:

```sh
npm install
npm run build
```

The repository does not currently include an npm lockfile, so frontend
dependency resolution is not reproducible by `npm ci` yet.

## Tests

The test suite uses an isolated SQLite in-memory database configured by
`phpunit.xml`. It covers transaction atomicity, price snapshots, fractional
quantities, idempotency, cancellation, withdrawal locking, authorization, and
reconciliation.

The Docker database is development data and must not be reset or altered as a
substitute for tests. Verify financial state with the reconciliation command
after any maintenance operation.

## Current Limitations

- Pending withdrawals do not reserve balance; approval rechecks it under a row
  lock.
- Request-level idempotency keys are not implemented.
- Cancellation is rejected if the cached balance cannot cover the reversal.
- The application does not yet provide a production deployment configuration.

## License

MIT
