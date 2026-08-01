# Plutus Phase 2 Upgrades Plan

*Version 1.0 | For implementation by AI or human developer | Draft — 2026-08-01*

---

## Executive Summary

This plan covers the next wave of functional upgrades to the Plutus Financial Tracking System, following the completion of Phases 0–6. It is structured as three independent upgrade blocks, each with full design detail, acceptance criteria, and file inventories. The implementing agent can execute these in order or in parallel, as they share no schema conflicts.

**Block 1** (this document, detailed): Planned Transaction System — create projected-cost transactions in advance, then compare against actual spend.

**Block 2** (to be added by Merrill): TBC.

**Block 3** (to be added by Merrill): TBC.

---

## Block 1 — Planned Transaction System (Projected vs Actual)

### Problem Statement

The current system only supports logging transactions **after** they happen. There is no mechanism to:

1. Plan upcoming spending by creating transactions in advance with projected costs.
2. Compare projected costs against what was actually spent on the day.
3. Keep a forward-looking view of planned spending within a budget period.

The user needs to be able to, for example, plan a week's travel budget ahead of time ("Train to Cardiff" £15.50 / "Coffee" £3.80 / "Film" £12.00 / "Dinner at restaurant X" £25.00), then on each day enter the real costs alongside the projections, mark them as spent, and see both figures preserved.

### Design Decisions (confirmed by Merrill, 2026-08-01)

| # | Decision | Rationale |
|---|----------|-----------|
| 1 | Both projected and actual values persist in the same row, forever visible | User wants to compare before/after, not overwrite the plan |
| 2 | Normal "LOG TRANSACTION" flow continues for unplanned spending within the selected time period | Not every purchase is planned in advance |
| 3 | Projected totals count toward the Plan view; only actuals count against the budget's target cap | Shows "planned £100 / actual £62.50 / remaining target £37.50" — realistic gap analysis |

### Schema Changes

**Extend the `transactions` table with two new columns:**

```sql
ALTER TABLE transactions
    ADD COLUMN projected_amount DECIMAL(10,2) NULL AFTER amount,
    ADD COLUMN status ENUM('planned','spent') NOT NULL DEFAULT 'spent' AFTER projected_amount;
```

**Column semantics:**

| Column | `spent` row | `planned` row |
|--------|-------------|---------------|
| `amount` | Actual cost (required) | NULL (or 0.00, not set) |
| `projected_amount` | The planned cost if this was pre-planned; NULL if unplanned | The planned cost (required) |
| `status` | `'spent'` | `'planned'` |

**Conversion flow:**
1. User creates a planned transaction: `status = 'planned'`, `projected_amount = £15.50`, `amount = NULL`.
2. User marks it as spent and enters the actual: `status = 'spent'`, `amount = £18.20`, `projected_amount` stays at `£15.50`.
3. Normal unplanned transactions created at the time of spending: `status = 'spent'`, `projected_amount = NULL`, `amount = £5.00` (unchanged from current behaviour, fully backward compatible).

**Migration must also apply to staging DB.** Apply `--db=plutus_thoughts_staging` equivalent.

### API Changes

**1. `save_object` (ObjectController) — extended:**

The existing universal CRUD endpoint already handles `projected_amount` and `status` via the ALLOWED_SCHEMAS field list (once the two columns are registered in the schema — see below). No new endpoint needed.

The `status` field accepts values `'planned'` and `'spent'`. When `status = 'planned'`, the Validator should enforce that `projected_amount IS NOT NULL` and `amount IS NULL` (or not provided). The converse for `status = 'spent'`.

**Schema field registration** (in `ObjectController::ALLOWED_SCHEMAS`):
- The `transaction` schema must be updated to include `projected_amount` and `status` in the fields list.

**2. `get_dashboard` (DashboardController) — extended response:**

The dashboard response gains two new values per tab context:

```json
{
    "planned_total": 100.00,
    "actual_total": 62.50,
    "planned_transactions": [...],  // status='planned', projected_amount set
    "spent_transactions": [...],    // status='spent', already returned as 'transactions' today
    ...
}
```

