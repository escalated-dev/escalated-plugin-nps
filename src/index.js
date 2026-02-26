import { defineEscalatedPlugin } from '@escalated-dev/escalated';
import NpsDashboard from './components/NpsDashboard.vue';
import SurveyBuilder from './components/SurveyBuilder.vue';
import NpsWidget from './components/NpsWidget.vue';

// ---------------------------------------------------------------------------
// NPS category definitions (client-side mirror of PHP logic)
// ---------------------------------------------------------------------------

const NPS_CATEGORIES = {
    promoter: { label: 'Promoter', range: '9-10', color: '#22c55e' },
    passive: { label: 'Passive', range: '7-8', color: '#eab308' },
    detractor: { label: 'Detractor', range: '0-6', color: '#ef4444' },
};

/**
 * Classify a score into its NPS category.
 */
function classifyScore(score) {
    if (score >= 9) return 'promoter';
    if (score >= 7) return 'passive';
    return 'detractor';
}

/**
 * Calculate NPS from an array of responses.
 */
function calculateNps(responses) {
    const total = responses.length;
    if (total === 0) {
        return {
            score: 0,
            total: 0,
            promoters: 0,
            passives: 0,
            detractors: 0,
            promoterPct: 0,
            passivePct: 0,
            detractorPct: 0,
        };
    }

    let promoters = 0;
    let passives = 0;
    let detractors = 0;

    for (const r of responses) {
        const cat = classifyScore(r.score ?? 0);
        if (cat === 'promoter') promoters++;
        else if (cat === 'passive') passives++;
        else detractors++;
    }

    const promoterPct = Math.round((promoters / total) * 1000) / 10;
    const passivePct = Math.round((passives / total) * 1000) / 10;
    const detractorPct = Math.round((detractors / total) * 1000) / 10;
    const score = Math.round(promoterPct - detractorPct);

    return {
        score,
        total,
        promoters,
        passives,
        detractors,
        promoterPct,
        passivePct,
        detractorPct,
    };
}

// ---------------------------------------------------------------------------
// Plugin definition
// ---------------------------------------------------------------------------

