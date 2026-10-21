# Audit log

Append-only record of who did what, to what, and when. Foundation for the Platform Admin → Audit Log page.

## Writing an entry

Always through the one service (never `AuditLog::create` by hand):

```php
use App\Facades\Audit;

DB::transaction(function () use ($org, $admin) {
    $org->transitionTo(OrgStatus::Active, [...], OrgStatus::Pending);

    Audit::log('organization.approved', $org, [
        'changes' => ['status' => ['old' => 'pending', 'new' => 'active']],   // old → new for changes
    ], $admin);
});
// emails / notifications AFTER the commit: DB::afterCommit(fn () => ...)
```

`Audit::log($action, $target = null, array $metadata = [], User|'system'|null $actor = null, array $options = [])`

- **Action keys** live in `config/audit.php` (`area.event`, e.g. `organization.approved`). An unknown key throws, so typos are caught by tests. Add the key (category, badge, label) when you build the page that produces the event.
- **Actor** is filled in automatically: the signed-in user, `"system"` for the scheduler / queue / console, or `guest` for an unauthenticated web request. Use `Audit::asSystem(fn () => ...)` to force `"system"` (the DNS command does).
- **Transaction rule:** `log()` does not open its own transaction. Call it inside the same `DB::transaction` as the action, so both succeed or both roll back. Do slow network work (DNS lookups, HTTP calls) **before** the transaction.
- **`Audit::tryLog()`** reports and swallows a failure. Only for sign-in events (`auth.*`), which must never block the user. Admin actions use `log()`.
- `options`: `organization_id`, `target_name`, `target_type` + `target_id` (for a target that no longer exists as a model), `at` (seeders only).

## What an entry holds

time (UTC) · actor type (`user` / `system` / `guest`), id, name + role copy · action + category · target type, id, name copy · organization id · metadata · IP, user agent, request id.

- **Request id:** `AssignRequestId` middleware gives each request a UUID (`X-Request-Id` response header; client-sent ids are ignored). Console runs use one id per process.
- **Name copies:** only for staff (platform admin, org admin; counselor for administrative actions, not sign-in). Everyone else is stored as a bare user id and the user agent is dropped for non-staff sign-ins. The app promises personal data is removed when an account is deleted; with ids only, the rows become anonymous on their own.
- **Targets** are stored with a short type key (`organization`, `user`, …, see `audit.target_types`) and a copy of their name, so entries stay readable after a rename or delete.
- **No foreign keys** on `actor_user_id` / `organization_id`: deleting a user or organization never rewrites an audit row.

## Metadata rules

Stripped automatically: password / token / secret / recovery / two-factor / cookie / credential keys, and keys that would carry chat or complaint text (`message`, `content`, `body`, `text`, `chat`, `description`, …). Store **reference ids**, not content. Long strings are cut at 2000 characters, nesting at depth 5.

## Immutability

`AuditLog` rows cannot be updated or deleted through the model or the Eloquent query builder (`update`, `delete`, `forceDelete`, `truncate`, `upsert`, `increment`, `saveQuietly`, …); they throw `AuditLogImmutable`. Inserts and reads work normally, and `migrate:fresh`, seeders and test teardown are unaffected.

**Deferred decision:** raw `DB::table('audit_logs')` access is outside what application code can police. On the production database add triggers that reject `UPDATE` / `DELETE` on `audit_logs` (and restrict the app's DB user accordingly). Not done yet — do it with the real server.

## Sign-in activity (`auth` category)

Recorded for **all** users (`auth.login`, `auth.login_failed`), best-effort (a logging failure is `report()`ed, never silent, and never blocks sign-in).

- The typed password is never read. For a failed attempt: the user id if the email matches an account, otherwise only an HMAC of the email (people sometimes type a password into the email field).
- Failed sign-ins are rate limited per IP (`audit.login_failed_per_ip_per_minute`, default 10 a minute); extra attempts are counted in the cache key `audit:login_failed_suppressed`, not stored.
- The Audit Log page hides the `auth` category by default ("Sign-in activity" filter shows it).

## Retention — decision to review before launch

**Nothing purges this table**, and no scheduled job may. The retention period for `auth` entries (high volume, personal data: id + IP) is a conscious open decision: agree a period and a safe archiving approach *before the real launch*.

## Legacy rows

The migration `2026_10_22_000001_extend_audit_logs_table` converts rows written by the Account page (`password_changed` → `account.password_changed`, class names → short target types, etc.) once, using `DB::table()` before the immutability rules apply. Keys not in its map are left untouched; `AuditCatalogue::describe()` gives them a readable fallback label so no row renders broken.

## Sample data

Seeded entries (`OrganizationReviewSeeder`, later the Audit Log seeder) are fake and carry `"seeded": true` in their metadata. Recent-approvals and similar widgets read only real entries going forward; there is deliberately no back-filled history.

## Events not yet recorded

Only events from pages that exist are recorded today (organizations, account security, sign-in, DNS). Counselors, LKM, complaints, moderation, settings, pricing, admin invites, organization-admin member actions and the system jobs (booking-request expiry, chat purge, PA-expiry suspension) are already in the catalogue; each page adds its `Audit::log()` calls when it is built.
