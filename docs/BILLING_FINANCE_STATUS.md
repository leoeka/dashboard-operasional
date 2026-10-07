# Billing & Finance — Development Status & Handoff

**Last updated:** 18 September 2026
**Status:** PHASE 1–4 COMPLETE / LOCKED
**Baseline at time of writing:** 386 tests passed, 0 failed, 1350 assertions

> The test numbers above are the baseline *as this document was written*. They
> are expected to grow as the rest of the application is developed. Treat them
> as "this is what green looked like", not as a number that must match exactly
> forever. What must stay true is the second half: **0 failed**.

If you are opening this repository cold — a new session, a new agent, or the
same person several months later — read this file before touching anything
under `app/Services/Billing`, `app/Models/Invoice.php`, or `/finance`. It exists
so you do not rebuild work that is already finished and tested.

---

## Current Status

| Phase | Scope | Status |
| --- | --- | --- |
| 1 | Schema & models | **COMPLETE / LOCKED** |
| 2 | Renewal engine & payment | **COMPLETE / LOCKED** |
| 3 | Email delivery & retry | **COMPLETE / LOCKED** |
| 4A | Finance workspace (overview, subscriptions, invoices) | **COMPLETE / LOCKED** |
| 4B | Invoice detail, payments, reminder history, timeline | **COMPLETE / LOCKED** |

**Do not re-open or refactor a locked phase** unless there is a real bug, proven
by a failing test or a reproducible case. "I would have designed it differently"
is not a reason. Several decisions here look arbitrary until you know what they
protect; the *why* is in the docblocks, and the tests will fail if you remove
them.

---

## Business Rules

### Yearly services (hosting, domain, website, maintenance)

- Reminders: **H-30, H-7, H-3**
- Invoice is created from **H-30 onward**, with catch-up.

### Monthly services (SEO retainers)

- Reminders: **H-7, H-3, H-1**
- Invoice is created from **H-7 onward**, with catch-up.

A yearly service gives a month's notice because renewing hosting or a domain
often needs a budget decision. A 30-day notice on a 30-day cycle would arrive
before the previous period had even ended, so monthly uses a shorter ladder.

### Reminders match the threshold exactly; invoices catch up

These are two different questions and the system treats them differently on
purpose.

- **A reminder is a promise about a date.** It fires only when the day count
  matches a threshold exactly. A missed day is never back-filled — the client
  is not sent an "H-30" notice on H-29, and the gap stays visible as a missing
  reminder log.
- **An invoice is a debt.** It is simply owed from the creation threshold
  onward, so it is raised by the first run that sees the subscription — H-29,
  H-12, or even past the renewal date if nobody has paid.

Worked example:

```
H-30  scheduler down, nothing happens
H-29  scheduler back up
      → invoice IS created (catch-up)
      → the H-30 email is NOT faked or sent late
      → H-7 and H-3 then run normally
```

Conflating the two used to mean a missed H-30 left the invoice unraised, and the
H-7 run then reminded a client about a bill that did not exist.

---

## Invoice Architecture

There is **one `invoices` table**. There is no second table for recurring
invoices, and adding one would split finance history in half.

Invoices are told apart by the `purpose` column:

| `purpose` | Meaning |
| --- | --- |
| `project_payment` | Legacy project invoice — DP, Pelunasan, Full Payment |
| `renewal` | Recurring invoice raised by `BillingRenewalService` |

**Never infer the kind from `project_id === null`.** A renewal can belong to a
project (hosting for a site we built), and a reader should not have to deduce an
invoice's kind from a missing value. Use `Invoice::isRenewal()`.

### Overdue is derived, never stored

There is no `overdue` status. An invoice is overdue when:

```
status != 'paid'  AND  due_date < billing business date
```

This is computed in exactly one place, `Invoice::isOverdue()`, which
`statusLabel()` and `statusColor()` both call, and which the Finance summary
mirrors in SQL. A stored flag would need a job to maintain it and would be wrong
between runs.

---

## Billing Models

| Model | Role |
| --- | --- |
| `BillingSubscription` | A recurring service being billed on a cycle |
| `Invoice` | One bill, project or renewal (see `purpose`) |
| `InvoiceItem` | Frozen line items belonging to an invoice |
| `Payment` | Money actually received against an invoice |
| `BillingReminderLog` | One reminder threshold for one invoice |
| `ServicePackage` | Optional catalogue entry a subscription may be based on |
| `Client` | Now also carries optional billing contact fields |

Relationships:

```
BillingSubscription
  → Client                (required)
  → Project               (optional)
  → ServicePackage        (optional)

Invoice (renewal)
  → Client                 → BillingSubscription
  → Project (optional)     → InvoiceItem[]
                           → Payment[]
                           → BillingReminderLog[]
```

