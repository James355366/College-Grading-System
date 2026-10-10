# College Grading System

PHP 8.4 with PDO MySQL and MariaDB 11.4 were used for validation. The application has no Composer dependencies. Bootstrap assets load from jsDelivr.

## Updating an existing database

Back up the database, then run from this directory with a database account permitted to alter the schema:

```sh
php scripts/migrate.php
```

In the prepared cloud environment:

```sh
docker exec college-grading-web php /app/scripts/migrate.php
```

The migration is repeatable. It creates login-throttling storage, makes assessment scores nullable, and enforces unique student IDs within each class. It preserves existing scores, including zeros. If duplicate IDs already exist within a class, it stops before changing records; resolve those duplicates and run it again.

`sql/schema.sql` is for a fresh demo database and **drops existing tables**. Do not use it to upgrade an existing installation. The migration was already applied to this cloud environment.

## Configuration

The application reads process environment variables directly; it does not automatically load `.env` files.

| Variable | Development default | Production configuration |
| --- | --- | --- |
| `APP_ENV` | `development` | Set to `production` |
| `DB_HOST` | `127.0.0.1` | Database hostname |
| `DB_PORT` | `3306` | Database port |
| `DB_NAME` | `college_grading_system` | Application database |
| `DB_USER` | `root` | Dedicated application account |
| `DB_PASS` | empty | Supply securely through the server environment |
| `APP_DEMO_MODE` | disabled | Demo buttons remain disabled in production |

Production mode rejects the root database account and empty database passwords, requires secure session cookies, and rejects the bundled `admin123`/`teacher123` demo passwords. Create or rotate real accounts before enabling production mode. Serve production traffic over HTTPS. A web application database user needs only SELECT, INSERT, UPDATE, and DELETE on this database; use a separate migration account for schema changes. Database and API diagnostics go to the PHP error log, while users receive generic error messages.

For local development, start PHP from this directory with `php -S 127.0.0.1:8000`. The prepared cloud environment instead runs PHP and MariaDB in Docker through `/workspace/.college-grading-setup/start.sh`.

## Grading and saving behavior

- Blank assessments are incomplete. A recorded zero is a real score. All seven assessments must be present before a final grade is classified as passed or failed.
- Scores and weights accept 0–100 with at most two decimal places. Every grading-weight component is required, and their total must equal 100.
- Class averages and pass rates exclude incomplete grades; the incomplete count is shown separately.
- Roster pages contain up to 50 students. Search and status filters apply to the entire class when Apply is selected. Exports include the entire class.
- Score saves are serialized. Failed or unconfirmed saves remain marked unsaved, can be retried, and trigger a navigation warning. Desktop and mobile inputs stay synchronized. After editing grades in a filtered view, Apply refreshes membership in that filter.
- Score changes record the previous and new values in the audit log. Undo is not implemented.
- Disabled or deleted accounts lose access on their next request. Login permits five attempts per account and 100 attempts per connecting IP in a 15-minute window; successful sign-in clears the account counter. A reverse proxy should supply an accurate `REMOTE_ADDR` through trusted server configuration; untrusted forwarded headers are not used.

CSV text cells that could be interpreted as spreadsheet formulas are prefixed with an apostrophe. The PDF action produces a printable HTML sheet for the browser's Save as PDF function.

## Tests

See [tests/README.md](tests/README.md). These tests use a local demo database and include temporary fixture creation and cleanup.