export default defineEscalatedPlugin({
    name: 'NPS Surveys',
    slug: 'nps',
    version: '0.1.0',
    description: 'Net Promoter Score surveys with scheduling and analytics',

    extensions: {
        menuItems: [
            {
                id: 'nps-dashboard',
                label: 'NPS Surveys',
                icon: 'chart-bar',
                route: '/admin/nps',
                parent: 'reporting',
                order: 20,
                capability: 'view_reports',
            },
        ],
        settingsPanels: [
            {
                id: 'nps-settings',
                title: 'NPS Surveys',
                component: SurveyBuilder,
                icon: 'chart-bar',
                category: 'features',
            },
        ],
        reportWidgets: [
            {
                id: 'nps-score-widget',
                title: 'NPS Score',
                component: NpsWidget,
                size: 'small',
            },
        ],
        ticketActions: [
            {
                id: 'nps-send-survey',
                label: 'Send NPS Survey',
                icon: 'star',
                capability: 'manage_tickets',
                handler: async (ticket, { apiRequest }) => {
                    try {
                        await apiRequest('/surveys/send', {
                            method: 'POST',
                            body: {
                                ticket_id: ticket.id,
                                contact_id: ticket.contact_id || ticket.requester_id,
                            },
                        });
                    } catch (err) {
                        console.error('[nps] Failed to send survey:', err);
                        throw err;
                    }
                },
            },
        ],
        pageComponents: {
            'admin.nps': NpsDashboard,
            'admin.nps.settings': SurveyBuilder,
            'nps-widget': NpsWidget,
        },
    },

    hooks: {
        /**
         * Extend the reporting navigation with NPS entry.
         */
        'admin.reporting.nav': (items) => {
            return [
                ...items,
                {
                    id: 'nps-dashboard',
                    label: 'NPS Surveys',
                    icon: 'chart-bar',
                    section: 'reporting',
                    order: 20,
                },
            ];
        },

        /**
         * Extend dashboard widgets with NPS score widget.
         */
        'dashboard.widgets': (widgets) => {
            return [
                ...widgets,
                {
                    id: 'nps-score',
                    title: 'NPS Score',
                    size: 'small',
                    order: 15,
                },
            ];
        },

        /**
         * Notification when a new NPS response is received.
         */
        'notification.types': (types) => {
            return [
                ...types,
                {
                    id: 'nps.response_received',
                    label: 'NPS Response Received',
                    icon: 'star',
                    category: 'nps',
                },
            ];
        },
    },

    setup(context) {
        const { reactive, ref } = context.vue || {};
        const _reactive = reactive || ((o) => o);
        const _ref = ref || ((v) => ({ value: v }));

        // ------------------------------------------------------------------
        // Reactive state
        // ------------------------------------------------------------------
        const state = _reactive({
            config: {
                question: 'How likely are you to recommend us to a friend or colleague?',
                follow_up_question: 'What is the main reason for your score?',
                trigger_delay_hours: 24,
                frequency_limit_days: 90,
                branding: { primary_color: '#3b82f6', logo_url: '' },
                enabled: true,
            },
            responses: [],
            nps: {
                score: 0,
                total: 0,
                promoters: 0,
                passives: 0,
                detractors: 0,
                promoterPct: 0,
                passivePct: 0,
                detractorPct: 0,
            },
            trend: [],
            breakdownByAgent: [],
            breakdownByTeam: [],
            breakdownByCategory: [],
            loading: false,
            filters: {
                period: '30d',
                agent_id: '',
                team_id: '',
                category: '',
            },
        });

        const saving = _ref(false);

        // ------------------------------------------------------------------
        // API helpers
        // ------------------------------------------------------------------
        const apiBase = () => {
            if (context.route) {
                return context.route('plugins.nps.api');
            }
            return '/api/plugins/nps';
        };

        async function apiRequest(path, options = {}) {
            const url = `${apiBase()}${path}`;
            const headers = {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            };

            if (options.body && typeof options.body === 'object') {
                headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(options.body);
            }

            const response = await fetch(url, { ...options, headers });

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.message || `API request failed: ${response.status}`);
            }

            return response.json();
        }

        // ------------------------------------------------------------------
        // Config management
        // ------------------------------------------------------------------

        async function fetchConfig() {
            try {
                const data = await apiRequest('/config');
                Object.assign(state.config, data);
            } catch (err) {
                console.error('[nps] Failed to fetch config:', err);
            }
        }

        async function saveConfig(config) {
            saving.value = true;
            try {
                const data = await apiRequest('/config', {
                    method: 'PUT',
                    body: config,
                });
                Object.assign(state.config, data);
                return data;
            } catch (err) {
                console.error('[nps] Failed to save config:', err);
                throw err;
            } finally {
                saving.value = false;
            }
        }

        // ------------------------------------------------------------------
        // Response fetching and NPS calculation
        // ------------------------------------------------------------------

        async function fetchResponses(filters = {}) {
            state.loading = true;
            try {
                const params = new URLSearchParams();
                for (const [key, val] of Object.entries(filters)) {
                    if (val) params.set(key, val);
                }

                const queryStr = params.toString();
                const path = `/responses${queryStr ? '?' + queryStr : ''}`;
                const data = await apiRequest(path);

                state.responses = Array.isArray(data) ? data : data.responses || [];
                state.nps = calculateNps(state.responses);

                return state.responses;
            } catch (err) {
                console.error('[nps] Failed to fetch responses:', err);
                return [];
            } finally {
                state.loading = false;
            }
        }

        async function fetchDashboardData(filters = {}) {
            state.loading = true;
            try {
                const params = new URLSearchParams();
                for (const [key, val] of Object.entries(filters)) {
                    if (val) params.set(key, val);
                }

                const queryStr = params.toString();
                const path = `/dashboard${queryStr ? '?' + queryStr : ''}`;
                const data = await apiRequest(path);

                if (data.responses) {
                    state.responses = data.responses;
                }
                if (data.nps) {
                    state.nps = data.nps;
                }
                if (data.trend) {
                    state.trend = data.trend;
                }
                if (data.breakdown_by_agent) {
                    state.breakdownByAgent = data.breakdown_by_agent;
                }
                if (data.breakdown_by_team) {
                    state.breakdownByTeam = data.breakdown_by_team;
                }
                if (data.breakdown_by_category) {
                    state.breakdownByCategory = data.breakdown_by_category;
                }

                return data;
            } catch (err) {
                console.error('[nps] Failed to fetch dashboard data:', err);
                return {};
            } finally {
                state.loading = false;
            }
        }

        async function fetchTrend(months = 6, filters = {}) {
            try {
                const params = new URLSearchParams({ months: String(months) });
                for (const [key, val] of Object.entries(filters)) {
                    if (val) params.set(key, val);
                }

                const data = await apiRequest(`/trend?${params.toString()}`);
                state.trend = Array.isArray(data) ? data : data.trend || [];
                return state.trend;
            } catch (err) {
                console.error('[nps] Failed to fetch trend:', err);
                return [];
            }
        }

        async function fetchBreakdown(dimension, filters = {}) {
            try {
                const params = new URLSearchParams();
                for (const [key, val] of Object.entries(filters)) {
                    if (val) params.set(key, val);
                }

                const queryStr = params.toString();
                const path = `/breakdown/${dimension}${queryStr ? '?' + queryStr : ''}`;
                const data = await apiRequest(path);
                const items = Array.isArray(data) ? data : data.breakdown || [];

                if (dimension === 'agent') state.breakdownByAgent = items;
                if (dimension === 'team') state.breakdownByTeam = items;
                if (dimension === 'category') state.breakdownByCategory = items;

                return items;
            } catch (err) {
                console.error(`[nps] Failed to fetch ${dimension} breakdown:`, err);
                return [];
            }
        }

        // ------------------------------------------------------------------
        // Manual survey sending
        // ------------------------------------------------------------------

        async function sendSurvey(ticketId, contactId) {
            saving.value = true;
            try {
                return await apiRequest('/surveys/send', {
                    method: 'POST',
                    body: { ticket_id: ticketId, contact_id: contactId },
                });
            } catch (err) {
                console.error('[nps] Failed to send survey:', err);
                throw err;
            } finally {
                saving.value = false;
            }
        }

        // ------------------------------------------------------------------
        // Contact NPS history
        // ------------------------------------------------------------------

        async function fetchContactHistory(contactId) {
            try {
                const data = await apiRequest(`/contacts/${contactId}/responses`);
                return Array.isArray(data) ? data : data.responses || [];
            } catch (err) {
                console.error('[nps] Failed to fetch contact history:', err);
                return [];
            }
        }

        // ------------------------------------------------------------------
        // Provide the NPS service to child components
        // ------------------------------------------------------------------

        context.provide('nps', {
            state,
            saving,
            NPS_CATEGORIES,
            classifyScore,
            calculateNps,
            apiRequest,
            fetchConfig,
            saveConfig,
            fetchResponses,
            fetchDashboardData,
            fetchTrend,
            fetchBreakdown,
            sendSurvey,
            fetchContactHistory,
        });
    },
});
