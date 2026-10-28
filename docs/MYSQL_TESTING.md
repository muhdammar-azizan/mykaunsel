# Running the tests on MySQL / MariaDB

The normal test run uses **SQLite in memory** (fast). The real server uses **MySQL**. The two behave differently in small but real ways, so **run the whole suite on MySQL before every deploy**.

## One command (repeatable)

```
bash tool/test-on-mysql.sh                                   # whole suite
SUITE_ARGS="tests/Feature/Platform" bash tool/test-on-mysql.sh   # only some tests
```

(Git Bash on Windows, Linux or macOS.) The script starts its **own temporary MySQL/MariaDB server** on port 3399 with its own temporary data folder, creates the database `mykaunsel_test`, runs

1. `php artisan migrate:fresh --seed` **from an empty database** (what a new server does, plus the demo data), then
2. `php artisan test` (the whole suite),

and stops and deletes the server when it ends. Nothing of your own databases (XAMPP's included) is touched. `MYSQL_BIN` is the folder with `mysqld`, `mysql`, `mysqladmin` (default `/c/xampp/mysql/bin`; for another install set it, e.g. `MYSQL_BIN=/usr/bin`). It takes about 30 minutes.

**Do not run two test runs from the same checkout at the same time** (for example this one and a normal `php artisan test`): they share `storage/framework/testing` (the fake file disks) and wipe each other's files, which shows up as strange failures in the counselor document tests.

## Doing it by hand, against a MySQL server you already have

Use a database whose name contains `test`. **The tests delete everything in the database they run on.** `tests/TestCase.php` refuses to run on any non-SQLite database whose name does not contain "test", before anything is touched.

```
export DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=mykaunsel_test DB_USERNAME=root DB_PASSWORD=
APP_ENV=local php artisan migrate:fresh --seed --force
php artisan test
```

(PowerShell: `$env:DB_CONNECTION='mysql'` and so on.) Leave `APP_ENV` alone for `php artisan test` (the tests set it themselves).

## What runs only on MySQL

`tests/Feature/Platform/PricingConcurrencyTest.php`: six **processes** redeem the last use of a promo code at the same moment; exactly one must succeed. SQLite cannot run two transactions side by side, so it is **skipped** there (the skip message says why) and **runs by itself** on MySQL. `tests/Feature/DatabaseCompatibilityTest.php` has four more "MySQL only" tests (below).

## Where SQLite and MySQL differ (and what we did about it)

| Difference | What we do |
|---|---|
| **Quoting of names in SQL**: SQLite `"table"`, MySQL `` `table` ``. A test that looks at the SQL text only passed on one. | Tests use `TestCase::plainSql()` (quotes removed). Six tests were fixed this way. |
| **Timestamps and timezone**: MySQL converts `TIMESTAMP` columns with the *session* timezone; the server's own zone (or daylight saving) could shift stored times. | The connection is pinned to UTC (`config/database.php`, `DB_TIMEZONE`, default `+00:00`). A MySQL-only test checks `@@session.time_zone` and the stored moment. |
| **Strict mode**: SQLite silently stores a too-long text or a negative number in an unsigned column. | `strict => true` (MySQL refuses). Validation catches these first; a MySQL-only test proves MySQL refuses if validation were skipped. |
| **Case**: MySQL's default collation (`utf8mb4_unicode_ci`) compares text without regard to capitals (and accents); SQLite is case-sensitive. | Emails are looked up with `lower(email)`; promo codes are stored in capitals and are unique in both; a MySQL-only test checks a lower-case duplicate is refused by the index. |
| **Unique index and NULL**: both allow many NULLs. | Used on purpose (`admin_invitations.active_email`, `promo_codes`, `lkm_review_requests.pending_for`); a test covers it on both databases. |
| **`timestamp` columns that are NOT NULL** (`Invalid default value`): MySQL/MariaDB refuse, SQLite does not. | **A real bug found this way:** the admin invitations table used `timestamp`; it is now `dateTime`. |
| **Index size on utf8mb4**: 4 bytes a character, so a unique index on a 255 character column is 1020 bytes. | Fine on MySQL 5.7+/8 and MariaDB 10.3+ (InnoDB, DYNAMIC rows, limit 3072). **Do not use older servers** (767 byte limit). A test fails if any column is long enough to break the limit. |
| **Decimals and money**: MySQL rounds a decimal on insert; SQLite keeps a float. | Money is **whole sen** in integer columns; percentages are rounded in PHP with whole-number arithmetic (`SubscriptionAssignmentService::discount()`). The two decimal columns that exist (location coordinates, an emotion score) are not used for money. |
| **Four-byte characters** (emoji) | utf8mb4 end to end; a test stores and reads one. |
| **`enum` columns**: MySQL enforces the list. | There are none (statuses are strings checked in PHP). |
| **`ONLY_FULL_GROUP_BY`** (MySQL 8 default) | Laravel's `strict => true` turns it on for every connection, so the whole suite runs with it. |
| **Real locks** (`lockForUpdate`) | SQLite has no row locks. The promo-code race test is the proof on MySQL. |

## Servers tested

MariaDB 10.4 (the one in XAMPP). The suite has not been run on MySQL 8 itself; MariaDB and MySQL differ in small ways (for example JSON, `explicit_defaults_for_timestamp` is *stricter* on MariaDB 10.4, which is how the `timestamp` bug was found). If the server will be MySQL 8, run the script once against it too (`MYSQL_BIN` pointing at a MySQL 8 install).

## Before every deploy

1. `bash tool/test-on-mysql.sh` is green (migrate:fresh --seed and the whole suite).
2. On the server: `php artisan migrate --force` (never `--seed`), then `php artisan platform:create-admin` the first time.
3. A queue worker and `* * * * * php artisan schedule:run` are running (emails and the scheduled clean-ups need them).
