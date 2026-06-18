# Event & Guest Management SaaS

**Stack:** Laravel 13, Vue 3 SPA (decoupled), PostgreSQL, Sanctum token auth, Cashier/Stripe, Resend email

**No ticketing. No attendee payments. Ever.**

## Architecture

- Vue 3 SPA talks to Laravel via `/api/*` with Bearer token auth (Sanctum)
- Web catch-all serves `resources/views/app.blade.php` (SPA shell)
- API routes registered in `bootstrap/app.php` via `api:` key
- Email: Resend (`MAIL_MAILER=resend`, `RESEND_API_KEY=` in `.env`)
- Payments: Laravel Cashier + Stripe (per-event and subscriptions)
- Queue: database (local), will upgrade to Redis + Horizon before production

## Key models

| Model | Purpose |
|-------|---------|
| `User` | Host. Has `plan` (free/event_pass/pro/business), Cashier, HasApiTokens |
| `Event` | Belongs to User. Has slug, status (draft/published/archived), RSVP settings |
| `Guest` | Belongs to Event. Has unique `rsvp_token`, `rsvp_status`, preferences |
| `PlusOne` | Belongs to Guest |

## Pricing / guest limits

| Plan | Guest limit | Event limit |
|------|------------|-------------|
| free | 50 | 3 active |
| event_pass | 300 | unlimited |
| pro | unlimited | unlimited |
| business | unlimited | unlimited |

## Build phases

- **Phase 1 (active):** Event CRUD, guest management, RSVP flow, plus-ones, preferences, email invitations, free tier enforcement
- **Phase 2:** Pro/Business tiers, sub-event logic, attachment support, analytics, custom domains
- **Phase 3:** White-label, API tier, Capacitor mobile

## Testing

Tests in `tests/Feature/`. Run against SQLite in-memory (see `phpunit.xml`). Run tests with:

```
php artisan test
```

**Always run tests before reporting a task complete. Never suppress failures.**

## Development Workflow

- Ask clarifying questions on architectural decisions before implementing
- Draft a plan for approval before complex refactors
- Never add Phase 2+ features while Phase 1 is incomplete
- Prefer editing existing files to creating new ones