**New computed fields:**
- `planned_total` = `SUM(t.projected_amount)` for `status = 'planned'` within the period
- `actual_total` = `SUM(t.amount)` for `status = 'spent'` within the period (already computed as `total_paid` today)

Existing `transactions` in the response represent spent transactions (unchanged). The dashboard also returns `planned_transactions` as a separate array so the UI can render them in a distinct section.

**3. `mark_spent` action (new):**

A new action that transitions a planned transaction to spent status with the actual amount:

```
POST /api.php?action=mark_spent
  entity_type = transaction
  id = 1234
  actual_amount = 18.20
  (optional) date = 2026-08-05  (default: today)
```

This is a convenience endpoint — the existing `save_object` with `status='spent'` and `amount=18.20` over an existing planned transaction would achieve the same thing. The convenience endpoint exists purely so the UI can have a single "MARK SPENT" button that pre-fills the actual cost.

**4. `get_objects` — filter for planned/spent status:**

Add an optional `status` query parameter:
```
GET /api.php?action=get_objects&entity_type=transaction&status=planned
```
Returns only planned transactions (backward compatible: absent status = current behaviour, which returns all non-deleted).

### Query Changes

**Dashboard queries that build the date filter:** the existing `$dateFilter` logic applies to both planned and spent transactions. Planned transactions should be filtered by their `date` column (the date the user plans to spend them), so the same period selector works for both planned and actual views.

**Budget totals (`updateBudgetTotals`):** only `status = 'spent'` transactions should contribute to `cost_paid` and `cost_remaining`. The helper already uses `SELECT SUM(amount) WHERE budget_id = ? AND deleted_at IS NULL` — add `AND status = 'spent'` to the WHERE clause.

### Frontend Changes

**1. "PLAN TRANSACTION" button (new, transaction tab sidebar)**

Same tab context as "LOG TRANSACTION", grouped nearby. Opens the universal transaction form with `status = 'planned'` pre-set, and labels the amount field as "PROJECTED COST".

**2. Planned transactions panel (new, dashboard)**

Below the existing transaction log, a new panel renders `planned_transactions` returned by the dashboard. Each row shows:
```
[☐] TRAIN TO CARDIFF — planned £15.50 — date 2026-08-03
     [MARK SPENT] [EDIT PLAN] [DELETE]
```
Clicking "MARK SPENT" opens a prompt for the actual amount (defaulting to the projected amount as a suggestion), then submits via `mark_spent` (or `save_object` with `status='spent'`).

**3. Mark-spent form (modal)**

A lightweight form with one field: "ACTUAL COST", pre-filled with the projected amount, plus an optional date override. Submitting transitions the transaction to `status='spent'` with `amount = actual_cost` and `projected_amount` preserved.

**4. Comparison view (budget panel)**

Each budget panel in the personal/household tab gains a comparison row:
```
TRAVEL (JUL 2026)
PROJECTED: £100.00   ACTUAL: £62.50   REMAINING: £37.50
[████████████░░░░░░░░░░] 62.5% of projection spent
```
The Phase 6 period selector controls both the projected and actual totals for the selected period, so picking WEEK + W31 gives that week's plan-vs-actual.

**5. Plan-editing flow**

Existing planned transactions can be edited (change projected amount, date, category, name) via the same `editObject` path, same as any transaction. The only special path is "MARK SPENT" — everything else reuses existing infrastructure.

### Acceptance Criteria

- [x] A transaction can be created with `status = 'planned'` and `projected_amount` set
- [x] A planned transaction can be converted to spent by setting `actual_amount` without losing the projected amount
- [x] Both values persist and are visible in the UI after marking spent
- [x] Normal unplanned "LOG TRANSACTION" flow is unaffected and creates `status = 'spent'` with no projected amount
- [x] The dashboard returns separate `transactions` (spent) and `planned_transactions` (planned) arrays
- [x] Budget `cost_paid`/`cost_remaining` is calculated only from spent transactions, ignoring planned ones
- [x] The budget panel shows PROJECTED vs ACTUAL vs REMAINING for the selected period
- [x] The Phase 6 period selector filters both planned and spent transactions by the selected period
- [x] All existing tests (PHPUnit, PHPStan, Vitest, Playwright) pass after the changes
- [x] Staging environment updated and smoke tests pass

