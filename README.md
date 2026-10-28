# Sofor - Trip Cost Splitter

Sofor is a Laravel + Tailwind CSS + Vite + MySQL web app that keeps an honest money
ledger for group trips. Any traveller can log a cost, invite the rest of the group,
pay money in as many times as they need, and everybody sees the split update live.

Domain: **sofor.io** - a Solutor product. Slogan: *Build Your Digital Legacy*.

---

## What is inside

| Feature | Where it lives |
| --- | --- |
| Futuristic public homepage (hero, features, how it works, FAQ) | `resources/views/home.blade.php` |
| Register / login with **email or phone**, confirmed by a link or a texted code; password reset by email or text | `app/Http/Controllers/Auth/*`, `app/Services/PhoneCodes.php`, `app/Support/Phone.php` |
| Trips with cover style, emoji, dates, budget and travellers | `app/Http/Controllers/TripController.php` |
| Automatic split - ৳5000 across 5 travellers is ৳1000 each, to the paisa | `app/Services/SplitService.php` |
| Per cost: reason, amount, category, date, payer, who shares it, custom weights | `app/Services/ExpenseService.php` |
| **Direct payments**: travellers pay each other on their own bKash, Nagad, Rocket, Upay or bank; the receiver confirms | `TripSettlementController`, `ContributionService`, `PayoutMethodController` |
| **Reminders and pay links**: in-app, email and (paid from the sender's SMS wallet) text; `/p/{token}` for travellers without an account | `app/Services/PaymentReminders.php`, `PayLinkController` |
| **SMS wallet**: prepaid at ৳0.45 a text, topped up with bKash from ৳10 | `app/Services/SmsWallet.php`, `SmsWalletController` |
| **Budgets**: per person or per group, live meter, pace forecast, alerts at 80% and 100%; cost estimates from finished trips to the same place | `app/Services/BudgetAlerts.php`, `app/Services/TripEstimator.php` |
| **Itemised bills** (VAT and service spread in proportion) and **receipt photos** (private, EXIF stripped) | `ExpenseService`, `app/Services/ReceiptService.php`, `ReceiptController` |
| **Trip pot**: collect an advance per person, pay shared costs from it, leftovers come back in the settle up | `app/Services/TripPot.php`, `TripCollectionController` |
| **Polls and RSVPs** inside a trip | `TripPollController`, `resources/views/trips/partials/plan.blade.php` |
| **Checkpoint list**: encrypted ID details for hill-tract checkpoints and hotels, printable PDF, purged 14 days after the trip | `TripManifestController`, `PurgeCheckpointLists` |
| **Check-ins and family links** (`/f/{token}`), optional texts to family from the SMS wallet | `TripSafetyController`, `FamilyLinkController` |
| **Trip recap card**: a 1080 x 1350 PNG made in the browser, no names on it | `TripRecapController`, `resources/views/trips/partials/recap-svg.blade.php` |
| **Balances across trips** per friend, settled with one payment (netted, confirmed as one batch) | `app/Services/CrossTripBalances.php`, `BalancesController` |
| **Offline mode**: service worker for pages, IndexedDB queue for costs, idempotent by `client_uuid` | `public/sw.js`, `resources/js/offline.js` |
| **English and Bangla** (`lang/bn.json`), per account, per visit or from the browser | `SetLocale`, `LocaleController` |
| Dues, credits and the shortest set of transfers to zero | `app/Services/TripLedger.php` |
| Invite by phone/email - invites are claimed automatically at sign up | `app/Services/TripInvitationService.php` |
| Profile photos - redrawn as a square WebP (EXIF stripped) and served from the private disk | `ProfileController@updateAvatar`, `app/Services/AvatarService.php` |
| Platform logo and favicon, uploaded from *Admin -> Settings -> Branding*; used by every page, the web app manifest and trip PDFs | `app/Services/BrandingService.php`, `BrandingController` |
| Maintenance mode from *Admin -> Settings -> Maintenance*: everyone but admins sees the maintenance page | `app/Services/MaintenanceMode.php`, `CloseSiteForMaintenance` |
| Branded error pages: 404, maintenance (503) and every other 4xx / 5xx | `resources/views/errors/*` |
| Travel map: Bangladesh by district, the world by country, suggested from trips, downloadable as an image | `TravelMapController`, `app/Services/TravelMaps.php`, `resources/js/travel-map.js` |
| Usernames (picked on sign up, changeable in settings) and public profiles at `/@username` with the travel maps | `app/Support/Username.php`, `PublicProfileController` |
| Explorers (`/explorers`): public profiles ranked by districts, then countries, visited; podium, your rank, community heat map | `ExplorerController`, `app/Services/ExplorerRanking.php` |
| Trip leader can end a trip; costs and settings lock afterwards | `TripController@end`, `TripPolicy` |
| Real time updates over server sent events with a polling fallback | `TripController@stream`, `resources/js/trip-live.js` |
| Traveller dashboard and admin console with charts | `resources/views/dashboard.blade.php`, `resources/views/admin/*` |

## Requirements

- PHP 8.2+ (XAMPP is fine) with `pdo_mysql`, `mbstring`, `openssl`
- MySQL 8 / MariaDB
- Node 18+ and npm

## Setup

```bash
composer install
npm install
cp .env.example .env          # then set your database credentials
php artisan key:generate
php artisan migrate
npm run build                 # or: npm run dev
php artisan serve --no-reload
```

Then open `http://127.0.0.1:8000`.

Environment values that matter:

```dotenv
APP_NAME=Sofor
APP_TIMEZONE=Asia/Dhaka
DB_CONNECTION=mysql
DB_DATABASE=sofor
DB_USERNAME=root
DB_PASSWORD=

# Live updates. Leave false behind `php artisan serve` (the single threaded PHP
# server would block on an open stream), set true behind Apache/nginx/FPM.
SOFOR_SSE_STREAMING=false

# Multiple workers so the stream never blocks other requests
PHP_CLI_SERVER_WORKERS=8
```

### Running it under XAMPP

Point a vhost at the `public` directory:

```apache
<VirtualHost *:80>
    ServerName sofor.test
    DocumentRoot "E:/Xampp/htdocs/Projects/Sofor/public"
    <Directory "E:/Xampp/htdocs/Projects/Sofor/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Apache serves each request on its own worker, so set `SOFOR_SSE_STREAMING=true` there to
use the realtime stream instead of polling.

## Accounts and data

**There is no demo data and no seeded credentials.** A fresh install starts empty and
every number you see comes from real records.

The **first account that registers becomes the administrator**, which is how you reach
the console at `/admin`. Promote or pause anybody else from *Admin -> Users*.

Admin accounts are **console only**: they sign in to `/admin`, any traveller page sends them
back there, and trips are read from *Admin -> Trips* (a read only ledger). Travellers get a
403 on `/admin`. To plan trips yourself, register a second, ordinary account.

Every account has a **username** (3-30 lowercase letters, numbers or underscores, at
least one letter; a few names like `admin` or `sofor` are reserved). It is picked on sign
up, can be changed in the profile, and is the address of the traveller's **public
profile**, `/@username`: name, photo and travel maps, never email, phone, trips or money.
Profiles are public by default, can be made private from the profile page, and are
`noindex` so they travel by link rather than by search engine. Admin accounts have no
public profile. Accounts that existed before usernames were given one from their name.

Need local fixtures? Write your own seeder in `database/seeders` and run it explicitly;
`DatabaseSeeder` is intentionally empty so nothing fake ever lands on a live page.

## How the money maths works

1. **Expenses** record who spent money for the group, and who shares that cost. Amounts
   are handled in cents, so `৳5000 / 3` becomes `1666.67 / 1666.67 / 1666.66` and never
   loses a poisha (largest remainder method in `SplitService`).
2. **Contributions** record money that actually changed hands: any traveller can pay in
   any amount, as many times as they like. Every entry is summed into the trip total and
   applied to the balances of the whole group.
3. **Balances** are `what you paid for the group - your share + what you paid in - what
   you received`. If you do not pick a recipient, Sofor routes your contribution to the
   traveller who has fronted the most money, so it always clears a real due.
4. **Settle up** suggests the shortest set of transfers that gets everybody to zero.
5. **Ending a trip** locks the ledger: no new costs, no split changes. Contributions can
   still be recorded because people settle up after the trip.

## Travel maps

`/map` shows every district (Bangladesh, 64) and country (world, 195 = UN members plus
the two observer states) a traveller has been to. Areas are stored by ISO code in
`visited_places`, so the data never depends on a boundary file's own spellings.

- Tap an area on the map or tick it in the list. Marking a district also marks its
  country on the world map.
- Trips suggest areas: their title, destination and notes are matched against area names
  and `resources/maps/places/<map>.php` (old spellings, Bangla names, places like Sajek or
  Kuakata), and trip currency hints at the country. The trip page offers the same.
- *Download image* draws a 2x PNG in the browser, in one of three colour themes.

The maps are generated, not drawn by hand. To add another country's provinces or states:

1. Add an entry to `scripts/maps/maps.config.mjs` (source file, projection, how to read
   each feature into an ISO 3166-2 code and group).
2. Run `npm run maps:build` (or `npm run maps:build -- <key>`) and commit the new
   `resources/maps/<key>.json`.
3. Add the key to `config/travel_maps.php` with its labels and `country` code.
4. Optionally add `resources/maps/places/<key>.php` so trips can suggest it.

`tests/Feature/TravelMapTest.php` checks every map and every places entry, so a typo in a
code fails the build. Boundary sources: geoBoundaries / Bangladesh Bureau of Statistics
(CC BY 3.0 IGO, credited on the page) and Natural Earth (public domain).

## Verification and password reset

A new account confirms the email or phone it signed up with before the traveller app opens
(`EnsureContactIsVerified`, alias `verified.contact`). One confirmed contact is enough; the
other can be confirmed later from the profile.

- **Email**: a signed link (`verification.verify`) sent through SMTP, valid for 24 hours
  (`auth.verification.expire`). It works on any device, signed in or not, and stops working
  if the address changes. The emails use the Sofor theme in
  `resources/views/vendor/mail/html/themes/sofor.css`.
- **Phone**: a 6 digit code texted through [sms.net.bd](https://sms.net.bd)
  (`app/Services/SmsService.php`). Codes are stored as a keyed hash, work for 10 minutes and
  allow 5 tries; a number gets at most one text a minute and five an hour.
- **Trip invites** only ever match a confirmed contact, so signing up with somebody else's
  email or number wins none of their invites. Pending invites are picked up the moment a
  contact is confirmed.
- **Password reset** (`/forgot-password`): a link by email, or a texted code by phone. The
  answers never say whether an account exists, and a reset signs the account out everywhere.
- Accounts created before verification existed were marked as confirmed by the
  `mark_existing_contacts_as_verified` migration.

Settings: `MAIL_*` (Hostinger: `MAIL_SCHEME=smtps`, `MAIL_HOST=smtp.hostinger.com`,
`MAIL_PORT=465`), and `SMS_DRIVER=smsnetbd` with `SMS_NET_BD_API_KEY` (optionally an approved
`SMS_NET_BD_SENDER_ID`). `MAIL_MAILER=log` and `SMS_DRIVER=log` write messages to
`storage/logs` instead of sending them, which is what the tests use. Set `APP_URL` to the real
site address in production, since email links are built from it.

## SEO

- Every page gets its title, a description (its own, or its subtitle), a canonical URL,
  Open Graph and Twitter card tags, and the 1200x630 share image `public/images/og/sofor.png`
  (`components/head.blade.php`). Pages pass `canonical`, `image` and `schema` through
  `x-layouts.site`.
- The home page carries JSON-LD for the Organization, WebSite, WebApplication (free) and its
  FAQ, built from the same list the page shows.
- `/robots.txt` and `/sitemap.xml` are routes (`SeoController`), so they always use the real
  site address. Outside production robots.txt blocks everything, so staging is never indexed.
- Kept out of search on purpose: the traveller app, admin, verification and reset pages,
  trip invite previews (they show a trip's money) and public profiles (a person's name and
  travels - they still preview when shared, with the traveller's photo).
- Icons: `favicon.ico`, `favicon.svg`, `icons/apple-touch-icon.png` and the 192, 512 and
  maskable app icons, all drawn from the Sofor mark.

In production set `APP_URL` to the live https address: canonicals, the sitemap and share
links are all built from it.

## Security

- **Headers** (`SecurityHeaders`, on every response): `X-Frame-Options: SAMEORIGIN` and
  `frame-ancestors 'self'` against clickjacking, `nosniff`, `strict-origin-when-cross-origin`
  referrers, a restrictive `Permissions-Policy`, and HSTS over https. The CSP does not limit
  scripts, because Alpine evaluates expressions; user data is kept out of scripts instead.
- **Output**: Blade escapes everything; values inside Alpine attributes or `onsubmit`
  handlers always go through `@js()`, never `'{{ }}'` or `addslashes`. Names in emails pass
  through `MailName`, so they cannot carry Markdown links.
- **CORS**: none. No route sends `Access-Control-Allow-Origin`, so other sites cannot read
  Sofor's JSON. **CSRF**: on for every web route, with no exceptions.
- **Who may change money records** (`TripPolicy`): a cost by its author or the leader; a
  payment record by its recorder or the leader, and travellers only record payments they
  made or received; bKash-verified payments are never deleted, and a trip that has them
  cannot be deleted by its leader (end it instead). The currency locks once money is logged.
- **bKash**: a payment is only recorded for the traveller who started it, after bKash
  confirms the status, the amount asked for, and a transaction id not seen before.
- **Accounts**: `role`, `is_active` and the verified timestamps are not mass assignable;
  pausing an account signs it out on its next request (`SignOutPausedAccounts`); a password
  change or reset signs out every other session. Session cookies are `secure` when
  `APP_URL` is https.
- **Rate limits** on sign in, sign up, password reset, verification, invites, payments,
  PDF export, the live trip stream and the public invite preview.

In production set `APP_ENV=production` and `APP_DEBUG=false`.

## Media storage (Cloudflare R2)

Uploaded media goes to Cloudflare R2 when `MEDIA_STORAGE=r2` is set, through R2's S3
compatible API (`league/flysystem-aws-s3-v3`). Disks are chosen in `config/media.php`:

| Media | Disk | Bucket | Served |
| --- | --- | --- | --- |
| Sponsor logos | `r2` | `R2_BUCKET` (public) | straight from `R2_PUBLIC_URL` |
| Profile photos | `r2_private` | `R2_PRIVATE_BUCKET` (private) | streamed by `AvatarController`, which decides who may see each photo |
| Platform logo, favicon | `local` | - | always local, never moved |

- Without `R2_PRIVATE_BUCKET`, profile photos stay on the local disk.
- With `MEDIA_STORAGE=local` (the default, and always in tests) everything stays in
  `storage/app/private`.
- Move files that are already local with `php artisan media:move-to-r2`. It reads each
  file back from R2 before deleting the local copy, never touches files nothing points
  at, and is safe to run again. `--dry-run` lists what would move; `--keep-local` keeps
  the local copies.
- `R2_PUBLIC_URL` can be the bucket's `r2.dev` address or a custom domain connected in
  Cloudflare. `r2.dev` is rate limited and meant for development, so use a custom domain
  in production.

## Realtime updates

`GET /trips/{trip}/stream` is a server sent events endpoint that watches a lightweight
fingerprint (`Trip::activityStamp()`) and tells the client when anything changes. The
client then pulls `GET /trips/{trip}/live`, which returns freshly rendered HTML fragments
(`summary`, `members`, `feed`, `settle`, `breakdown`, `activity`) and patches them into
the page. If streaming is unavailable - or the server cannot hold the connection - it
falls back to polling every 10 seconds automatically.

## Paying with bKash

Trip money never goes through Sofor. Each traveller lists where they want to be paid
(bKash, Nagad, Rocket, Upay or a bank account), friends pay them directly, and the
receiver confirms. Sofor's own bKash merchant account is only used for money that is
meant for Sofor: **SMS wallet top-ups** (`/wallet`, ৳10 minimum, exactly the amount picked)
and **support** (`/support`).

### Two API generations

bKash ships two Checkout (PGW) API generations and a merchant account is on one of them.
Sofor speaks both and picks the right one from the API host:

| Generation | Host | Endpoints |
| --- | --- | --- |
| legacy | `checkout.pay.bka.sh` / `checkout.sandbox.bka.sh` | `/checkout/token/grant`, `/checkout/payment/create`, `/checkout/payment/execute/{paymentID}` |
| tokenized | `tokenized.pay.bka.sh` / `tokenized.sandbox.bka.sh` | `/tokenized/checkout/token/grant`, `/tokenized/checkout/create`, `/tokenized/checkout/execute?paymentID=` |

Only the **tokenized** API returns the hosted payment page (`bkashURL`) with the payment.
Legacy accounts get a **Checkout js link** instead, and bKash's Checkout JS renders the
payment page inside our own page:

| Mode | Used by | How the traveller pays |
| --- | --- | --- |
| `browser` | legacy accounts (default) | bKash's Checkout JS opens the payment overlay in the page; Sofor never navigates away |
| `redirect` | tokenized accounts | Sofor redirects to the `bkashURL` bKash returned |

The browser flow needs no extra setting. `redirect` on a legacy account needs
`BKASH_CHECKOUT_URL` (the Checkout URL from the merchant portal) because that API does
not return a page; without it the bKash buttons stay hidden and `/support` explains why
instead of failing at the last step.

### The browser flow, step by step

1. The page loads `BKASH_SCRIPT_URL` (only when a payment is about to start).
2. `bKash.init()` gets two Sofor endpoints, and bKash's flow starts on a click on a
   hidden `#bKash_button` element.
3. `POST /wallet/bkash/create` (or `/support/bkash/create`) calls bKash Create Payment
   and answers with the `paymentID`; the page hands it to `bKash.create().onSuccess()`.
4. bKash renders the payment page in an overlay at `client.pay.bka.sh`.
5. Once the traveller authorises, `POST .../bkash/execute` runs bKash Execute Payment and
   credits the wallet (or records the gift), then the overlay closes and the page refreshes.

Every amount is recomputed on the server at both steps, so the browser can never choose
what it pays, and the payment id is matched against the top-up or donation it was created
for before anything is recorded.

### Setup

```dotenv
BKASH_BASE_URL=https://checkout.pay.bka.sh/v1.2.0-beta   # or the tokenized host
BKASH_API=auto                    # auto | legacy | tokenized
BKASH_MODE=browser                # browser | redirect
BKASH_SCRIPT_URL=https://scripts.pay.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout.js
BKASH_USERNAME=                   # issued by the bKash merchant portal
BKASH_PASSWORD=
BKASH_APP_KEY=
BKASH_APP_SECRET=
BKASH_CHECKOUT_URL=               # redirect mode on a legacy account only
BKASH_MFS_CHARGE_PERCENT=1.5
BKASH_SANDBOX=false
BKASH_TIMEOUT=30
BKASH_INTENT_TTL=120
```

Then check it with the built in doctor - it probes every known endpoint, names the one that
answers, and with `--create` it also creates a real (unpaid, expiring) payment intent and
reads its status back:

```bash
php artisan bkash:check
php artisan bkash:check --create --amount=100
```

How a payment flows:

1. `POST {prefix}/token/grant` grants an access token (cached for 50 minutes).
2. `POST {prefix}/.../create` returns a `paymentID` (plus `bkashURL` on the tokenized API).
   The traveller is sent to the hosted bKash page; the intended amount is remembered for
   `BKASH_INTENT_TTL` minutes.
3. bKash redirects the browser back to `GET /trips/{trip}/bkash/callback` (or
   `GET /support/callback`), Sofor executes the payment and records a contribution with the
   bKash `trxID` as the reference.

Safety rails: the trip intent is pulled from the cache and donor rows are matched by
`payment_id`, so a callback can never be replayed; transaction IDs are unique in the
database; a cancelled or declined payment records nothing; and the amount that clears the
due is recomputed server side from the stored intent - never trusted from the query string.

Notes:

- A rejected `token/grant` means the credentials themselves were refused. Locally the
  gateway's own message is appended to the error toast so it is obvious what to fix.
- The redirect back to `127.0.0.1` works locally because bKash redirects the traveller's
  own browser. `php artisan serve` handles it as long as the site is running.

## Scheduled jobs

Two daily jobs run from the Laravel scheduler (`routes/console.php`), at Dhaka time:

| Command | When | What |
| --- | --- | --- |
| `sofor:remind-dues` | 10:00 | Reminds travellers who still owe on ended trips: two days after, then weekly, four times at most |
| `sofor:purge-checkpoint-lists` | 03:30 | Deletes checkpoint-list ID details 14 days after a trip ends |

On the server, add one cron entry: `* * * * * cd /path/to/sofor && php artisan schedule:run >> /dev/null 2>&1`.
While developing, `php artisan schedule:work` runs them in the foreground.

## Mobile experience

Sofor is built phone first: a native feeling bottom tab bar with a floating add button,
bottom sheet modals, safe area padding and an installable web app manifest.

- The mobile header is a compact bar: menu, truncated title and subtitle, page action
  and alerts. Desktop keeps the avatar and theme toggle.
- Everything that does not fit in the bottom tab bar lives in the slide in side menu:
  start a trip, alerts, profile, admin console, about, terms, privacy, theme switch,
  sign out and the support numbers.
- Layouts are guarded against sideways scroll down to 320px: flex and grid children may
  shrink (`min-width: 0`), form fields never exceed their column, and long emails or URLs
  wrap instead of widening the page. Verified with no horizontal overflow on every page
  at 320px, 375px and 414px.

## Tests

```bash
php artisan test
```

The suite covers registration (including the first-account-becomes-admin rule), phone
normalisation, invite claiming, the split maths, weighted and excluded splits, member
removal history, trip locking, contributions from several travellers, auto routed
contributions, direct payments and confirmations, the SMS wallet and its bKash top-ups
(replay protection, cancelled payments), reminders and pay links, budgets and estimates,
itemised bills and receipts, the trip pot, polls, the checkpoint list, check-ins and
family links, balances across trips, offline idempotency, Bangla, and the admin console. Tests run on an in-memory SQLite database, so your
local MySQL data is untouched.

## Project layout

```
app/
  Enums/          TripStatus, MemberStatus, MemberRole, SplitType, ExpenseCategory, PaymentMethod
  Http/           Controllers (auth, trips, expenses, members, contributions, admin), Requests, Middleware
  Models/         User, Trip, TripMember, Expense, ExpenseShare, Settlement, TripActivity
  Policies/       TripPolicy - who can view, edit, add costs and record contributions
  Services/       SplitService, ExpenseService, TripLedger, PortfolioService,
                  TripInvitationService, TripActivityLogger, ContributionService,
                  BkashService (token grant, create, execute, query, refund)
  Support/        Money (৳ formatting), Phone (BD number normalisation)
resources/
  css/app.css     Tailwind v4 theme: semantic light/dark tokens, glass, chips, animations
  js/             trip-live.js (SSE + polling sync), toasts.js (toasts + theme)
  views/          home, auth, dashboard, trips (+ live partials), notifications, profile, admin, pages
```

## Copy and design conventions

- Currency is BDT, formatted as `৳ 1,250` through `App\Support\Money`.
- Never use em dashes in copy - use a plain hyphen.
- Dark is the default theme; the choice is remembered per device and can be toggled from
  the top bar, the sidebar or the profile page.
- Mobile is a first class citizen: bottom tab bar with a floating add button, a side
  drawer for the rest, bottom sheet modals, safe area padding and an installable web app
  manifest.

## Contact

Sofor - Solutor, Lift 6, Elite House, 54 Motijheel C/A, Dhaka - 1000, Bangladesh.
Phone: +880 1922-992279 / +880 1910-069929.
