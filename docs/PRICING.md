# Pricing & Promotions (Platform Admin → Platform → Pricing & Promotions)

**Online payment is not connected yet** (the Billplz gateway is the next step of the subscription work, see [PAYMENTS_PLAN.md](PAYMENTS_PLAN.md)). Giving an organization a plan now also makes an **invoice** (below); an admin marks it paid by hand once a bank transfer has arrived. This page records plans, prices and promo codes and which organization is on which plan. The page says so, and so does this document. Prices shown are **examples** and **exclude tax (SST)**. Out of scope: payment gateway, invoices, receipts, proration, automatic renewal and reminders, what happens when a subscription ends, tax, and a public pricing page.

This file is built in three steps. **Step 1 (done): Plans tab, commission, database, example data. Step 2 (done): Promo Codes tab. Step 3 (done): giving plans to organizations (Approve modal, "Change plan"), redemption, snapshots.**

## Business rules

- **Universities, corporates and clinics** pay a **yearly** subscription based on member count (clinics were added on 1 November 2026). Independent counselors have no plan and use MyKaunsel for free for now; the commission (one setting below, record only) is for their paid sessions later.
- **Yearly only.** The price is an integer in sen in `price_cents` (a generic column), so a billing period can be added later with a migration. Money is always integer sen, never a float (`App\Support\Money`).

## Plans (step 1)

- A plan: name, organization type (University or Corporate), minimum members, maximum members (empty = no upper limit, shown "5,000+"), yearly price, Active or Inactive.
- **Plans are never deleted.** There is no delete route, and the database refuses to delete a plan that was ever given to an organization (`restrict`). A plan can be made inactive: it is not offered when assigning, but organizations already on it keep it.
- **Active plans of the same type must not overlap** (sharing even one member count is an overlap). Checked while the plans of that type are locked, so two admins saving at the same moment cannot both pass. Inactive plans and plans of the other type are not compared.
- **Gaps are a warning, never an error.** After every create or edit the page lists the member counts no active plan of that type covers (for example "501–999"). Deactivating a plan is allowed even if it leaves a gap; the uncovered range is shown. The Plans tab also shows the current gaps under each group.
- **The type of a plan cannot change** once any organization has been given it (also after it moved to another plan: the history still points to it).
- **Editing a price** of a plan in use shows: "Existing organizations keep their current price. Only new assignments use the new price." That is true because an assignment stores a **snapshot** (price, discount, final price) when it is made.
- **Two admins editing the same plan:** every plan has a `version`. An edit says which version it saw; it is saved only with `WHERE version = seen` and the version goes up. If someone saved first, nothing is saved and the form says "Someone else changed this plan…". Saving without any change makes no new version and no audit entry.
- Limits (server side, `config/pricing.php`): at most 1,000,000 members, price above RM 0.00 and at most RM 1,000,000.00 a year, at most two decimals, maximum not below the minimum.

## Promo codes (step 2)

A promo code lowers the price that is **recorded** when a Platform Admin gives an organization a plan (step 3). Nothing is charged.

