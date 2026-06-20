# guestlist. — Event & Guest Management SaaS

**Stack:** Laravel 13, Vue 3 SPA (decoupled), PostgreSQL, Sanctum Bearer token auth, Cashier/Stripe, Resend email

**No ticketing. No attendee payments. Ever.**

## Architecture

- Vue 3 SPA talks to Laravel via `/api/*` with Bearer token auth (Sanctum)
- Web catch-all (`Route::fallback()`) serves `resources/views/app.blade.php` (SPA shell)
- API routes registered in `bootstrap/app.php` via `api:` key — this key MUST remain or all API routes return 405
- Email: Resend (`MAIL_MAILER=resend`, `RESEND_API_KEY=` in `.env`)
- Payments: Laravel Cashier + Stripe — Event Pass only ($19/event one-time, `checkoutCharge()`)
- Queue: database driver (`QUEUE_CONNECTION=database`) — adequate for current volume
- Password reset URL customized in `AppServiceProvider` to point to `/reset-password/:token` in the SPA

## Key Models

| Model | Purpose |
|-------|---------|
| `User` | Host. Has `plan` (free/event_pass/pro/business), Cashier, HasApiTokens, Notifiable |
| `Event` | Belongs to User. Has slug, status (draft/published/archived), RSVP settings, `event_pass_paid_at` |
| `Guest` | Belongs to Event. Has unique UUID `rsvp_token`, `rsvp_status` (pending/attending/declined/waitlisted), preferences |
| `PlusOne` | Belongs to Guest. Has name, dietary_preference |

## Plan Limits

| Plan | Guest limit | Active events |
|------|------------|---------------|
| free | 50 | 3 |
| event_pass | 300 (per event that purchased the pass) | unlimited |
| pro | unlimited | unlimited |
| business | unlimited | unlimited |

`User::guestLimit()` returns the plan-level limit. `Event::effectiveGuestLimit()` takes `min(planLimit, event.max_guests)` with null-safety for unlimited plans.

## Build Status

- **Phase 1: COMPLETE** — all features implemented, production-hardened, 127 tests passing
- **Phase 2: SUBSCRIPTIONS SHIPPED** — recurring Pro/Business plans via Cashier + Stripe; sub-events, analytics, custom domains not started
- **Phase 3: NOT STARTED** — White-label, API tier, Capacitor mobile

## Implemented Features (Phase 1)

