# The public website (landing page)

Built in steps. **Step 1 (done): the main page** (`/`): header with five menus, eleven sections, urgent help strip and footer, plus the shared "Coming soon" page. **Not built yet: the sub pages** (they open "Coming soon"), the shared sub page templates, the real pricing pages and the public Find a Counselor directory.

The planned structure is in the owner's `landing-page-structure-spec.md` (v2, 3 November 2026). This file records what was built and how.

## Where things are

| What | Where |
|---|---|
| The main page | `resources/views/welcome.blade.php` |
| Header, footer, urgent help strip (shared by every page of the site) | `resources/views/components/landing-layout.blade.php` |
| A place for a picture or video | `resources/views/components/landing/media.blade.php` |
| The "Coming soon" page | `resources/views/landing/soon.blade.php`, route `landing.soon` (`/coming-soon/{page?}`, noindex) |
| All the words, Malay and English | `lang/ms/landing.php` and `lang/en/landing.php` (same keys, change both together) |
| Switches, links, hotline numbers | `config/landing.php` |
| Where a menu or footer link goes | `app/Support/LandingLinks.php` |
| Language choice | `app/Http/Middleware/SetLandingLocale.php` |
| Pictures and videos | `public/landing/` (see its README for the names and sizes) |
| Tests | `tests/Feature/LandingPageTest.php` |

## Rules that are built in

- **Language:** Malay by default, English with `?lang=en`; the choice is kept in the `mk_lang` cookie for a year. It only applies to the public website pages, not to the rest of the system. The footer switcher changes it.
- **Individuals** never see "Sign up": they are sent to the app (`registration.mobile_app_url`, `/#get-the-app` until the app is published). Web sign-up for individuals stays disabled (404).
- **Organization and counselor** go to their sign-up page while that door is open (Registration Control). When it is closed, and there is a waitlist, the button says "Join the waitlist" and leads to `register.type`, which shows the waitlist form.
- **Invented content** is only in these blocks: `proof_logos`, `proof_quotes`, `proof_figures` and the whole Problem section (`problem`), listed in `config/landing.php` → `placeholder_blocks`. They carry a "Sample content" tag. They are shown only while `landing.show_placeholder_content` is true (`LANDING_SHOW_PLACEHOLDER`; on in local and testing, off in production by default); when it is off the blocks disappear and the page closes up with no gap. The first proof block (credentials checked, book in a few taps, sessions not recorded) is true and always shown.
- **No made-up facts:** no real names, logos, statistics or government marks. The figures are `00+`. Nothing says the system is connected live to LKM: counselors are checked against registration records plus a human review.
- **"Soon" tag:** only on pages that are meant to stay "Coming soon" for a while (`soon_tagged`: Wellness feed, Mental health articles, Glossary, Status, Sitemap). Every other page of the planned site that is not built yet also opens "Coming soon" but has no tag, until its turn comes. A slug in `config/landing.php` → `built` opens a real page (Terms, Privacy, Help, the waitlist).
- **Contact:** `hello@mykaunsel.example` (an invented address on a reserved domain) in `LANDING_CONTACT_EMAIL`. Replace it before launch.
- **Urgent help** (HEAL 15555, Talian Kasih 15999, Befrienders by website only) is always shown, in `config/landing.php` → `urgent_help`. Check the numbers before launch.
- **Font:** Inter Tight (Google Fonts), for this website only. The portals use Geist, the rest of the system Plus Jakarta Sans.

## Pictures and videos

Every picture or video has a named slot. Put a file named after the slot into `public/landing` (for example `hero.mp4` or `hero.jpg`) and it appears by itself. Until then a dashed box shows what goes there, its shape and the file name. Videos play automatically, muted and in a loop.

## Before launch

Replace or remove the placeholder content, replace the contact address, check the hotline numbers, publish the app and enable the store badges, set `LANDING_SHOW_PLACEHOLDER=false` in production (the default), and build every "Soon" page or remove its link.