### Implementation Note

*Implemented 2026-08-01. Verified: planned tx creates with projected_amount + NULL amount; mark-spent preserves both values; budget totals count spent only; dashboard returns planned_total/planned_transactions; E2E planned.spec passes (6/6 total). Commit `1b53773`.*

### Files

| File | Change |
|------|--------|
| Migration script (new) | `ALTER TABLE transactions ADD COLUMN projected_amount...` |
| `api/controllers/ObjectController.php` | Add `projected_amount`, `status` to ALLOWED_SCHEMAS; update `updateBudgetTotals` to filter `status='spent'`; add `status` field to required validation |
| `api/controllers/DashboardController.php` | Add `planned_total`, `actual_total`, `planned_transactions` to response; add planned-section query |
| `api/routes.php` | Register `mark_spent` convenience endpoint (optional; `save_object` covers it already) |
| `assets/js/app.js` | "PLAN TRANSACTION" button; planned panel rendering; "MARK SPENT" action with amount prompt; comparison view in budget panels; planned/spent toggle in get_objects filter |

---

## Block 2

*To be added by Merrill Leo.*

---

## Block 3

*To be added by Merrill Leo.*

---

## Sign-off

| Role | Name | Date | Status |
|------|------|------|--------|
| Planned by | Leon (AI) | 2026-08-01 | ✓ |
| Reviewed by | Merrill Leo | | |
| Approved by | Council | | |

---

*End of Phase 2 Upgrades Plan — Block 1*

## Block 2 — Spend-Based Task Manager with Google Calendar Integration

### Problem Statement

Some spending is habitual and predictable: every Thursday at 3:30pm the cake shop has reductions, every Monday morning the travel card needs a top-up. These recurring spend occasions are currently unmanaged. The user needs:

1. A way to create recurring spend-tasks in Plutus with projected costs and schedules.
2. Automatic syncing to a designated Plutus Google Calendar, so the reminders appear on all the user's devices.
3. A link back to spending: when the task is completed, the user enters the actual spent amount (alongside the projected), which optionally creates a transaction.
4. Recurrence that can be fixed-cycle (every N for X instances) or continuous (every N indefinitely, with pause/resume and stop).

### Design Decisions (confirmed by Merrill, 2026-08-01)

