# Escalated Plugin: NPS

**Website:** [escalated.dev](https://escalated.dev)

Net Promoter Score survey system for Escalated with automated scheduling, analytics, and contact-level history. Sends NPS surveys after ticket resolution with configurable delay and frequency throttling.

## Features

- Automated survey queuing after ticket resolution with configurable delay
- Contact throttling to prevent survey fatigue (minimum days between surveys)
- NPS score calculation: promoters (9-10), passives (7-8), detractors (0-6)
- Admin notification on detractor responses
- NPS Dashboard admin page with score analytics
- NPS Score dashboard widget with live score badge
- NPS contact history panel on contact detail view
- NPS Score column in ticket list view
- Public survey response endpoint for email survey links

## Configuration

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `enabled` | boolean | No | Enable NPS surveys. Defaults to `true`. |
| `delay_hours` | number | No | Hours after ticket resolution before sending survey. Defaults to `24`. |
| `throttle_days` | number | No | Minimum days between surveys per contact. Defaults to `90`. |
| `question` | textarea | No | Survey question text. |
| `follow_up_promoter` | textarea | No | Follow-up text shown to promoters (score 9-10). |
| `follow_up_detractor` | textarea | No | Follow-up text shown to detractors (score 0-6). |
| `notify_on_detractor` | boolean | No | Notify the assigned agent when a detractor response is received. Defaults to `true`. |

## Admin Pages

- **nps** — NPS dashboard with score analytics, response history, and trend analysis.

## Hooks

### Actions
- `ticket.resolved` — Queues a survey for delivery after the configured delay.

### Filters
- `ticket.list.columns` — Adds an NPS Score column to the ticket list view.

### Cron
- `every:1h` — Sends due surveys with contact throttle enforcement.

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/responses` | List survey responses, optionally filtered by contact_id. |
| POST | `/responses` | Submit a survey response (public, called from survey email link). |
| GET | `/score` | Calculate NPS score for a given time period (default 30 days). |
| GET | `/settings` | Get plugin configuration. |
| POST | `/settings` | Save plugin configuration. |

## Installation

```bash
npm install @escalated-dev/plugin-nps
```

## License

MIT