- **Code:** stored in capitals, 4 to 32 characters (A–Z, 0–9, hyphen). The form capitalises as you type and the server does it again. Unique **without regard to capitals**: "welcome10" and "WELCOME10" are the same code (unique index on the stored capitals, and a check with a clear message).
- **Type:** Percentage (a whole number from 1 to 100) or Fixed amount (RM, more than RM 0.00, at most two decimals, stored in sen).
- **Valid from / until:** typed in the admin's own timezone, stored in UTC. The start must be before the end. Times are kept to the minute: a code is valid from the **start of its first minute** to the **end of its last minute** (stored with :59 seconds).
- **Usage limit:** optional (empty = unlimited). `used_count` counts redemptions.
- **Applies to:** all, University only or Corporate only.
- **Status is worked out, never stored.** One method, `PromoCode::status()`, decides, in this order: **Disabled** (manual switch), **Used up** (limit reached), **Expired** (after the end), **Scheduled** (before the start), **Active**. The list, the status filter, the tab count, the dashboard number (`PricingStats::activePromoCodes()`) and the tests all use it.
- **After the first redemption** the code, type, value, start date and "applies to" are **locked**. Only the end date, the usage limit (never below what is already used) and the disabled switch can change. A code is "redeemed" when `used_count` is above 0 **or** an assignment points to it.
- **Actions:** Copy (to the clipboard), Edit, **Disable / Enable** (a switch on its own: editing a code never changes it; doing what is already done changes and logs nothing), and **Delete** only for a code that was never redeemed. A redeemed code can only be disabled. The database also refuses to delete a code an assignment points to.
- **Two admins editing the same code:** same version check as plans (`WHERE version = seen`, refused with a clear message when someone saved first; no change = no new version and no audit entry).
- **List:** code (monospace, with Copy), discount ("20%" or "RM 500.00"), applies to, validity, usage ("34 / 100" or "34 / Unlimited"), status; status filter with counts; 15 per page.
- **Audit:** `promo.created` (ids, type, value, dates, limit, applies to), `promo.updated` (old and new for the numeric fields and dates; `code_changed` when the code was renamed), `promo.disabled`, `promo.enabled`, `promo.deleted` (written first, in the same transaction, so it still names the code). `promo.redeemed` is written by step 3.
- **No public endpoint checks a code.** Codes are applied only by a Platform Admin when giving an organization a plan. All promo routes are inside the Platform Admin area (403 for everyone else; a test lists every route that mentions a code). **If a code field is ever added to a public form it needs rate limiting** (per address and per code) and must give the same answer for a wrong, expired or used-up code.
- **Example codes** (all start with `EX-`): one Active, one Scheduled, one Expired, one Used up, one Disabled (`PricingSeeder`).
## Giving an organization a plan (step 3)

Only **active universities and corporates** can be given a plan (clinics have none; a pending organization gets one only through Approve). Nothing is charged: an assignment is a record.

**Where**
1. **Organizations → a pending organization → Approve.** The plan is optional ("No plan for now" stays allowed). The list shows only **active plans of the organization's type**. The size the applicant stated (`estimated_size`, for example "51–200 members", "5,000+ employees"; older data "10 - 50") is read as a range (`App\Support\OrganizationSize`; "5,000+" means 5,000 up to no limit). A plan that covers **all** of that range is listed first with a **Fits** badge; one that covers only **part** is listed with **Covers part of the stated size** (never hidden); one that covers none of it is not offered. If the size is empty or unreadable every active plan is shown with "Size not stated". If no plan covers the size the form says **"No plan covers this size."** and you can still approve without a plan.
2. **Organizations → an active organization → Subscription → Change plan** (or "Choose a plan"). Same list, same preview, recorded as a **new assignment**; the old one stays in the history ("Earlier plans").

**Price preview.** Before confirming, the form asks the server (`POST …/plan-preview`, Platform Admin only) and shows plan price, discount and the price that will be recorded, the start and end date, and any warning. The preview does **not** use up a code and saves nothing.

**What is recorded** (`subscription_assignments`): organization, plan, promo code (if any), the plan price, the discount and the final price **as they were that day** (snapshots), start date, end date, and who assigned it. `organizations.current_assignment_id` points to the newest; `subscription_ends_at` is set to the end date. The end date is **one year after the start** (29 February → 28 February). Today is taken in the public timezone (`platform-settings.public_timezone`, Malaysia). **Editing a plan's price or a code later never changes an existing assignment.**

**More members than the plan's maximum** is only a warning on the organization page (and in the preview). Nothing is blocked.

**Promo code redemption**, in the **same database transaction** as the assignment and its audit entries (approving an organization with a plan is also one transaction: if the plan or code is refused the approval is undone, the email is not sent, and the form opens again with the reason):
1. lock the organization, then the plan, then the code (always in that order);
2. check the code is not disabled, not used up, not expired, not scheduled, and applies to this organization type;
3. raise `used_count` with **one conditional statement**: `UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ? AND disabled = 0 AND valid_from <= now AND valid_until >= now AND (usage_limit IS NULL OR used_count < usage_limit)`, and check that exactly one row changed (never read-then-write). If the code was used up in between, nothing is saved;
4. the discount: **percentage rounded to the nearest sen (half up)**, **fixed amount never above the price**, **final price never below RM 0.00**.