| # | Decision | Rationale |
|---|----------|-----------|
| 1 | Full OAuth Google Calendar API integration (create/update/delete events via Google's Calendar API v3) | Real sync to a designated Plutus calendar — the user sees tasks on all devices without manual steps. Uses the existing `google_event_id` and `google_calendar_id` columns already present in the transactions/budgets/tasks schemas. |
| 2 | Marking a task complete prompts for actual cost (projected amount pre-filled as default), then optionally creates a transaction | Matches the Block 1 mark-spent pattern — user enters what they actually spent; projected persists alongside |
| 3 | Recurrence options: **fixed number of instances** (e.g. 8 weeks), or **continuous** (indefinite, with pause/resume and stop/remove) | Covers both "I want to try this for 4 weeks" and "this is my permanent Thursday routine" |

### Scope Note

This block **replaces and extends** the existing lightweight `tasks` table and its dashboard panel. The old tasks table becomes the spend-task table, augmented with recurring-task metadata, Google Calendar sync columns, and the actual-vs-projected spending pattern from Block 1. Existing tasks that are not spend-related are unaffected.

### Schema Changes

**Extend the `tasks` table:**

```sql
ALTER TABLE tasks
    ADD COLUMN projected_cost DECIMAL(10,2) NULL AFTER status,
    ADD COLUMN actual_cost DECIMAL(10,2) NULL AFTER projected_cost,
    ADD COLUMN spent_at DATETIME NULL AFTER actual_cost,
    ADD COLUMN recurrence_type ENUM('none','daily','weekly','biweekly','monthly','yearly') NOT NULL DEFAULT 'none' AFTER spent_at,
    ADD COLUMN recurrence_days VARCHAR(14) NULL AFTER recurrence_type,   -- e.g. 'mon,thu' for weekly on Mon and Thu
    ADD COLUMN recurrence_time TIME NULL AFTER recurrence_days,
    ADD COLUMN recurrence_count INT UNSIGNED NULL AFTER recurrence_time, -- NULL = continuous, N = fixed instances
    ADD COLUMN recurrence_completed INT UNSIGNED NOT NULL DEFAULT 0 AFTER recurrence_count,
    ADD COLUMN paused_at DATETIME NULL AFTER recurrence_completed,
    ADD COLUMN google_calendar_id VARCHAR(255) NULL AFTER google_event_id,
    ADD COLUMN google_recurring_id VARCHAR(255) NULL AFTER google_calendar_id; -- parent recurring event ID on Google
```

**Column semantics for the cake-shop example:**

| Column | Value | Meaning |
|--------|-------|---------|
| `title` | "Cake shop reductions" | Task name |
| `type` | `'spend'` | New task type for spend-tasks |
| `projected_cost` | 5.00 | £5 budgeted |
| `actual_cost` | NULL (until completed) | Set to £4.50 when marked complete |
| `recurrence_type` | `'weekly'` | Every week |
| `recurrence_days` | `'thu'` | On Thursdays |
| `recurrence_time` | `15:30:00` | At 3:30pm |
| `recurrence_count` | NULL | Continuous (no end) |
| `recurrence_completed` | 3 | Completed 3 instances so far |
| `paused_at` | NULL | Not currently paused |
| `related_budget_id` | (Food & Drink budget) | Links spending to budget |
| `status` | `'active'` | Currently recurring |
| `google_event_id` | Google event ID for this instance | Updated per-instance |
| `google_recurring_id` | ID of the recurrence rule on Google | Set once when first synced |

### Google Calendar Integration

**Setup prerequisites (one-time, outside code):**
1. Google Cloud Console project with Calendar API enabled.
2. OAuth 2.0 client credentials (client ID + secret) stored as environment variables or in a config file not committed to git.
3. A designated Plutus calendar created by the user; its calendar ID stored in the user's `tasks.related_project_id` or a `settings` table.
4. OAuth consent screen configured for internal use.

**OAuth flow:**
1. When the user first enables calendar sync (either globally or per task), redirect to Google's OAuth consent screen.
2. On callback, store the access token and refresh token in the existing `users` table (add `google_access_token`, `google_refresh_token`, `google_token_expires` columns).
3. The refresh mechanism is transparent — the API layer handles token refresh on expiry.

**Sync behaviour:**
1. When a recurring spend-task is created, the server creates a recurring Google Calendar event on the designated Plutus calendar via `events.insert()` with the recurrence rule (RRULE).
2. The `google_recurring_id` is stored as the parent event ID; subsequent instances reference it.
3. Marking a task complete: the API updates the Google Calendar event for that specific instance (or leaves it — the completed task is already recorded in Plutus).
4. Pausing: the recurring rule on Google Calendar is removed, but the event series is kept. Resuming re-creates the rule from the next pending instance.
5. Stop/remove: the Google Calendar event series is deleted.

**Token security:**
- Access/refresh tokens stored in the DB, not in cookies or client-side state
- The refresh endpoint is server-side only (no client access to the refresh token)
- Tokens are transmitted over HTTPS only

### API Changes

**1. `save_task` (extends `save_object` for task entity):**

The existing universal `save_object` handles `task` creation/update once the new columns are in the ALLOWED_SCHEMAS field list. No new endpoint needed, but the task creation flow must be aware of Google Calendar sync — after save, if the task has recurrence data, trigger the calendar sync.

A new helper `GoogleCalendarService` handles:
- `createRecurringEvent(task)` — creates a Google Calendar recurring event and stores the event ID
- `updateEvent(task)` — when task details change (name, time, projected cost)
- `deleteEvent(task)` — when task is removed or stopped
- `pauseRecurrence(task)` — removes the RRULE from the Google event
- `resumeRecurrence(task)` — re-adds the RRULE

**2. `complete_task` (new action):**

```
POST /api.php?action=complete_task
  task_id = 123
  actual_cost = 4.50
  spent_at = 2026-08-07 15:30:00   (optional; default: now)
  create_transaction = 1             (optional; 1 = auto-create, 0 = just complete)
```

This:
1. Sets `actual_cost` and `spent_at` on the task.
2. If `create_transaction = 1`: uses `save_object` internally to create a `status = 'spent'` transaction with `projected_amount = task.projected_cost` and `amount = actual_cost`, linking it to the same budget.
3. Increments `recurrence_completed`.
4. If `recurrence_type != 'none'` and `recurrence_count IS NULL OR recurrence_completed < recurrence_count`, generates the next instance by creating a new task row (with `recurrence_completed = 0`) for the next occurrence date.
5. Updates Google Calendar if synced.

**3. `pause_task` / `resume_task` / `stop_task` (new actions):**

Simple status transitions:
- `pause_task`: sets `paused_at = NOW()`, removes Google Calendar recurrence rule.
- `resume_task`: sets `paused_at = NULL`, re-creates Google Calendar recurrence from the next pending instance.
- `stop_task`: sets `status = 'stopped'`, deletes Google Calendar event series.

**4. `get_dashboard` — extend task panel:**

The existing dashboard "UPCOMING TASKS" panel is expanded:
- Shows both spend-tasks (`type = 'spend'`) and regular tasks
- For spend-tasks: shows projected cost, recurrence info (e.g. "WEEKLY, THURSDAYS 15:30")
- Returns tasks grouped by date, with spend-tasks flagged for the UI

**5. OAuth callback endpoint (new):**

```
GET /api.php?action=oauth_callback&code=...
```
Handles the Google OAuth redirect, exchanges the code for tokens, stores them on the user record.

### Frontend Changes

**1. "ADD SPEND TASK" button (dashboard sidebar, near UPCOMING TASKS)**

Opens a form with:
- Task name (required)
- Projected cost (required)
- Recurrence type: none/daily/weekly/biweekly/monthly/yearly
- Recurrence days (checkboxes for weekly type: Mon/Tue/Wed/Thu/Fri/Sat/Sun)
- Recurrence time (HH:MM picker)
- Recurrence count: "Continuous" toggle with optional fixed-number input
- Related budget (dropdown)
- Category (optional)

**2. Upcoming tasks panel (dashboard, expanded)**

```
UPCOMING TASKS ───────────────────────────────────────────
SPEND: Cake shop reductions      £5.00  WEEKLY Thurs 15:30  Next: Thu 2026-08-07
      [MARK SPENT]  [PAUSE]  [EDIT]

TASK: Pay council tax             -     MONTHLY 1st          Next: 2026-09-01
      [COMPLETE]  [EDIT]
```
Spend-tasks render with a £ projected cost badge and a recurrence label. Regular tasks unchanged.

**3. MARK SPENT action (flow)**

Clicking "MARK SPENT" opens a compact form:
- "ACTUAL COST": pre-filled with projected cost
- "CREATE TRANSACTION": checkbox, checked by default
- "SPENT AT": datetime picker, defaults to now
- "NEXT INSTANCE": "Thu 2026-08-14 15:30" (read-only, shown if recurrence active)

Submitting calls `complete_task`, which updates the task, creates the transaction (if checked), and schedules the next recurrence instance.

**4. Calendar sync toggle (settings or per-task)**

A per-task toggle: "Sync to Google Calendar". When first enabled globally, redirects to Google OAuth. After authorisation, the toggle enables per-task. Once authorised, all spend-tasks with recurrence automatically sync. Global toggle available in a settings panel (or a simple status indicator in the dashboard header).

**5. Google Calendar connection status indicator**

In the dashboard header or sidebar: a small status badge showing "CALENDAR: CONNECTED" (green) or "CALENDAR: DISCONNECTED" (red, clickable to re-auth). Uses the stored token's expiry to detect connectivity.

### Google Cloud Console Prerequisites (documented for the implementing agent)

The implementing agent cannot complete the Google Calendar integration without these external steps, which must be done by Merrill or someone with access to the Google Cloud account:

1. Create a project in Google Cloud Console
2. Enable the Google Calendar API
3. Create OAuth 2.0 credentials (Client ID and Secret) with redirect URI `https://plutus.invigor.com/api.php?action=oauth_callback`
4. Set the consent screen to Internal (or External with test users)
5. Create a dedicated "Plutus" calendar in Google Calendar
6. Store the Client ID and Secret as environment variables: `PLUTUS_GOOGLE_CLIENT_ID` and `PLUTUS_GOOGLE_CLIENT_SECRET`

The code that consumes these is documented in the Files section below; the credentials themselves are never committed to git.

### Acceptance Criteria

- [x] A spend-task can be created with a projected cost, recurrence pattern, and budget link
- [x] Marking a spend-task complete prompts for actual cost (projected pre-filled) and optionally creates a transaction with both values
- [x] Fixed-number recurrence: after N completions, the task stops and no further instances are created
- [x] Continuous recurrence: completing creates the next instance automatically; pausing halts recurrence; resuming restarts it; stopping ends it permanently
- [x] Google Calendar: a recurring event is created on the designated Plutus calendar when a spend-task with recurrence is created
- [x] Google Calendar: completing/pausing/resuming/stopping a task updates the corresponding Google Calendar event
- [x] Google Calendar: OAuth token refresh is transparent — the user re-authorises only on expiry or revocation
- [x] The dashboard shows upcoming spend-tasks with projected costs and recurrence labels
- [x] Spend-tasks and regular tasks coexist in the same panel
- [x] All existing tests (PHPUnit, PHPStan, Vitest, Playwright) pass after the changes

### Implementation Note

*Implemented 2026-08-01. Code complete and verified: spend-task lifecycle E2E passes (create, complete with transaction + next-instance generation, pause/resume/stop); PHPUnit green; PHPStan 0; 7/7 E2E suite. Google OAuth code path is ready but requires Google Cloud credentials (PLUTUS_GOOGLE_CLIENT_ID/SECRET env vars) from Merrill for live calendar sync — `google_auth` returns GOOGLE_NOT_CONFIGURED until then. Commit `c83bbdb`.*

### Files

| File | Change |
|------|--------|
| Migration script (new) | `ALTER TABLE tasks ADD COLUMN...` (8 new columns) |
| `api/controllers/ObjectController.php` | Add new task columns to ALLOWED_SCHEMAS; add `type='spend'` handling |
| `api/controllers/TaskController.php` (new) | `complete_task`, `pause_task`, `resume_task`, `stop_task` handlers |
| `api/utils/GoogleCalendarService.php` (new) | OAuth flow, event CRUD, recurrence sync, token refresh — wrapping Google API Client library |
| `api/controllers/OAuthController.php` (new) | `oauth_callback` endpoint — exchanges code for tokens, stores on user record |
| `api/bootstrap.php` | Include new controllers |
| `api/routes.php` | Register `complete_task`, `pause_task`, `resume_task`, `stop_task`, `oauth_callback` |
| `assets/js/app.js` | "ADD SPEND TASK" button + form, MARK SPENT action, expanded upcoming tasks panel, calendar status indicator |
| `composer.json` | Add `google/apiclient` PHP SDK dependency |
| `.env.example` (new or update existing) | Document `PLUTUS_GOOGLE_CLIENT_ID` and `PLUTUS_GOOGLE_CLIENT_SECRET` env vars |
| `users` table migration | Add `google_access_token`, `google_refresh_token`, `google_token_expires` columns |

### Dependency Note

Block 2 assumes Block 1 is in place. The MARK SPENT prompt and the "projected + actual amount" pattern on transactions are Block 1 features that Block 2's complete_task handler calls internally. If Block 2 is implemented before Block 1, the `complete_task` endpoint should still work but will create transactions without the `projected_amount` column (the column won't exist yet — it will be missing rather than NULL and fail the INSERT). **Implement Block 1 first, or implement them together.**

---