### What survives a delete

Invoices are financial history and must outlive the things they point at.
`invoices.project_id`, `invoices.client_id` and `invoices.billing_subscription_id`
are all **`nullOnDelete`** — deleting a project, a client or a subscription
empties the reference and leaves the invoice standing.

Records that belong *to* an invoice (`invoice_items`, `payments`,
`billing_reminder_logs`) cascade, but only when the invoice itself is deleted —
and deleting an invoice is heavily restricted (see *Invoice UI Safety*).

One thing worth knowing because it is not symmetric: `billing_subscriptions.client_id`
**does** cascade. Deleting a client removes their subscriptions, but their
invoices remain.

---

## Historical Snapshot

A renewal invoice is a snapshot of what was billed, not a live view of the
subscription. It freezes:

- `amount`, `subtotal`, `discount`, `tax`, `currency`
- `billing_period_start` and `billing_period_end`
- its `invoice_items` rows, each with the price as it stood

Changing a subscription's **name, price, or service package must not alter any
invoice already issued.** There is a regression test for exactly this.

`Invoice.amount` stays the authoritative total. `Invoice::itemsTotal()` exists
for display and reconciliation, and does not override it.

---

## Date & Timezone

This is the part most likely to be "fixed" by someone who does not know why it
is like this. Please read it before changing a date comparison.

| Layer | Timezone |
| --- | --- |
| Application (`config/app.php`) | **UTC** |
| Billing (`config/billing.php`) | **`Asia/Makassar`** by default |

Environment:

```
APP_TIMEZONE=UTC
BILLING_TIMEZONE=Asia/Makassar
```

**Instants stay UTC** — `created_at`, `updated_at`, `sent_at`, queue timestamps,
log lines. They record moments and must stay comparable across every row, both
before and after any config change.

**Business dates use the billing zone** — `next_renewal_date`, `due_date`,
`billing_period_start`, `billing_period_end`, `paid_at`, `paid_on`,
`scheduled_for`. Billing does not reason in instants, it reasons in calendar
days: "H-7" has to mean the same day to us and to the client.

`Invoice.paid_at` belongs in that list despite its name: it is the date an
invoice was settled, not the instant it was marked. `ReminderTimeline` compares
it against each threshold's day to decide whether a threshold that never got a
log was `not_required` (settled on or before that day) rather than unrecorded,
so it has to be read as a calendar date like the rest.

Moving the whole application off UTC was rejected because it would change the
meaning of every timestamp already written. So only billing's own date
arithmetic uses the billing zone, and it goes through **`BillingClock`** — the
single source of truth for "what day is it". Nothing hardcodes `Asia/Makassar`;
it is read from config, and there is a test that repoints the config at another
zone to prove it.

The scheduler runs at **09:00 billing time**, which is 01:00 UTC while the zone
is Asia/Makassar.

---

## Renewal Engine

Main service: `App\Services\Billing\BillingRenewalService`

Supporting cast:

| Class | Responsibility |
| --- | --- |
| `ReminderPolicy` | The thresholds, and when an invoice is due |
| `BillingCycle` | Anchor-based period maths (no month-end drift) |
| `BillingClock` | What "today" means to billing |
| `InvoiceNumberGenerator` | Race-safe invoice numbering |
| `BillingPaymentService` | Mark paid, advance the subscription |

Command:

```bash
php artisan billing:process-renewals
```

**There is exactly one scheduler entry**, in `routes/console.php`. It covers both
recurring renewals and the legacy project-invoice reminders, because scheduling
those separately would let two runs reach the same invoice and send a client two
reminders for it.

**Do not schedule `invoices:send-reminders`.** That command still exists as a
deprecated compatibility alias so an old crontab entry keeps working, but it is
not a scheduler entry, and it delegates to the same shared service.

`BillingCycle` derives dates from the subscription's `start_date` as an anchor
rather than repeatedly adding a month to the last date. Otherwise a subscription
starting 31 January drifts to the 28th and never comes back.

---

## Legacy Project Invoice Compatibility

Legacy project invoices still work exactly as they did: manual creation from the
Finance page, mark paid, and a manual reminder.

Legacy reminders go out by **email and WhatsApp**, via
`App\Services\Billing\LegacyInvoiceReminderService`.

That service's query **must keep its `purpose = project_payment` filter.** Without
it a renewal invoice would be picked up by both the legacy path and the recurring
path, and the client would get two reminders for one bill.

**Recurring renewals are email only.** They deliberately do not use WhatsApp.