- Event CRUD with status lifecycle (draft → published → archived)
- State guards: publish only from draft, archive rejects already-archived
- RSVP deadline cross-validation (must be before `starts_at`)
- Guest management with CSV export
- RSVP flow: unique token per guest, no guest login required
- Plus-ones with dietary preferences
- Preference collection: dietary, accessibility, seating, phone
- Automatic waitlisting when `isAtCapacity()` is true
- Waitlist auto-promotion: `Event::promoteFirstWaitlisted()` fires when an attending guest declines or is deleted; sends `WaitlistPromotion` notification to the next guest in line (ordered by `responded_at`)
- Email invitations (queued via `GuestInvitation` notification)
- Host RSVP notifications (queued via `RsvpReceived` notification)
- Guest RSVP confirmation emails (queued via `RsvpConfirmation` notification): attending, waitlisted, and declined variants; only sent when guest has an email address
- Bulk invite (single bulk UPDATE, not N+1)
- Stripe Event Pass checkout + webhook verification
- Free tier enforcement (3 active events, 50 guests)
- Rate limiting: auth (5–10/min), RSVP (60/min), authenticated API (120/min)
- Security headers middleware (`SecureHeaders`): X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS (HTTPS only)
- CORS restricted to `APP_URL`
- DB performance indexes
- Global 401 interceptor in Vue SPA
- Full error handling on all Vue pages
- Open redirect protection on login `?redirect=` param
- Password policy: min 8 chars + letters + numbers (set via `Password::defaults()` in AppServiceProvider)
- `me()` endpoint returns curated payload — Stripe/Google internals never exposed; `has_google` and `has_password` bools tell frontend auth state
- `POST /api/auth/set-password` — Google-only users (null password) can add email/password login; rejects if password already set
- `users.password` is nullable — Google OAuth users created without a password; `has_password: false` means Google-only account
- Global 429 interceptor in Vue SPA → annotates response with friendly "Too many requests" message
- App.vue loading gate (`auth.ready`) prevents white flash on page load
- Sanctum token expiration: 30 days (`SANCTUM_TOKEN_EXPIRATION` env, default 43200 min)
- Password reset with custom SPA redirect URL
- RSVP `show()` endpoint includes `event.status`; Vue `rsvpClosed` gates on non-published status (draft/archived show locked message before form)
- Google OAuth sign-in (Laravel Socialite) — links to existing email/password accounts
- Account settings page (`/settings`): update name/email (email requires password confirmation), change password (revokes other sessions), connected accounts (Google link status), account deletion with cascade
- Privacy Policy (`/privacy`) and Terms of Service (`/terms`) — required for production launch
- Professional landing page with product screenshot mockup (Hero, Features, How it works, Pricing, CTA); pricing shows Free ($0), Event Pass ($19/event), Pro ($29/month)
- 404 page in Vue router
- Event date/time shown correctly using wall-clock time semantics (UTC display + timezone label) in host view, RSVP page, and guest invitation emails
- Timezone selector on Create/Edit event forms (full IANA list via `Intl.supportedValuesOf`); defaults to browser timezone
- Password reset form shows field-level validation errors (not just generic message)
- EventDetailPage stats grid shows all 5 states: total, attending, declined, pending, waitlisted
- Guest management actions (Invite, Invite all, Add guest) hidden on archived events
- AccountSettingsPage shows current plan with limits; Change Password hidden for Google-only users; Set Password form shown instead
- `X-Frame-Options: DENY` (stricter than SAMEORIGIN) + `frame-ancestors 'none'` in CSP
- Content Security Policy (production-only): `default-src 'self'`; `style-src 'self' 'unsafe-inline'` (Vue :style bindings require this); `img-src 'self' data:`; `object-src 'none'`; `base-uri 'self'`; `form-action 'self'`
- `robots.txt` blocks /dashboard, /events, /settings, /auth/, /rsvp/ from indexing
- `has_password` and `has_google` returned from `me()`, `register`, `login` responses
- `APP_TIMEZONE=UTC` in `.env.example` — wall-clock time semantics depend on UTC app timezone
- Google-only users who try to change their email get a `422` with `errors.email` = "Set a password before changing your email address." — not the generic "Current password is incorrect."
- RSVP `respond()`: already-attending guests are exempt from the capacity check when re-submitting (updating preferences at a full event will not bump them to waitlisted)
- `ends_at` and `starts_at` server validation errors are shown on Create/Edit event forms via `errors.ends_at` / `errors.starts_at`
- `EventDetailPage.vue` guest table uses a single merged `:class` binding — duplicate `:class` on the same `<tr>` silently drops the first binding in Vue 3
- Admin panel at `/admin` — read-only; `users.is_admin` boolean gates access; `AdminMiddleware` returns 403 for non-admins; `is_admin` included in all auth responses (`me()`, `login()`, `register()`, `updateProfile()`); grant admin via `php artisan admin:promote email@example.com`
- Email verification: `users.email_verified_at` (nullable) — `User` implements `MustVerifyEmail`; queued `App\Notifications\VerifyEmail` sent on registration; `GET /api/auth/verify-email/{id}/{hash}` (signed, named `verification.verify`) verifies and redirects to `/dashboard?verified=1`; `POST /api/auth/resend-verification` (auth:sanctum, throttle:5,5) resends; `email_verified` bool in all auth responses; AppLayout shows amber banner + resend button when unverified; Google OAuth users auto-verified on sign-in
- Google OAuth account linking fix: `POST /api/auth/google/link-token` (auth:sanctum) generates a 5-min cache token → `GET /auth/google/link?token=xxx` (web route) stores `link_user_id` in session → callback checks session and links Google ID to the authenticated user's account instead of creating a new one; handles `google_already_linked` and `link_expired` error cases in the settings UI
- Admin panel extended: `GET /api/admin/users/{user}/events` returns user's events with guest/attending counts; `POST /api/admin/users/{user}/toggle-admin` toggles is_admin (blocked for self); admin stats now include `failed_jobs` count; AdminDashboardPage shows failed-jobs alert banner, expandable user rows with event list, and is_admin toggle buttons
- `GET /api/health` — public healthcheck endpoint; checks database, cache, and queue_table; returns `{status: ok|degraded, database, cache, queue_table}`; returns 503 on any failure
- `php artisan admin:promote {email}` — console command to grant admin access (safer than tinker in production)
- Pro/Business subscriptions: `POST /api/subscriptions/checkout` + `POST /api/subscriptions/portal` (auth:sanctum); `SubscriptionController::planForPriceId()` maps Stripe price IDs → plan names; webhooks handle `customer.subscription.created/updated/deleted` → sync `users.plan`; settings page shows upgrade cards (Monthly/Annual) for free/event_pass users, "Manage subscription" portal link for pro/business; price IDs configured via `STRIPE_PRO_MONTHLY_PRICE_ID`, `STRIPE_PRO_ANNUAL_PRICE_ID`, `STRIPE_BUSINESS_MONTHLY_PRICE_ID`, `STRIPE_BUSINESS_ANNUAL_PRICE_ID`

