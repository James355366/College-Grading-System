# Regression checks

Run from `college-grading-system-php/fixed-grading-system` with the migrated PHP application and seeded local development database running. Set `GRADING_TEST_URL` if using an address other than the default internal server on port 8000.

```sh
php tests/grading.php
python3 tests/score_save.py
python3 tests/security.py
```

In the prepared cloud environment, use `docker exec college-grading-web php /app/tests/grading.php` for the PHP check.

`score_save.py` needs the original teacher demo account and class/student IDs. It temporarily changes three scores and restores them in a `finally` block. `security.py` creates a temporary teacher, class, and students, then deletes them in its cleanup block. These tests must not run against production. An interrupted process may require manual cleanup. Audit entries and expiring login-throttle buckets remain as records of the requests.

The tests capture class and administrator HTML at `/tmp/grading-class-test.html` and `/tmp/grading-admin-test.html`. Run both Python checks before the DOM checks:

```sh
npm install --cache /tmp/grading-npm-cache --prefix /tmp/grading-dom-tests --no-audit --no-fund jsdom@30.1.2
NODE_PATH=/tmp/grading-dom-tests/node_modules node tests/roster.cjs
```

jsdom requires Node 22.22.2+, Node 24.15+, or Node 26+. The tests were run on Node 24.19.

Coverage includes grade boundaries, incomplete versus zero scores, CSV formulas and Unicode, API/export agreement, ownership checks, strict input validation, duplicate student IDs, pagination/search, account revocation, login throttling, safe attribute data, modal initialization, weight controls, table column counts, save serialization, stale responses, offline retry, navigation warnings, and desktop/mobile synchronization. DOM tests execute the actual page scripts but do not render a browser layout or verify assistive technologies.

The migration was tested twice against the prepared database to verify repeatability and score preservation.
