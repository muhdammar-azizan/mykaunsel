# Pricing & Promotions (Platform Admin → Platform → Pricing & Promotions)

**There is no payment.** Nothing is charged. This page records plans, prices and promo codes and which organization is on which plan. The page says so, and so does this document. Prices shown are **examples** and **exclude tax (SST)**. Out of scope: payment gateway, invoices, receipts, proration, automatic renewal and reminders, what happens when a subscription ends, tax, and a public pricing page.

This file is built in three steps. **Step 1 (done): Plans tab, commission, database, example data.** Step 2: Promo Codes tab. Step 3: giving plans to organizations (Approve modal, "Change plan"), redemption, snapshots.

## Business rules

- Only **universities and corporates** pay, a **yearly** subscription based on member count. Clinics and independent counselors have **no plans**: their clients pay and the platform keeps a commission (one setting below, record only).
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

`php artisan db:seed --class=PricingSeeder` (part of `db:seed`, local and testing only): three University plans that cover every size (the last, "5,000+", has no upper limit) and two Corporate plans (one with no upper limit, one inactive). Every name says "(example)".

## Numbers for the Dashboard

`App\Services\PricingStats`: `activePlans()`, `subscriptionsEndingWithin(30)`. The Dashboard is not connected to them yet. (The count of active promo codes comes with step 2.)

## Security

Platform Admin only (403 for everyone else), CSRF on every action, rate limits (plan changes 20 and 30 a minute, commission 10 a minute), server-side validation of every rule above (also inside the service, so no caller can skip it), all text escaped when shown. **There is no public endpoint that checks a promo code.** Codes are applied only by a Platform Admin when assigning a plan. If a code field is ever added to a public form it needs rate limiting.