Every **assignment with a code** uses it once, including "Change plan". Changing the plan **without** a code does not use the earlier code again.

**Audit** (ids and numbers only; the target is the organization, or the code for `promo.redeemed`): `organization.plan_assigned` (the first plan) or `organization.plan_changed` (with `previous_assignment_id`), and `promo.redeemed` (organization, assignment, discount, the new `used_count`).

**Reading it back for the Organization Admin portal (not built):** `SubscriptionAssignmentService::currentSubscription($organization)` returns the plan, list price, discount, price, code, dates and whether it ended; `history($organization)` lists all assignments.

**Out of scope (and not built):** what happens when a subscription ends (no read-only period, no blocking of bookings: reported only, nothing in the code does it), renewal, reminders, invoices, tax.

### Two simultaneous redemptions of the last use

- Normal tests (SQLite): the statement above is checked in the query log, a second statement finds no room and changes nothing, and a code used up between the check and the update refuses the redemption.
- **A real parallel test** (`tests/Feature/Platform/PricingConcurrencyTest.php`): six processes redeem the last use at the same moment. It needs a database that runs transactions side by side, so it is **skipped on SQLite** ("Needs MySQL and PRICING_CONCURRENCY_TEST=1"). To run it, against a **throw-away MySQL/MariaDB database, never a real one** (the test commits its own rows and removes them afterwards, but do not point it at live data):

```
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=mykaunsel_test DB_USERNAME=root DB_PASSWORD= php artisan migrate --force
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=mykaunsel_test DB_USERNAME=root DB_PASSWORD= PRICING_CONCURRENCY_TEST=1 php artisan test tests/Feature/Platform/PricingConcurrencyTest.php
```

  (On Windows PowerShell set the variables with `$env:NAME = 'value'` first.) Exactly one process must answer OK and the other five REFUSED. It was run against MariaDB 10.4 three times with six processes and passed each time; with the locks and the conditional update removed all six succeed, so the test does detect the problem. Note that locking the plan row already makes the redemptions of one plan take turns; the conditional UPDATE is the second, independent protection.
## Commission

One percentage for sessions of clinics and independent counselors, 0 to 100 with at most two decimals. The starting value **10** is example data (`config/pricing.php`). Stored in `platform_settings` (key `pricing.commission_percent`) and changed through `PlatformSettings::setCommissionPercent()`: row locked, audit entry in the same transaction, saving the same value again changes and logs nothing. **Record only. No payments are processed.**

## Database

| Table | What |
|---|---|
| `subscription_plans` | type, name, min/max members, `price_cents`, `active`, `display_order`, `version` |
| `promo_codes` | (used from step 2) code in capitals with a unique index, type, value, validity, `usage_limit`, `used_count`, `applies_to`, `disabled`, `version`, `created_by` |
| `subscription_assignments` | (used from step 3) organization, plan, **snapshots** of the plan price, discount and final price, promo code used, start and end date, who assigned |
| `organizations.current_assignment_id` | the organization's current assignment; older ones stay as history |

`organizations.subscription_status` is **not** about billing: it is the approval status (pending, active, suspended, rejected). It was left as it is. `organizations.subscription_ends_at` is kept and will be set by step 3.

## The old `subscription_tier` text column

It held free text and was replaced by `current_assignment_id`. The migration `…_drop_legacy_subscription_tier_from_organizations` **stops with an error before changing anything** if any organization still has tier text that no plan took over, and the message lists the organization ids. To clear them in a development database:

```
php artisan pricing:map-legacy-tiers --dry-run     # see what would be mapped
php artisan pricing:map-legacy-tiers                # give each organization the plan of its own type with the same name
php artisan migrate
```

An organization with no plan of that name is listed and left alone (create the plan first, or clear the text yourself). A mapping is a normal assignment with the plan's price as it is now, no promo code, starting when the organization was approved and lasting until its old end date or one year; it is audited as a system action (`organization.plan_assigned`, source `legacy_tier_mapping`).

## Audit entries (ids and numbers only; the target of a plan entry is the plan by name)

