# TODO: Escalated Plugin - NPS Surveys

## Backend
- [ ] NPS survey model and migration (question, schedule, status)
- [ ] NPS response model (score, comment, contact_id, ticket_id)
- [ ] Survey scheduling engine (after resolution, periodic, one-time)
- [ ] Email survey delivery with branded template
- [ ] In-app survey delivery option
- [ ] Survey response collection endpoint
- [ ] NPS score calculation (promoters% - detractors%)
- [ ] Trend analysis over time periods
- [ ] Segmentation by team, agent, category, channel
- [ ] Follow-up automation for detractors
- [ ] Survey throttling (don't survey same person too frequently)
- [ ] Export survey data (CSV, PDF)

## Frontend
- [ ] NPS dashboard with score gauge and trend chart
- [ ] Promoters/Passives/Detractors breakdown visualization
- [ ] Survey response list with scores and comments
- [ ] Survey builder (customize question, follow-up, branding)
- [ ] Survey scheduling configuration UI
- [ ] NPS by agent/team/category breakdown charts
- [ ] Time period selector for trend analysis
- [ ] Individual response detail view
- [ ] Detractor follow-up workflow UI
- [ ] NPS report widget for main dashboard

## Integration
- [ ] Auto-trigger survey after ticket resolution (configurable delay)
- [ ] Link NPS responses to tickets and contacts
- [ ] Display NPS history on customer profile
- [ ] NPS score as ticket list column
- [ ] Alert on detractor responses for immediate follow-up
- [ ] NPS data in scheduled reports

## Configuration
- [ ] Survey trigger rules (which tickets trigger surveys)
- [ ] Survey frequency limits (max once per X days per contact)
- [ ] Survey email template customization
- [ ] Follow-up question configuration
- [ ] Detractor alert threshold and notification rules
- [ ] Survey delivery delay after resolution
