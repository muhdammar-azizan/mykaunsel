# Payments: plan (nothing is built; to be decided)

Nothing is charged today. [PRICING.md](PRICING.md) only records plans, prices, promo codes and a commission percentage. This page keeps the thinking about real payments so it is not repeated. Owner's order: settle every other question first, then decide payments.

## Who pays whom (corrected on 31 October 2026)

| Payer | Payee | How |
|---|---|---|
| University, corporate **and mental-health clinic** | MyKaunsel | A **yearly subscription** by member count. (PRICING.md still says clinics have no plans: that is to be changed. A clinic is an organization like the others.) |
| The members of those organizations | nobody | Their sessions are covered by the subscription. No charge per session. |
| **Independent counselor** (freelance) | MyKaunsel | The platform is a freelance marketplace for them: a **commission** on each paid session (and possibly more, see below). |
| The independent counselor's client (the public) | the counselor, through MyKaunsel | Pays per session. |

The hard part is the last row. The owner wants the money to pass through MyKaunsel (a refund is simpler when the platform holds the money).

## Decided by the owner (31 October 2026)

- **Commission starts at 7%** on an independent counselor's paid session. (Whether a lower rate for a client who books the same counselor again is wanted is still to be said.)
- **Refund and cancellation rules agreed** as proposed under "Proposed design" below.
- **After a subscription ends, new members may still join** an organization (code or invitation) but can not book until it is renewed.
- **Price range for a session:** none yet. The Platform Admin sets a floor and a ceiling later; the owner will say when.
- **Escrow:** still being explained to the owner; who holds the money is not decided.
- Payments stay on hold until every other question is settled.

## Subscription end date (decided in principle)

`organizations.subscription_ends_at` already exists and is set from the plan (one year after the start). **It is not enforced anywhere yet.** Rule from the owner: a session can only be booked **inside the paid period**, so the counselors and clients of an organization can not run the service when it has not paid. How it is delivered (what people see) is proposed in the conversation of 31 October 2026 and is to be confirmed:

- a session is bookable only if its **start** is on or before the end date (not merely the booking date);
- the counselor's Planner can not open times after the end date and says why;
- the clients of the organization simply do not see times after the end date (and the app says "your organization's access runs until …");
- warnings before the end: the organization's admins at 60, 30, 14 and 7 days (e-mail, banner, notification), the counselors at 30 and 14 days;
- sessions already booked inside the paid period go ahead;
- after the end: no new bookings, existing sessions inside the period still happen, "Request renewal" for the admin;
- a grace period (proposed: none for booking, but sessions already booked are honoured).

## Research (31 October 2026)

Mostly blog posts that disagree with each other, so the figures are indicative; check the official pages before relying on any.

- **Preply** (tutors): the tutor pays 100% of a new student's trial lesson to the platform, then 18% to 33% falling with hours taught. **italki**: about 15% (one source says it rose to 21% for individual classes). Cancellation: the tutor sets it on italki (flexible, moderate, strict); a refund is at the tutor's discretion on Preply.
- **Upwork**: a variable freelancer fee of 0% to 15% (since May 2025); fixed-price funds are held in **escrow** and released when the work is approved, with a 5 to 10 day clearing period. **Fiverr**: 20% from the seller plus about 5.5% from the buyer; clearing period 7 to 14 days.
- **Therapist platforms**: Psychology Today is a flat listing fee of about USD 30 a month with no booking or payment; Zocdoc charges a fee per **new patient booking** (about USD 35 to 110), at the moment of booking even if the patient does not come; marketplace guides quote a typical take rate of 15% to 30%.
- **Malaysia**: holding or issuing e-money is regulated by Bank Negara Malaysia (approval, licences); providers that hold funds for others have e-money and money services licences. Which local payment providers offer split payments and payouts to third parties was **not** confirmed.

## Proposed design (to be confirmed)

- **Hold the money with a licensed payment provider, not in MyKaunsel's own bank account.** The client pays at booking; the provider holds it; MyKaunsel instructs releases and refunds. This keeps the refund easy without MyKaunsel becoming the custodian. Needs a lawyer and the chosen provider to confirm.
- **Release:** after the session is completed plus a short clearing period (proposed 48 to 72 hours, so a complaint or a no-show dispute can stop it), paid out weekly in one batch, minus the commission.
- **Who sets the price:** the counselor, inside a range set by the Platform Admin (a floor and a ceiling), with online and in-person prices allowed. The client sees the full price before confirming; no hidden fee.
- **Commission:** start low (the example 10% already in the settings, up to 15%) and show it plainly to the counselor. Idea to reduce counselors moving clients off the platform: a lower commission for a client who books the same counselor again. Option later: a monthly plan for independent counselors with no commission.
- **Cancellation and refunds** (tied to the cancellation deadline that already exists): before the deadline a full refund; after it, half to the counselor and half back to the client; a no-show by the client pays the counselor in full; a cancellation or no-show by the **counselor** is an automatic full refund and counts against the counselor; a dispute holds the payout until the Platform Admin decides (through the complaints page). Not satisfied is not an automatic refund; boundary or safety problems go to a complaint.
- **Technical rules that will apply:** money in whole sen (already so), every payment action safe to repeat (the idempotency pattern exists), a ledger of every movement, signed notifications from the provider, and no card data on our side.

## To check with an accountant and a lawyer before building

Licensing for holding funds; sales and service tax on the commission and on the counselors' fees; e-invoicing (LHDN MyInvois); the Apple and Google rules for paying for real-world services inside an app (they generally allow outside payment for one-to-one services, to be confirmed at release); the provider's fees and who bears them; the counselor's identity and bank checks before a payout.
