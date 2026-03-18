import { definePlugin } from '@escalated-dev/plugin-sdk'
import type { PluginContext } from '@escalated-dev/plugin-sdk'

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface NpsResponse {
    id: string
    ticket_id: string | number
    contact_id: string | number
    score: number         // 0-10
    comment?: string
    sent_at: string
    responded_at?: string
}

interface NpsScore {
    score: number         // -100 to 100
    total: number
    promoters: number     // score 9-10
    passives: number      // score 7-8
    detractors: number    // score 0-6
    promoter_pct: number
    passive_pct: number
    detractor_pct: number
}

interface TicketResolvedEvent {
    id: string | number
    requester_id?: string | number
    assigned_to?: string | number
}

// ---------------------------------------------------------------------------
// NPS Scoring
// ---------------------------------------------------------------------------

function calculateNps(responses: NpsResponse[]): NpsScore {
    if (responses.length === 0) {
        return { score: 0, total: 0, promoters: 0, passives: 0, detractors: 0,
            promoter_pct: 0, passive_pct: 0, detractor_pct: 0 }
    }

    const promoters  = responses.filter((r) => r.score >= 9).length
    const passives   = responses.filter((r) => r.score >= 7 && r.score <= 8).length
    const detractors = responses.filter((r) => r.score <= 6).length
    const total      = responses.length

    return {
        score:         Math.round(((promoters - detractors) / total) * 100),
        total,
        promoters,
        passives,
        detractors,
        promoter_pct:  Math.round((promoters  / total) * 100),
        passive_pct:   Math.round((passives   / total) * 100),
        detractor_pct: Math.round((detractors / total) * 100),
    }
}

async function getAllResponses(ctx: PluginContext): Promise<NpsResponse[]> {
    return (await ctx.store.query('responses', {})) as unknown as NpsResponse[]
}

// ---------------------------------------------------------------------------
// Plugin definition
// ---------------------------------------------------------------------------