---

## Payment

Main service: `App\Services\Billing\BillingPaymentService`

Marking an invoice paid is one transaction:

1. Lock the invoice row (`lockForUpdate`)
2. Create the `Payment` record
3. Mark the invoice paid
4. Advance the subscription — **only if this is a renewal invoice**

It is idempotent. Marking an already-paid invoice paid a second time must not
create a second `Payment` and must not advance the subscription by two periods.
The guard is a re-read of the status *inside* the lock, not a check before it.

A project invoice never advances a subscription.

---

## Reminder Delivery

The source of truth is **`billing_reminder_logs`**, and it is an idempotency
record before it is a history table.

Identity: **unique on `(invoice_id, days_before)`**. Claiming a threshold means
*inserting that row*; a duplicate insert fails at the database. Nothing relies on
a sender checking a timestamp first.

States: `pending` (claimed, not yet delivered) → `sent` | `failed`.

Delivery path:

```
BillingRenewalService
  → MailReminderDispatcher
    → SendBillingReminderEmail  (queued job, carries only an ID)
      → BillingRenewalReminderMail
        → resources/views/emails/billing-renewal-reminder.blade.php
```

Queue connection: **database**. Recurring reminder emails are never sent
synchronously from the scheduler — a slow SMTP host would otherwise hold up the
whole daily run.

---

## Retry & Idempotency

A failed reminder **reuses the same row**. It never creates a second one, so the
unique claim still holds and `attempts` is a real count.

`BillingReminderLog::MAX_ATTEMPTS` is the single source for the cap. Do not write
the number anywhere else.

The daily run **freezes the set of failed-reminder candidate IDs at the start of
the run**, before any threshold processing. This is the subtle one: without it, a
row that fails *during today's run* would immediately be picked up by the retry
pass in that same run and burn its second attempt the same morning. The frozen
set means a failure on the H-30 run is retried on the H-29 run, as intended.

`handledReminderIds` is then subtracted from that frozen set, so the threshold
path and the retry path can never both process the same row in one run.

**A queue dispatch failure puts the row back to `failed`.** A reminder must never
be stranded as `pending` with no job behind it, because `pending` means "claimed"
and nothing would ever retry it.

---

## Queue Concurrency

`SendBillingReminderEmail` uses `WithoutOverlapping`, keyed on the
`BillingReminderLog` ID, so two workers cannot enter the SMTP critical section
for the same reminder at the same time.

### Limitation — this is not exactly-once, and must not be described as such

SMTP and the database do not share a transaction. A window remains:

```
SMTP accepts the message
  → process crashes
    → the row was never marked sent
      → a later run may send it again
```

What the system actually guarantees:

- no duplicate send under normal concurrency
- retries are bounded by `MAX_ATTEMPTS`
- the `sent` state is idempotent — a duplicate job on a sent row is a no-op
- a duplicate queued job is normally harmless

Please keep this honest in any future documentation or commit message.

---

## Finance Workspace

Route: **`/finance`** — the existing page, upgraded. There is no second finance
application.

Tabs, via `?tab=`:

1. **Overview** — upcoming renewals with countdown
2. **Langganan** — subscriptions
3. **Invoice** — all invoices, project and renewal
4. **Pembayaran** — payments received
5. **Riwayat Reminder** — reminder log

Summary cards, shown on every tab:

- Outstanding / Belum Dibayar
- Overdue / Terlambat
- Due Soon
- Paid This Month
- Active Subscriptions
- Upcoming Renewals

All six are SQL aggregates. They do not load invoices into PHP to add them up.

Subscription CRUD: create, edit, pause, resume, cancel. Project is optional;
`ServicePackage` is optional (`service_package_id` is nullable, and a custom
subscription with no package is fully supported).

Validation worth keeping:

- the chosen **project must belong to the chosen client** (enforced with a
  scoped `Rule::exists`, not just in the UI)
- `next_renewal_date >= start_date`

---

## Invoice UI Safety

Delete is allowed for **unpaid `project_payment` invoices only**.

Delete is blocked for:

- paid project invoices
- **all** renewal invoices

The **backend guard in `InvoiceController::destroy()` is the source of truth.**
Hiding the button is a courtesy; the controller refuses the request either way,
and there is a test that posts the delete without the button.

A paid invoice is financial history, and deleting it would cascade its payments
away with it.

Manual reminders:

- unpaid project invoice → allowed
- renewal invoice → **not offered**, because a renewal follows its recorded
  thresholds and a hand-sent email would correspond to no threshold at all

---

## Reminder Timeline