`plan.created`, `plan.updated` (old and new price, and other numeric changes), `plan.activated`, `plan.deactivated`, `promo.created`, `promo.updated`, `promo.enabled`, `promo.disabled`, `promo.deleted`, `promo.redeemed`, `commission.updated` (category *Pricing & promo codes*), `organization.plan_assigned`, `organization.plan_changed` (category *Organizations*). Every entry is written in the same transaction as the change.

The older keys (`pricing.plan_created`, `pricing.plan_updated`, `pricing.plan_deleted`, `pricing.promo_created`, `pricing.promo_updated`, `pricing.promo_deleted`) can no longer be written. Rows that carry them are **never rewritten**; the Audit Log still shows them through `audit.legacy_actions`. There is no `plan.deleted`: plans are never deleted.

## Example data

`php artisan db:seed --class=PricingSeeder` (part of `db:seed`, local and testing only): three University plans that cover every size (the last, "5,000+", has no upper limit), two Corporate plans (one with no upper limit, one inactive) and five promo codes, one in each state, and one university already on "University Growth (example)" with the code `EX-WELCOME10` (to see that editing the plan's price later does not change what that organization was given). Every plan name says "(example)" and every code starts with `EX-`.

## Numbers for the Dashboard

`App\Services\PricingStats`: `activePlans()`, `activePromoCodes()`, `subscriptionsEndingWithin(30)` (organizations whose CURRENT plan ends in the next 30 days). The Dashboard is not connected to them yet.

## Security

Platform Admin only (403 for everyone else), CSRF on every action, rate limits (adding 20 a minute, editing and disabling 30 a minute, deleting 20, commission 10), server-side validation of every rule above (also inside the service, so no caller can skip it), all text escaped when shown. **There is no public endpoint that checks a promo code.** Codes are applied only by a Platform Admin when assigning a plan. If a code field is ever added to a public form it needs rate limiting.

## Invoices (built 1 November 2026, step 1 of the subscription payment)

Giving an organization a plan (**Approve** with a plan, or **Change plan**) makes an invoice **in the same transaction**: if the invoice can not be made, the plan is not given either.

- **Lines:** the plan price, the discount from the promo code, then one tax line on what is left (name and rate from the invoice settings), then the total. Money is whole sen; tax is rounded to the nearest sen, half up (`InvoiceService::tax`).
- **Snapshots:** the invoice keeps who sold (name, address, SSM, tax number, bank account), who was billed (name, SSM, type), the tax name and rate, the due days and every amount. Changing a plan, a setting or an organization later never changes an invoice.
- **Number:** `INV-<year>-<n>` (for example `INV-2026-0001`), the number restarting every year; the year's counter row is locked while a number is taken. One invoice per assignment (a unique key).
- **Dates:** issued today and due after the invoice setting `due_days` (14), by the **Malaysian** calendar (the application clock is UTC). An unpaid invoice past its due date shows as **Overdue** (the day itself is not late). Overdue is only a label for now: it does not stop anything.
- **A total of RM 0.00** (a 100% promo code) is paid at once with the method `no_charge`.
- **Marking paid** (Platform Admin, a bank transfer that has arrived): the bank reference (required) and the day the money arrived (not in the future). **Voiding** (a mistake): a reason; only an unpaid invoice; the plan the organization was given does not change. A paid invoice can not be voided (that would be a credit note, not built). Both lock the invoice, so two admins at once can not both succeed.
- **Pages:** *Invoices* in the menu (tabs Open, Overdue, Paid, Void, All, with counts and a search by number or organization); one invoice as a document with the Payment, Cancel and History panels; on an organization's page the latest five invoices and **Issue invoice for the current plan** (for a plan given before invoices existed); *Pricing & Promotions > Invoice settings* for the tax name and rate (0% while this is a final year project), the due days, the seller and the bank account.
- **Audit** (ids and amounts only, never the bank details or the reference): `invoice.issued`, `invoice.marked_paid`, `invoice.voided`, `invoice.settings_updated` (which settings changed, and the old and new tax rate and due days).
- **Not built yet (next steps):** the Billplz gateway and the pay link, the organization's own Subscription page and receipts, the e-mails, and enforcing the end date. See PAYMENTS_PLAN.md.