export default definePlugin({
    name: 'nps',
    version: '0.1.0',
    description: 'Net Promoter Score surveys with automated scheduling, analytics, and contact-level history',

    config: [
        { name: 'enabled', label: 'Enable NPS Surveys', type: 'boolean', default: true },
        { name: 'delay_hours', label: 'Send Delay (hours after resolution)', type: 'number', default: 24 },
        { name: 'throttle_days', label: 'Min Days Between Surveys per Contact', type: 'number', default: 90 },
        { name: 'question', label: 'Survey Question', type: 'textarea',
            default: 'How likely are you to recommend us to a friend or colleague?' },
        { name: 'follow_up_promoter', label: 'Promoter Follow-up Text', type: 'textarea',
            default: "That's great to hear! What did we do well?" },
        { name: 'follow_up_detractor', label: 'Detractor Follow-up Text', type: 'textarea',
            default: "We're sorry to hear that. What can we do better?" },
        { name: 'notify_on_detractor', label: 'Notify Agent on Detractor Response', type: 'boolean', default: true },
    ],

    onActivate: async (ctx) => {
        ctx.log.info('[nps] Plugin activated')
    },

    onDeactivate: async (ctx) => {
        ctx.log.info('[nps] Plugin deactivated')
    },

    // -----------------------------------------------------------------------
    // Action hooks
    // -----------------------------------------------------------------------

    actions: {
        'ticket.resolved': async (event, ctx) => {
            const ticket = event as TicketResolvedEvent
            const cfg = await ctx.config.all()
            if (!cfg.enabled) return

            // Queue survey delivery (store pending survey, cron sends it)
            const sendAt = new Date(Date.now() + Number(cfg.delay_hours ?? 24) * 3_600_000).toISOString()
            await ctx.store.insert('pending_surveys', {
                ticket_id: ticket.id,
                contact_id: ticket.requester_id ?? '',
                send_at: sendAt,
                queued_at: new Date().toISOString(),
                sent: false,
            })

            ctx.log.info('[nps] Survey queued', { ticket_id: ticket.id, send_at: sendAt })
        },
    },

    // -----------------------------------------------------------------------
    // Filter hooks
    // -----------------------------------------------------------------------

    filters: {
        'ticket.list.columns': {
            priority: 10,
            handler: (columns) => [
                ...(columns as unknown[]),
                {
                    key: 'nps_score',
                    label: 'NPS',
                    sortable: true,
                    width: '80px',
                    component: 'NpsScoreBadge',
                },
            ],
        },
    },

    // -----------------------------------------------------------------------
    // Pages, components & widgets
    // -----------------------------------------------------------------------

    pages: [
        {
            route: 'nps',
            component: 'NpsDashboard',
            layout: 'admin',
            capability: 'view_reports',
            menu: { label: 'NPS Surveys', section: 'admin', position: 20, icon: 'chart-bar' },
        },
    ],

    components: [
        {
            page: 'contact.show',
            slot: 'sidebar',
            component: 'NpsContactHistory',
            props: { pluginSlug: 'nps' },
            order: 20,
        },
    ],

    widgets: [
        {
            component: 'NpsWidget',
            label: 'NPS Score',
            size: 'quarter',
            order: 15,
            capability: 'view_reports',
            badge: async (ctx) => {
                const responses = await getAllResponses(ctx)
                const recent = responses.filter((r) => {
                    const age = Date.now() - new Date(r.responded_at ?? r.sent_at).getTime()
                    return age < 30 * 86_400_000 // last 30 days
                })
                const nps = calculateNps(recent)
                return nps.total > 0 ? nps.score : null
            },
        },
    ],

    // -----------------------------------------------------------------------
    // Endpoints
    // -----------------------------------------------------------------------

    endpoints: {
        'GET /responses': {
            capability: 'view_reports',
            handler: async (ctx, req) => {
                const contactId = req.query.contact_id
                const filter = contactId ? { contact_id: contactId } : {}
                const responses = await ctx.store.query('responses', filter,
                    { orderBy: 'responded_at', order: 'desc', limit: 100 })
                return { responses }
            },
        },
        'POST /responses': {
            // Public — called from survey email link (no auth)
            handler: async (ctx, req) => {
                const { token, score, comment } = req.body as {
                    token: string; score: number; comment?: string
                }

                if (score < 0 || score > 10) {
                    return { success: false, message: 'Score must be 0–10' }
                }

                // Validate token and find pending survey
                const surveys = await ctx.store.query('pending_surveys', { token, sent: true })
                if (surveys.length === 0) return { success: false, message: 'Invalid or expired survey link' }

                const survey = surveys[0] as unknown as { ticket_id: string; contact_id: string }
                const response = await ctx.store.insert('responses', {
                    ticket_id: survey.ticket_id,
                    contact_id: survey.contact_id,
                    score,
                    comment: comment ?? '',
                    responded_at: new Date().toISOString(),
                })

                // Notify agent if detractor
                const cfg = await ctx.config.all()
                if (cfg.notify_on_detractor && score <= 6) {
                    await ctx.emit('nps.detractor.received', { response, ticket_id: survey.ticket_id })
                }

                return { success: true, response }
            },
        },
        'GET /score': {
            capability: 'view_reports',
            handler: async (ctx, req) => {
                const days = Number(req.query.days ?? 30)
                const responses = await getAllResponses(ctx)
                const filtered = days > 0
                    ? responses.filter((r) => {
                        const age = Date.now() - new Date(r.responded_at ?? r.sent_at).getTime()
                        return age < days * 86_400_000
                    })
                    : responses
                return calculateNps(filtered)
            },
        },
        'GET /settings': {
            capability: 'manage_settings',
            handler: async (ctx) => ctx.config.all(),
        },
        'POST /settings': {
            capability: 'manage_settings',
            handler: async (ctx, req) => {
                await ctx.config.set(req.body as Record<string, unknown>)
                return { success: true }
            },
        },
    },

    // -----------------------------------------------------------------------
    // Cron — send queued surveys hourly
    // -----------------------------------------------------------------------

    cron: {
        'every:1h': async (ctx) => {
            const now = new Date().toISOString()
            const due = await ctx.store.query('pending_surveys', {
                sent: false,
                send_at: { $lte: now } as unknown as string,
            })

            const cfg = await ctx.config.all()
            const throttleDays = Number(cfg.throttle_days ?? 90)

            let sent = 0
            for (const raw of due) {
                const survey = raw as unknown as {
                    key: string; ticket_id: string; contact_id: string; send_at: string
                }

                // Check throttle — skip if contact received survey recently
                if (survey.contact_id) {
                    const recent = await ctx.store.query('responses', { contact_id: survey.contact_id },
                        { orderBy: 'responded_at', order: 'desc', limit: 1 })
                    if (recent.length > 0) {
                        const lastDate = new Date(
                            (recent[0] as unknown as { responded_at: string }).responded_at,
                        ).getTime()
                        if ((Date.now() - lastDate) < throttleDays * 86_400_000) {
                            await ctx.store.update('pending_surveys', survey.key, { sent: true, skipped: true })
                            continue
                        }
                    }
                }

                // Emit for email delivery (handled by email system or another plugin)
                await ctx.emit('nps.survey.send', {
                    ticket_id: survey.ticket_id,
                    contact_id: survey.contact_id,
                })

                await ctx.store.update('pending_surveys', survey.key, {
                    sent: true,
                    sent_at: now,
                })
                sent++
            }

            if (sent > 0) ctx.log.info(`[nps] Sent ${sent} surveys`)
        },
    },
})
