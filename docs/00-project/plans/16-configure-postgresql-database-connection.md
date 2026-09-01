# Configure PostgreSQL Database Connection

## Objective
Configure the application to use PostgreSQL as the default database connection instead of the default MySQL/SQLite.

## Context
The project blueprint indicates that PostgreSQL is used for E2E browser tests and potentially for the application in production. The current environment is configured for MySQL/SQLite via the `.env` file.

## Steps
1. Copy `.env.example` to `.env` if it does not already exist.
2. Update the `.env` file with the following PostgreSQL connection settings:
   - `DB_CONNECTION=pgsql`
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=5432`
   - `DB_DATABASE=filament_inventory`
   - `DB_USERNAME=` (leave empty for user to fill)
   - `DB_PASSWORD=` (leave empty for user to fill)
3. Verify the configuration by attempting to connect to the database using an Artisan command (e.g., `php artisan tinker --execute='DB::connection()->getPdo();'`).
4. Ensure that the test suite continues to pass (tests use an in-memory SQLite database and are unaffected by this change).

## Notes
- The `.env` file should not be committed with actual credentials. Users must fill in `DB_USERNAME` and `DB_PASSWORD` locally.
- This change does not affect the test environment, which is configured to use `:memory:` for SQLite in `phpunit.xml`.
- If PostgreSQL is not running locally, the user must install and start a PostgreSQL instance and create the `filament_inventory` database.

## Validation
- Run `php artisan test` to confirm that the application still boots and tests pass (using SQLite).
- Optionally, run a database connection check in tinker to verify the PostgreSQL settings are read correctly.