Shown on a renewal invoice's detail page. It is **derived at read time and stored
nowhere**. Thresholds come from `ReminderPolicy` via
`App\Services\Billing\ReminderTimeline`; a Blade template must never write out
`[30, 7, 3]` itself, or it will silently disagree with the engine the day the
schedule changes.

| State | UI label |
| --- | --- |
| `sent` | Terkirim |
| `failed` | Gagal · percobaan N/3 |
| `pending` | Menunggu kirim |
| `not_required` | Tidak diperlukan |
| `upcoming` | Belum waktunya |
| `due_today` | Dijadwalkan hari ini |
| `disabled` | Reminder nonaktif |
| `missed` | **Tidak tercatat** |

Two naming decisions that were made deliberately:

**`missed` is labelled "Tidak tercatat", not "Terlewat".** The absence of a
`BillingReminderLog` row proves exactly one thing — that no reminder was recorded
for that threshold. It does not prove the scheduler failed. The timeline is
observability, and it should not raise an alarm it cannot substantiate.

**A threshold falling today is `due_today`, never missed.** The daily run fires
at 09:00 billing time, so a threshold cannot be judged on the day it falls due.

Payment interacts with the timeline by date, not by status alone:

- paid **on or before** a threshold's day → that threshold is `not_required`,
  because the engine deliberately stopped reminding
- paid **after** a threshold had already passed unrecorded → that earlier
  threshold stays `Tidak tercatat`; a later payment does not explain the earlier
  silence

When `auto_reminder` is off, the timeline says so for today and future
thresholds only. There is no history of *when* the flag was switched off, so it
never speaks for the past.

---

## Reminder Timestamp Display

`billing_reminder_logs.sent_at` is **stored in UTC and stays that way.**

The Finance UI converts a copy for display and labels the zone, so an operator
reading a time on screen cannot mistake it for the stored one:

```
stored:    2026-10-22 23:30 UTC
displayed: 23 Oct 2026 07:30 WITA
```

This happens in `BillingReminderLog::sentAtForDisplay()`. It is display-only and
writes nothing back.

---

## Production Runtime Requirements

**1. The server cron must run Laravel's scheduler every minute:**

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Laravel then runs `billing:process-renewals` at 09:00 billing time.

**2. A queue worker must be running:**

```bash
php artisan queue:work
```

or the equivalent under a process manager (Supervisor, systemd).

If the queue worker is not running, reminders will be **claimed and left
`pending`** — the invoice is still raised, but no email goes out. This is the
most likely cause of "the system stopped sending reminders" in production. Check
the worker before suspecting the engine.

**Do not add a separate cron entry for emails.** One scheduler entry, one queue
worker.

---

## Important Files

**Config**

- `config/billing.php`

**Services** — `app/Services/Billing/`

- `BillingClock.php`
- `BillingCycle.php`
- `ReminderPolicy.php`
- `BillingRenewalService.php`
- `BillingPaymentService.php`
- `InvoiceNumberGenerator.php`
- `LegacyInvoiceReminderService.php`
- `ReminderTimeline.php`
- `ReminderDispatcher.php`, `MailReminderDispatcher.php`, `QueuedReminderDispatcher.php`

**Jobs & Mail**

- `app/Jobs/SendBillingReminderEmail.php`
- `app/Mail/BillingRenewalReminderMail.php`

**Controllers**

- `app/Http/Controllers/FinanceController.php`
- `app/Http/Controllers/FinanceInvoiceController.php`
- `app/Http/Controllers/BillingSubscriptionController.php`
- `app/Http/Controllers/InvoiceController.php`

**Models**

- `app/Models/BillingSubscription.php`
- `app/Models/BillingReminderLog.php`
- `app/Models/Invoice.php`
- `app/Models/InvoiceItem.php`
- `app/Models/Payment.php`
- `app/Models/ServicePackage.php`

**Views**

- `resources/views/pages/finance.blade.php`
- `resources/views/finance/` (invoice detail, subscription form, tab partials)
- `resources/views/emails/billing-renewal-reminder.blade.php`

**Routes**

- `routes/web.php`
- `routes/console.php`

**Migrations** — `database/migrations/2026_09_18_000001` … `000007`

`000007` carries a rollback guard that refuses to drop billing columns while
billing data exists, so a careless `migrate:rollback` cannot delete finance
history. It fails loudly instead.

---

## Regression Tests

| Suite | Covers |
| --- | --- |
| `BillingSchemaTest` | Tables, columns, constraints, defaults |
| `BillingRollbackGuardTest` | The `000007` rollback guard |
| `BillingRenewalEngineTest` | Thresholds, catch-up, cycles, payment, advancement |
| `BillingEmailDeliveryTest` | Dispatch, retry, overlap lock, sanitised errors |
| `FinanceWorkspaceTest` | Tabs, filters, CRUD, delete guards, query cost |
| `FinanceDetailTest` | Invoice detail, payments, reminder history, timeline, timezone boundary |

