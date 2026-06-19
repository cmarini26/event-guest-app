# guestlist.

**Event guest management for private events and small corporate gatherings.**

Collect RSVPs, track dietary and accessibility preferences, send invitation emails, manage plus-ones, and export your guest list — all from one clean dashboard. No ticketing. No attendee payments. Ever.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13 (PHP 8.3+) |
| Frontend | Vue 3 SPA (decoupled, Vite) |
| Database | PostgreSQL (production), SQLite in-memory (tests) |
| Auth | Laravel Sanctum (Bearer token) |
| Email | Resend via Laravel Mail |
| Payments | Laravel Cashier + Stripe (Event Pass only) |
| Queue | Database queue driver (production-ready for low volume) |
| Styles | Tailwind CSS v4 |

---

## Features

### Event Management
- Create events with name, description, venue, start/end times, timezone
- Status lifecycle: **draft → published → archived**
- Per-event RSVP deadline with automatic close enforcement
- Per-event guest cap (`max_guests`) layered on top of plan limit
- Auto-generated URL slugs with collision handling
- Stripe Event Pass upgrade ($19/event → 300 guest limit)

### Guest Management
- Add guests individually with first name, last name, email, phone
- Unique RSVP token per guest (UUID v4) — no guest login required
- Track per-guest: `rsvp_status` (pending / attending / declined / waitlisted), `responded_at`, `invited_at`
- Export full guest list to CSV (includes preferences and plus-ones)
- Bulk invite all uninvited guests with email in one click
- Authorization scoped: guests are always verified to belong to the requested event

### RSVP Flow
- Public RSVP page at `/rsvp/:token` — no authentication required
- Collect dietary preferences, accessibility needs, seating preferences (configurable per event)
- Optional phone number requirement
- Plus-ones with individual names and dietary preferences
- Automatic waitlisting when event reaches capacity
- RSVP deadline enforcement (server-side)
- Status guard: RSVPs rejected for draft or archived events
- Host receives email notification on every RSVP response

### Email
- **Invitation email** — personalized per guest, contains unique RSVP link
- **Host RSVP notification** — sent to event owner when a guest responds
- **Password reset** — customized URL pointing to `/reset-password/:token` in the SPA
- All email via Resend; all notification jobs queued

### Access Control & Limits
| Plan | Guest limit | Active events |
|------|------------|---------------|
| free | 50 | 3 |
| event_pass | 300 (per upgraded event) | unlimited |
| pro | unlimited | unlimited |
| business | unlimited | unlimited |

### Security & Hardening
- Rate limiting: auth endpoints (5–10 req/min), RSVP (60 req/min), authenticated API (120 req/min)
- Open redirect protection: `?redirect=` parameter on login is validated as a relative path
- Sanctum token expiration: 30 days (configurable via `SANCTUM_TOKEN_EXPIRATION`)
- App.vue loading gate prevents unauthenticated flash while session is being verified
- CORS restricted to `APP_URL`
- Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`
- Global 401 interceptor in the SPA → clears session and redirects to login
- State guards on all status transitions
- DB indexes on `events(status)`, `events(user_id, status)`, `guests(rsvp_status)`, `guests(event_id, rsvp_status)`, `guests(email)`, `guests(invited_at)`

---

## Local Development

### Requirements
- PHP 8.3+, Composer
- Node 20+, npm
- PostgreSQL 15+ (or use SQLite for local dev)

### Setup

```bash
# Clone and install
git clone git@github.com:cmarini26/event-guest-app.git
cd event-guest-app
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database (PostgreSQL)
# Create a database, then:
php artisan migrate

# Start dev servers (two terminals)
php artisan serve          # Laravel on :8000
npm run dev               # Vite HMR on :5173
```

### Environment Variables

Copy `.env.example` and fill in:

```env
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_DATABASE=event_guest_app
DB_USERNAME=postgres
DB_PASSWORD=your_password

MAIL_MAILER=resend
RESEND_API_KEY=re_xxxxxxxxxxxx
MAIL_FROM_ADDRESS=noreply@yourdomain.com

STRIPE_KEY=pk_test_xxxxxxxxxxxx
STRIPE_SECRET=sk_test_xxxxxxxxxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxx

# Optional: Sanctum token lifetime in minutes (default 43200 = 30 days)
SANCTUM_TOKEN_EXPIRATION=43200
```

### Queue Worker

Email notifications are queued. Start the worker in a separate terminal:

```bash
php artisan queue:work
```

---

## Testing

Tests run against SQLite in-memory — no external services required.

```bash
php artisan test
```

**72 tests, 161 assertions** across:
- `AuthTest` — registration, login, logout, token auth
- `EventTest` — CRUD, publish/archive state guards, RSVP deadline validation, free tier limits
- `GuestTest` — guest management, plan limit enforcement, CSV export, invitations
- `RsvpTest` — RSVP flow, preferences, plus-ones, deadline enforcement, host notifications
- `StripeTest` — Event Pass checkout, webhook signature verification, idempotency
- `PasswordResetTest` — forgot password, reset flow

---

## Deployment

### Production Checklist

```bash
# 1. Set environment
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
LOG_LEVEL=error

# 2. Install dependencies (no dev)
composer install --no-dev --optimize-autoloader

# 3. Build frontend assets
npm ci
npm run build

# 4. Run migrations
php artisan migrate --force

# 5. Cache config/routes
php artisan optimize

# 6. Create storage symlink
php artisan storage:link
```

### Queue Worker (Supervisor)

```ini
[program:guestlist-worker]
command=php /path/to/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker.log
```

### Stripe Webhooks

In the Stripe dashboard, add a webhook endpoint:
- **URL:** `https://yourdomain.com/api/webhooks/stripe`
- **Events:** `checkout.session.completed`
- Copy the signing secret to `STRIPE_WEBHOOK_SECRET`

---

## API Reference

All API routes are under `/api/`. Authenticated endpoints require `Authorization: Bearer {token}`.

### Auth
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/auth/register` | — | Register (returns token) |
| POST | `/api/auth/login` | — | Login (returns token) |
| POST | `/api/auth/logout` | ✓ | Revoke current token |
| GET | `/api/auth/me` | ✓ | Current user |
| POST | `/api/auth/forgot-password` | — | Send reset link |
| POST | `/api/auth/reset-password` | — | Reset with token |

### Events
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/events` | List user's events (with guest counts) |
| POST | `/api/events` | Create event |
| GET | `/api/events/:id` | Get event (with RSVP counts) |
| PUT | `/api/events/:id` | Update event |
| DELETE | `/api/events/:id` | Delete event |
| POST | `/api/events/:id/publish` | Draft → Published |
| POST | `/api/events/:id/archive` | Any → Archived |
| POST | `/api/events/:id/checkout` | Create Stripe Event Pass checkout |

### Guests
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/events/:id/guests` | List guests (with plus-ones) |
| POST | `/api/events/:id/guests` | Add guest |
| PUT | `/api/events/:id/guests/:gid` | Update guest |
| DELETE | `/api/events/:id/guests/:gid` | Delete guest |
| POST | `/api/events/:id/guests/:gid/invite` | Send invitation email |
| POST | `/api/events/:id/guests/bulk-invite` | Invite all uninvited guests |
| GET | `/api/events/:id/guests/export` | Download CSV |

### RSVP (public)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/rsvp/:token` | Get RSVP page data |
| POST | `/api/rsvp/:token` | Submit RSVP response |

---

## Project Structure

```
app/
├── Http/Controllers/Api/
│   ├── AuthController.php
│   ├── EventCheckoutController.php
│   ├── EventController.php
│   ├── GuestController.php
│   ├── PasswordResetController.php
│   ├── RsvpController.php
│   └── StripeWebhookController.php
├── Http/Middleware/
│   └── SecureHeaders.php
├── Models/
│   ├── Event.php
│   ├── Guest.php
│   ├── PlusOne.php
│   └── User.php
├── Notifications/
│   ├── GuestInvitation.php
│   └── RsvpReceived.php
└── Policies/
    └── EventPolicy.php

resources/js/
├── pages/
│   ├── auth/         # Login, Register, ForgotPassword, ResetPassword
│   ├── events/       # Dashboard, CreateEvent, EditEvent, EventDetail
│   └── rsvp/         # RsvpPage
├── layouts/
│   ├── AppLayout.vue    # Authenticated shell (nav + main)
│   └── GuestLayout.vue  # Public shell
├── stores/
│   ├── auth.js
│   └── events.js
└── router/index.js
```

---

## Roadmap

### Phase 2 (planned)
- Pro/Business subscriptions via Stripe
- Sub-event support (sessions, breakouts)
- Attachment support for events
- Analytics dashboard
- Custom domain support

### Phase 3 (future)
- White-label mode
- Public API tier
- Capacitor mobile app
