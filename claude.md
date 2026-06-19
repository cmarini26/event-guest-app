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

- **Phase 1: COMPLETE** — all features implemented, production-hardened, 76 tests passing
- **Phase 2: NOT STARTED** — Pro/Business subscriptions, sub-events, analytics, custom domains
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
- Email invitations (queued via `GuestInvitation` notification)
- Host RSVP notifications (queued via `RsvpReceived` notification)
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
- `me()` endpoint returns curated payload — Stripe/Google internals never exposed; `has_google` bool tells frontend if Google is linked
- App.vue loading gate (`auth.ready`) prevents white flash on page load
- Sanctum token expiration: 30 days (`SANCTUM_TOKEN_EXPIRATION` env, default 43200 min)
- Password reset with custom SPA redirect URL
- Google OAuth sign-in (Laravel Socialite) — links to existing email/password accounts
- Account settings page (`/settings`): update name/email (email requires password confirmation), change password (revokes other sessions), connected accounts (Google link status), account deletion with cascade
- Privacy Policy (`/privacy`) and Terms of Service (`/terms`) — required for production launch
- Professional landing page with product screenshot mockup (Hero, Features, How it works, Pricing, CTA)
- 404 page in Vue router

## Testing

Tests in `tests/Feature/`. Run against SQLite in-memory (see `phpunit.xml`).

```bash
php artisan test
```

**83 tests, 191 assertions. Always run before reporting a task complete. Never suppress failures.**

Test files: `AuthTest`, `EventTest`, `GuestTest`, `RsvpTest`, `StripeTest`, `PasswordResetTest`

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
- `GuestInvitation` and `RsvpReceived` both implement `ShouldQueue` — start `php artisan queue:work` in dev
- Google OAuth: `GET /auth/google/redirect` + `GET /auth/google/callback` are **web routes** (not API) — OAuth flows require browser redirects. Callback generates a Sanctum token and redirects to `/auth/callback?token=xxx` in the SPA.
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