## Testing

Tests in `tests/Feature/`. Run against SQLite in-memory (see `phpunit.xml`).

```bash
php artisan test
```

**127 tests, 312 assertions. Always run before reporting a task complete. Never suppress failures.**

Test files: `AuthTest`, `EventTest`, `GuestTest`, `RsvpTest`, `StripeTest`, `PasswordResetTest`, `AdminTest`

## Development Workflow

- Ask clarifying questions on architectural decisions before implementing
- Draft a plan for approval before complex refactors
- Never add Phase 2+ features while Phase 1 is incomplete
- Prefer editing existing files to creating new ones
- Run `php artisan test` before every task completion

## Key Technical Notes

- `Route::fallback()` (not `Route::get('/{any}')`) is required for the SPA catch-all — the fallback flag defers matching so API routes win
- `abort_unless` in controllers returns JSON when `X-Requested-With: XMLHttpRequest` is set (Axios sets this automatically)
- `effectiveGuestLimit()` on Event uses `$this->user?->guestLimit()` with nullable-safe chaining — `user` may not be loaded
- Stripe webhook endpoint at `/api/webhooks/stripe` is outside the Sanctum middleware group (public)
- `GuestInvitation`, `RsvpReceived`, `RsvpConfirmation`, and `WaitlistPromotion` all implement `ShouldQueue` — start `php artisan queue:work` in dev
- Google OAuth: `GET /auth/google/redirect` + `GET /auth/google/link` + `GET /auth/google/callback` are **web routes** (not API) — OAuth flows require browser redirects. Callback generates a Sanctum token and redirects to `/auth/callback?token=xxx` in the SPA. `/auth/google/link` is the account-linking flow for authenticated users in settings.
- `App\Notifications\VerifyEmail` extends the base Laravel `VerifyEmail` notification and adds `ShouldQueue` — uses the queued mail driver. `VerifyEmail::createUrlUsing()` in `AppServiceProvider` points the signed URL to the named route `verification.verify` in the API.
- `AuthCallbackPage.vue` at `/auth/callback` reads the token, calls `auth.loginWithToken()`, then navigates to dashboard. `loginWithToken()` clears `_fetchPromise` before re-fetching to avoid stale cache.

## Deployment Quick Reference

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan storage:link
```

Queue worker via Supervisor (`queue:work database --tries=3`).
Scheduler cron: `* * * * * php artisan schedule:run` — runs daily token prune + weekly failed-job prune.
Stripe webhook: `https://yourdomain.com/api/webhooks/stripe` → event: `checkout.session.completed`.
