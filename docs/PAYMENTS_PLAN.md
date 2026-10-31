# Payments: plan (nothing is built; to be decided)

> **Context (owner, 31 October 2026): MyKaunsel is a final year project (FYP) for now. There are no real users, only the developer.** Payments, taxes, licences, lawyers and accountants are looked at again only if the project becomes commercial and has real users. Until then nothing in this file needs to be built or checked.
>
> **Decision of 31 October 2026: payments for independent counselors are ON HOLD.** The owner still wants the feature, but it is looked at again only after many more functions of the system are finished. Until then **independent counselors use MyKaunsel for free**: no commission, no session price, no payment, no escrow. Everything below about independent counselors is the thinking kept for later. **Organizations (universities, corporates and clinics) stay on the yearly subscription**, which is simple: one payer, once a year, nothing to refund. Until a payment gateway exists, a subscription is **recorded by the Platform Admin and paid outside the system** (invoice and bank transfer), as the Pricing page already works today.

Nothing is charged today. [PRICING.md](PRICING.md) only records plans, prices, promo codes and a commission percentage. This page keeps the thinking about real payments so it is not repeated. Owner's order: settle every other question first, then decide payments.

## Who pays whom (corrected on 31 October 2026)

| Payer | Payee | How |
|---|---|---|
| University, corporate **and mental-health clinic** | MyKaunsel | A **yearly subscription** by member count. (PRICING.md still says clinics have no plans: that is to be changed. A clinic is an organization like the others.) |
| The members of those organizations | nobody | Their sessions are covered by the subscription. No charge per session. |
| **Independent counselor** (freelance) | MyKaunsel | The platform is a freelance marketplace for them: a **commission** on each paid session (and possibly more, see below). |
| The independent counselor's client (the public) | the counselor, through MyKaunsel | Pays per session. |

The hard part is the last row. The owner wants the money to pass through MyKaunsel (a refund is simpler when the platform holds the money).

## Subscription payment: to be built (owner, 31 October 2026)

The owner decided the **organization subscription is built with a real payment API** (in the provider's test mode, since this is an FYP), together with clinic plans and the end-date enforcement. This is the simple case: the organization pays MyKaunsel's own account, no money is held for anyone else.

### Decided for the subscription payment (owner, 1 November 2026)
- **Provider: Billplz first** (sandbox), behind a small gateway interface; Stripe is the second choice.
- **Tax on the invoice:** an invoice shows subtotal, discount, a tax line and the total, like a real one. The tax rate is a setting (starting value 0% while this is an FYP) and each invoice keeps the rate it was made with.
- **Renewal: reminders with a pay link, not an automatic charge** (60, 30, 14 and 7 days before the end). Billplz's pricing page lists an "Auto-Deduct" product (2.3% + RM1.25 cards, 2% + RM0.75 FPX on the Standard plan), but whether that is a true FPX e-mandate (direct debit) is unclear: one competitor says it is not, and Curlec is the one described as strong for e-mandates. Large organizations also tend to pay a yearly invoice through a purchase order, not by a standing charge, so automatic charging is left for later.
- **A plan change during the term** keeps the plan until the term ends.
- **Build order:** (1) clinic plans, invoices and the manual "mark as paid"; (2) the Billplz gateway, bill creation and the signed callback; (3) the organization's Subscription page, receipts and e-mails; (4) end-date enforcement with the warnings and "request renewal"; (5) documents and a last check.

### Provider research (31 October 2026)
Fees come from blogs that disagree with each other; none was confirmed on an official page. Re-check the provider's own page and test mode before building.

| Provider | What was found |
|---|---|
| **Billplz** | Bill API, a separate **sandbox** (own domain and keys) with a simulated payment page; callbacks (retried up to 5 times if the server does not answer 200) signed with **HMAC-SHA256** (`x_signature`); FPX charged a **flat fee** deducted from the credit balance; a Laravel adapter exists (jomweb/billplz-laravel, MIT, last updated 2024). The fee amount, card support and settlement schedule were not found. |
| **Stripe** (Malaysia) | Cards, FPX, GrabPay, Alipay; reported fee **3% + RM1.00** per transaction (no DuitNow QR or Touch 'n Go); settlement reported as 7 days; best documentation and an official PHP library; test mode needs no business. FPX per-payment ceiling reported as RM30,000. |
| **ToyyibPay** | Bill API and a sandbox (dev.toyyibpay.com); callback verified with an MD5 hash; reports from other developers of undocumented sandbox errors and callbacks posted as multipart. Quick to use, weaker for production. |
| **CHIP, Curlec (Razorpay), HitPay, Fiuu (Razer), Xendit** | Often quoted cheaper than Stripe (about 1% to 2%, FPX flat or about 1.8% + RM0.40); Curlec is positioned for recurring billing, Xendit for developer-first platforms with custom pricing. API quality and sandbox access not confirmed. |

### Recommendation
1. **Billplz first**, behind a small gateway interface in our code so the provider can be changed. It maps exactly to "an invoice with a pay link", the sandbox and the signature scheme are documented, and a Malaysian organization pays by FPX.
2. **Stripe second** (best documentation; dearer; fewer local methods). **CHIP or Curlec** to be compared with real quotes if the project becomes commercial.
3. **Keep a manual path next to the online one**: a large invoice (a university) can exceed what one FPX payment allows and many organizations pay by bank transfer with a purchase order, so the Platform Admin can still mark an invoice paid by hand (audited).
4. A live account normally needs a registered business; the FYP uses the sandbox only.

## Still to do for the subscription (not built)

1. Allow **clinics** to be given a plan (PRICING.md says only universities and corporates can; clinics are to be added).
2. **Enforce the end date**: bookings and times only inside the paid period, with the warnings and the "request renewal" button described below.
3. Later, when there is a payment gateway: invoices and receipts, renewal reminders, and a way to pay online.

## Decided by the owner (31 October 2026)

- **Commission is 10%, with every tax MyKaunsel has to handle included** (changed from 7% the same day, because tax had been forgotten). It is taken from an independent counselor's paid session. Not yet said: who bears the payment provider's own fee (MyKaunsel out of the 10%, the counselor, or a small service fee for the client); the accountant must say which taxes apply to the commission and what is left after them. A lower rate for a client who books the same counselor again is not decided (proposed: decide it later, with real data).
- **Refund and cancellation rules agreed** as proposed under "Proposed design" below.
- **After a subscription ends, new members may still join** an organization (code or invitation) but can not book until it is renewed.
- **Price range for a session:** none yet. The Platform Admin sets a floor and a ceiling later; the owner will say when.
- **Escrow:** explained. Nobody in government holds the money: it is held by a licensed payment provider or by a bank or trustee account. Who holds it is not decided; to ask the lawyer and two or three Malaysian providers (questions in the conversation of 31 October 2026: split payments with delayed payout to third parties, the licence under which they hold funds, onboarding of our counselors, payout schedule, refunds and chargebacks, fees for FPX, cards and e-wallets, the API and notifications, and a test environment).
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