Baseline at time of writing: **386 passed, 0 failed, 1350 assertions.**

Before modifying anything in Billing & Finance, run the targeted suite for the
area you are touching. Before committing, run the whole thing:

```bash
vendor/bin/pest
```

Tests never send real email. `Mail::fake()` and `Queue::fake()` are used
throughout, and the test environment runs on an in-memory SQLite database.

---

## Locked Architecture — Do Not Casually Change

Each of these exists because the alternative was tried, or was reasoned through
and rejected for a specific reason recorded in the code:

1. One `invoices` table
2. `purpose` as the explicit discriminator
3. `ReminderPolicy` thresholds
4. `BillingClock` timezone separation (app UTC / billing local)
5. Anchor-based `BillingCycle` date maths
6. Invoice catch-up behaviour
7. Missed reminders are never back-filled
8. `BillingReminderLog` as the idempotency record
9. Frozen retry-candidate IDs
10. The queue overlap lock
11. The payment transaction and subscription advancement
12. Historical invoice snapshots
13. Project / renewal separation
14. Delete guards on financial history

Changing any of them requires all three of:

1. a documented reason
2. a regression test that demonstrates the problem
3. a full green suite afterwards

---

## Known Limitations / Future Work

Not implemented, and **not bugs** — this is future scope:

- PDF invoice generation
- Downloadable invoices
- Public / client-facing billing portal
- Payment gateway integration
- Automatic receipts
- CSV or accounting exports
- Accounting software integration
- Manual "generate renewal invoice now" action
- Recurring reminders over WhatsApp (legacy project invoices have this;
  renewals deliberately do not)
- Absolute exactly-once SMTP delivery (see *Queue Concurrency*)

### Residual limitation — a reminder stranded as `pending` by a hard crash

This one is accepted and documented, not an unfinished part of Phases 1–4.

Claiming a threshold and enqueuing its job are two steps. Between them the row
sits at `pending`, which means "claimed, a job is coming".

- **An ordinary dispatch exception is handled.** If the queue refuses the job —
  connection down, driver error — the exception is caught and the row is put
  back to `failed`, where the next daily run's retry pass picks it up. This path
  is covered by tests in `BillingEmailDeliveryTest`.
- **A hard process crash in that window is different.** If the PHP process dies
  outright after the row is set to `pending` but before the job is durably
  recorded, nothing runs to correct it. The row stays `pending` with no job
  behind it.

The retry pass deliberately only considers `failed` rows, so it will **not** pick
such a row up. Treating any long-standing `pending` row as retryable would risk
re-sending a reminder whose job is simply still queued.

Recovering from this currently needs operational inspection: look for
`billing_reminder_logs` rows that have been `pending` well beyond the time a
queue worker would normally have drained them, confirm no matching job exists,
and resolve them by hand.

If this ever proves to happen in practice, the fix is a reconciliation or outbox
mechanism — a sweep that pairs `pending` rows against the jobs table, or writing
the intent and the job in one transaction. That is future work to be justified by
real incidents, not something to build speculatively.

### Alpine.js is loaded twice

Once via the npm import in `app.js` and once from the CDN in the layout.
Recorded as technical debt by decision; it was deliberately left alone during
Phase 4.

---

## Where To Continue Next

**Billing & Finance core scope, Phases 1–4, is finished.**

Do not start again from the schema, the renewal engine, email delivery, or the
Finance workspace. They are built, tested and locked.

If billing development continues, it should start from a **new requirement**, not
from a rewrite of what is here.

Possible Phase 5 candidates, none of which has been started or agreed:

- PDF / downloadable invoices
- Client billing portal
- Payment gateway
- Receipts
- Finance exports
- Service package management UI
- Revenue reporting and analytics

**Review the requirement with the user before choosing any of these.** Do not
pick one and begin implementing it on your own initiative.

---

## Git Checkpoint

Phases 1–4 were committed as a single checkpoint on `master`:

```
Commit:  1cbcb4c  (1cbcb4c96fa0690550bfd96c514f9e471ae26ef5)
Message: feat: add recurring billing and finance workspace
Date:    18 September 2026
```

That commit carries the whole of Phases 1–4 — schema, engine, delivery,
workspace, tests — and the first version of this document. The hash recorded
here was filled in afterwards by a follow-up commit, so this file's own history
runs one commit ahead of the checkpoint it describes.
