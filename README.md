# @escalated-dev/plugin-nps

Net Promoter Score survey system with automated scheduling, analytics, and contact-level history. Sends NPS surveys after ticket resolution with configurable delay and frequency throttling.

## Features

- Automated survey queuing after ticket resolution with configurable delay
- Throttling to prevent survey fatigue (min days between surveys per contact)
- NPS score calculation: promoters (9-10), passives (7-8), detractors (0-6)
- Admin notification on detractor responses
- NPS Dashboard admin page with trend analysis
- NPS Score dashboard widget (quarter size) with live score badge
- NPS contact history panel on contact detail view
- NPS Score column in ticket list view

## Hooks

| Type | Hook | Description |
|------|------|-------------|
| Action | `ticket.resolved` | Queues a survey for delivery after the configured delay |
| Filter | `ticket.list.columns` | Adds NPS Score column to ticket list |
| Cron | `every:1h` | Sends due surveys, applying throttle rules |

## Endpoints

| Method | Path | Capability |
|--------|------|-----------|
| GET | `/responses` | `view_reports` |
| POST | `/responses` | public (survey link) |
| GET | `/score` | `view_reports` |
| GET | `/settings` | `manage_settings` |
| POST | `/settings` | `manage_settings` |
