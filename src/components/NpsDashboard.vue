<template>
    <div :class="['min-h-screen p-6', dark ? 'bg-gray-900 text-gray-100' : 'bg-gray-50 text-gray-900']">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">NPS Dashboard</h1>
                <p :class="['text-sm mt-1', dark ? 'text-gray-400' : 'text-gray-500']">
                    Net Promoter Score analytics and response tracking
                </p>
            </div>
            <div class="flex items-center gap-3">
                <!-- Period filter -->
                <select
                    v-model="filters.period"
                    @change="loadDashboard"
                    :class="[
                        'rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500',
                        dark
                            ? 'bg-gray-800 border-gray-700 text-gray-200'
                            : 'bg-white border-gray-300 text-gray-700',
                    ]"
                >
                    <option value="7d">Last 7 days</option>
                    <option value="30d">Last 30 days</option>
                    <option value="90d">Last 90 days</option>
                    <option value="all">All time</option>
                </select>

                <!-- Team filter -->
                <select
                    v-model="filters.team_id"
                    @change="loadDashboard"
                    :class="[
                        'rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500',
                        dark
                            ? 'bg-gray-800 border-gray-700 text-gray-200'
                            : 'bg-white border-gray-300 text-gray-700',
                    ]"
                >
                    <option value="">All Teams</option>
                    <option v-for="team in availableTeams" :key="team.id" :value="team.id">
                        {{ team.name }}
                    </option>
                </select>

                <!-- Agent filter -->
                <select
                    v-model="filters.agent_id"
                    @change="loadDashboard"
                    :class="[
                        'rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500',
                        dark
                            ? 'bg-gray-800 border-gray-700 text-gray-200'
                            : 'bg-white border-gray-300 text-gray-700',
                    ]"
                >
                    <option value="">All Agents</option>
                    <option v-for="agent in availableAgents" :key="agent.id" :value="agent.id">
                        {{ agent.name }}
                    </option>
                </select>

                <!-- Category filter -->
                <select
                    v-model="filters.category"
                    @change="loadDashboard"
                    :class="[
                        'rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500',
                        dark
                            ? 'bg-gray-800 border-gray-700 text-gray-200'
                            : 'bg-white border-gray-300 text-gray-700',
                    ]"
                >
                    <option value="">All Categories</option>
                    <option v-for="cat in availableCategories" :key="cat" :value="cat">
                        {{ cat }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Loading overlay -->
        <div v-if="loading" class="flex items-center justify-center py-20">
            <div class="animate-spin rounded-full h-10 w-10 border-4 border-blue-500 border-t-transparent"></div>
        </div>

        <template v-else>
            <!-- Top section: NPS Gauge + Breakdown bars -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- NPS Score Gauge -->
                <div
                    :class="[
                        'rounded-xl border p-6 flex flex-col items-center justify-center',
                        dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200',
                    ]"
                >
                    <h2 :class="['text-sm font-medium mb-4', dark ? 'text-gray-400' : 'text-gray-500']">
                        NPS Score
                    </h2>
                    <!-- Circular gauge -->
                    <div class="relative w-48 h-48">
                        <svg viewBox="0 0 200 200" class="w-full h-full -rotate-90">
                            <!-- Background arc -->
                            <circle
                                cx="100"
                                cy="100"
                                r="85"
                                fill="none"
                                :stroke="dark ? '#374151' : '#e5e7eb'"
                                stroke-width="16"
                                stroke-linecap="round"
                                :stroke-dasharray="gaugeCircumference"
                                :stroke-dashoffset="gaugeCircumference * 0.25"
                            />
                            <!-- Score arc -->
                            <circle
                                cx="100"
                                cy="100"
                                r="85"
                                fill="none"
                                :stroke="npsColor"
                                stroke-width="16"
                                stroke-linecap="round"
                                :stroke-dasharray="gaugeCircumference"
                                :stroke-dashoffset="gaugeOffset"
                                class="transition-all duration-700 ease-out"
                            />
                        </svg>
                        <!-- Center score -->
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span
                                class="text-5xl font-bold transition-colors duration-300"
                                :style="{ color: npsColor }"
                            >
                                {{ nps.score }}
                            </span>
                            <span :class="['text-xs mt-1', dark ? 'text-gray-500' : 'text-gray-400']">
                                out of {{ nps.total }} responses
                            </span>
                        </div>
                    </div>
                    <!-- Scale -->
                    <div class="flex justify-between w-full mt-4 px-4">
                        <span class="text-xs text-red-500 font-medium">-100</span>
                        <span :class="['text-xs', dark ? 'text-gray-500' : 'text-gray-400']">0</span>
                        <span class="text-xs text-green-500 font-medium">+100</span>
                    </div>
                </div>

                <!-- Breakdown bars -->
                <div
                    :class="[
                        'rounded-xl border p-6 lg:col-span-2',
                        dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200',
                    ]"
                >
                    <h2 :class="['text-sm font-medium mb-6', dark ? 'text-gray-400' : 'text-gray-500']">
                        Score Breakdown
                    </h2>
                    <div class="space-y-6">
                        <!-- Promoters -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-green-500"></span>
                                    <span class="text-sm font-medium">Promoters (9-10)</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold">{{ nps.promoters }}</span>
                                    <span
                                        :class="[
                                            'text-xs px-2 py-0.5 rounded-full font-medium',
                                            'bg-green-500/10 text-green-500',
                                        ]"
                                    >
                                        {{ nps.promoterPct }}%
                                    </span>
                                </div>
                            </div>
                            <div
                                :class="[
                                    'w-full h-4 rounded-full overflow-hidden',
                                    dark ? 'bg-gray-700' : 'bg-gray-200',
                                ]"
                            >
                                <div
                                    class="h-full rounded-full bg-green-500 transition-all duration-500"
                                    :style="{ width: nps.promoterPct + '%' }"
                                ></div>
                            </div>
                        </div>

                        <!-- Passives -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
                                    <span class="text-sm font-medium">Passives (7-8)</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold">{{ nps.passives }}</span>
                                    <span
                                        :class="[
                                            'text-xs px-2 py-0.5 rounded-full font-medium',
                                            'bg-yellow-500/10 text-yellow-500',
                                        ]"
                                    >
                                        {{ nps.passivePct }}%
                                    </span>
                                </div>
                            </div>
                            <div
                                :class="[
                                    'w-full h-4 rounded-full overflow-hidden',
                                    dark ? 'bg-gray-700' : 'bg-gray-200',
                                ]"
                            >
                                <div
                                    class="h-full rounded-full bg-yellow-500 transition-all duration-500"
                                    :style="{ width: nps.passivePct + '%' }"
                                ></div>
                            </div>
                        </div>

                        <!-- Detractors -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-red-500"></span>
                                    <span class="text-sm font-medium">Detractors (0-6)</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold">{{ nps.detractors }}</span>
                                    <span
                                        :class="[
                                            'text-xs px-2 py-0.5 rounded-full font-medium',
                                            'bg-red-500/10 text-red-500',
                                        ]"
                                    >
                                        {{ nps.detractorPct }}%
                                    </span>
                                </div>
                            </div>
                            <div
                                :class="[
                                    'w-full h-4 rounded-full overflow-hidden',
                                    dark ? 'bg-gray-700' : 'bg-gray-200',
                                ]"
                            >
                                <div
                                    class="h-full rounded-full bg-red-500 transition-all duration-500"
                                    :style="{ width: nps.detractorPct + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trend chart (CSS-only bar chart) -->
            <div
                :class="[
                    'rounded-xl border p-6 mb-6',
                    dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200',
                ]"
            >
                <h2 :class="['text-sm font-medium mb-6', dark ? 'text-gray-400' : 'text-gray-500']">
                    NPS Trend (Last 6 Months)
                </h2>
                <div v-if="trend.length === 0" class="text-center py-10">
                    <p :class="['text-sm', dark ? 'text-gray-500' : 'text-gray-400']">
                        No trend data available yet.
                    </p>
                </div>
                <div v-else class="flex items-end justify-between gap-2" style="height: 200px">
                    <div
                        v-for="(month, idx) in trend"
                        :key="idx"
                        class="flex-1 flex flex-col items-center justify-end h-full"
                    >
                        <!-- Score label above bar -->
                        <span
                            class="text-xs font-semibold mb-1"
                            :style="{ color: trendBarColor(month.score) }"
                        >
                            {{ month.score }}
                        </span>
                        <!-- Bar -->
                        <div class="w-full flex justify-center">
                            <div
                                class="rounded-t-md transition-all duration-500"
                                :style="{
                                    width: '60%',
                                    height: trendBarHeight(month.score) + 'px',
                                    backgroundColor: trendBarColor(month.score),
                                    minHeight: '4px',
                                }"
                            ></div>
                        </div>
                        <!-- Month label -->
                        <span
                            :class="['text-xs mt-2 text-center', dark ? 'text-gray-500' : 'text-gray-400']"
                        >
                            {{ month.label }}
                        </span>
                        <!-- Response count -->
                        <span
                            :class="['text-[10px]', dark ? 'text-gray-600' : 'text-gray-300']"
                        >
                            {{ month.total }} resp.
                        </span>
                    </div>
                </div>
                <!-- Zero line indicator -->
                <div class="relative mt-1" v-if="trend.length > 0">
                    <div
                        :class="[
                            'absolute w-full border-t border-dashed',
                            dark ? 'border-gray-600' : 'border-gray-300',
                        ]"
                        :style="{ bottom: '0' }"
                    ></div>
                </div>
            </div>

            <!-- Response list table -->
            <div
                :class="[
                    'rounded-xl border overflow-hidden',
                    dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200',
                ]"
            >
                <div class="flex items-center justify-between p-4 border-b"
                    :class="dark ? 'border-gray-700' : 'border-gray-200'"
                >
                    <h2 :class="['text-sm font-medium', dark ? 'text-gray-400' : 'text-gray-500']">
                        Recent Responses
                    </h2>
                    <span :class="['text-xs', dark ? 'text-gray-500' : 'text-gray-400']">
                        {{ responses.length }} response{{ responses.length !== 1 ? 's' : '' }}
                    </span>
                </div>

                <div v-if="responses.length === 0" class="text-center py-12">
                    <p :class="['text-sm', dark ? 'text-gray-500' : 'text-gray-400']">
                        No responses yet. Surveys will be sent after ticket resolution.
                    </p>
                </div>

                <table v-else class="w-full">
                    <thead>
                        <tr :class="dark ? 'bg-gray-800/50' : 'bg-gray-50'">
                            <th
                                :class="[
                                    'text-left text-xs font-medium px-4 py-3 uppercase tracking-wider',
                                    dark ? 'text-gray-400' : 'text-gray-500',
                                ]"
                            >
                                Score
                            </th>
                            <th
                                :class="[
                                    'text-left text-xs font-medium px-4 py-3 uppercase tracking-wider',
                                    dark ? 'text-gray-400' : 'text-gray-500',
                                ]"
                            >
                                Contact
                            </th>
                            <th
                                :class="[
                                    'text-left text-xs font-medium px-4 py-3 uppercase tracking-wider',
                                    dark ? 'text-gray-400' : 'text-gray-500',
                                ]"
                            >
                                Ticket
                            </th>
                            <th
                                :class="[
                                    'text-left text-xs font-medium px-4 py-3 uppercase tracking-wider',
                                    dark ? 'text-gray-400' : 'text-gray-500',
                                ]"
                            >
                                Comment
                            </th>
                            <th
                                :class="[
                                    'text-left text-xs font-medium px-4 py-3 uppercase tracking-wider',
                                    dark ? 'text-gray-400' : 'text-gray-500',
                                ]"
                            >
                                Date
                            </th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(response, idx) in responses" :key="response.id">
                            <tr
                                :class="[
                                    'border-t cursor-pointer transition-colors',
                                    dark
                                        ? 'border-gray-700 hover:bg-gray-700/50'
                                        : 'border-gray-100 hover:bg-gray-50',
                                ]"
                                @click="toggleExpand(response.id)"
                            >
                                <!-- Score badge -->
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold text-white"
                                        :style="{ backgroundColor: scoreColor(response.score) }"
                                    >
                                        {{ response.score }}
                                    </span>
                                </td>
                                <!-- Contact -->
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium">
                                        {{ response.contact_name || response.contact_id || 'Unknown' }}
                                    </div>
                                    <div
                                        :class="['text-xs', dark ? 'text-gray-500' : 'text-gray-400']"
                                        v-if="response.contact_email"
                                    >
                                        {{ response.contact_email }}
                                    </div>
                                </td>
                                <!-- Ticket -->
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'text-sm font-mono',
                                            dark ? 'text-blue-400' : 'text-blue-600',
                                        ]"
                                    >
                                        #{{ response.ticket_id || '--' }}
                                    </span>
                                </td>
                                <!-- Comment -->
                                <td class="px-4 py-3">
                                    <span class="text-sm">
                                        {{ truncate(response.comment, 60) || '--' }}
                                    </span>
                                </td>
                                <!-- Date -->
                                <td class="px-4 py-3">
                                    <span :class="['text-sm', dark ? 'text-gray-400' : 'text-gray-500']">
                                        {{ formatDate(response.created_at) }}
                                    </span>
                                </td>
                                <!-- Expand toggle -->
                                <td class="px-4 py-3 text-center">
                                    <svg
                                        class="w-4 h-4 transition-transform duration-200"
                                        :class="{ 'rotate-180': expandedRows.has(response.id) }"
                                        :style="{ color: dark ? '#9ca3af' : '#6b7280' }"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 9l-7 7-7-7"
                                        />
                                    </svg>
                                </td>
                            </tr>
                            <!-- Expanded detail row -->
                            <tr v-if="expandedRows.has(response.id)">
                                <td
                                    colspan="6"
                                    :class="[
                                        'px-4 py-4 border-t',
                                        dark ? 'bg-gray-800/80 border-gray-700' : 'bg-gray-50 border-gray-100',
                                    ]"
                                >
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <h4
                                                :class="[
                                                    'text-xs font-medium uppercase tracking-wider mb-2',
                                                    dark ? 'text-gray-400' : 'text-gray-500',
                                                ]"
                                            >
                                                Full Comment
                                            </h4>
                                            <p class="text-sm">
                                                {{ response.comment || 'No comment provided.' }}
                                            </p>
                                        </div>
                                        <div v-if="response.follow_up_response">
                                            <h4
                                                :class="[
                                                    'text-xs font-medium uppercase tracking-wider mb-2',
                                                    dark ? 'text-gray-400' : 'text-gray-500',
                                                ]"
                                            >
                                                Follow-up Response
                                            </h4>
                                            <p class="text-sm">
                                                {{ response.follow_up_response }}
                                            </p>
                                        </div>
                                        <div v-if="response.agent_id || response.team_id || response.category">
                                            <h4
                                                :class="[
                                                    'text-xs font-medium uppercase tracking-wider mb-2',
                                                    dark ? 'text-gray-400' : 'text-gray-500',
                                                ]"
                                            >
                                                Details
                                            </h4>
                                            <div class="flex flex-wrap gap-2">
                                                <span
                                                    v-if="response.agent_id"
                                                    :class="[
                                                        'text-xs px-2 py-1 rounded-full',
                                                        dark
                                                            ? 'bg-gray-700 text-gray-300'
                                                            : 'bg-gray-200 text-gray-600',
                                                    ]"
                                                >
                                                    Agent: {{ response.agent_id }}
                                                </span>
                                                <span
                                                    v-if="response.team_id"
                                                    :class="[
                                                        'text-xs px-2 py-1 rounded-full',
                                                        dark
                                                            ? 'bg-gray-700 text-gray-300'
                                                            : 'bg-gray-200 text-gray-600',
                                                    ]"
                                                >
                                                    Team: {{ response.team_id }}
                                                </span>
                                                <span
                                                    v-if="response.category"
                                                    :class="[
                                                        'text-xs px-2 py-1 rounded-full',
                                                        dark
                                                            ? 'bg-gray-700 text-gray-300'
                                                            : 'bg-gray-200 text-gray-600',
                                                    ]"
                                                >
                                                    {{ response.category }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, computed, inject, onMounted } from 'vue';

const dark = inject('esc-dark', false);
const npsService = inject('nps', null);

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

const loading = ref(true);
const expandedRows = ref(new Set());

const filters = reactive({
    period: '30d',
    team_id: '',
    agent_id: '',
    category: '',
});

const nps = reactive({
    score: 0,
    total: 0,
    promoters: 0,
    passives: 0,
    detractors: 0,
    promoterPct: 0,
    passivePct: 0,
    detractorPct: 0,
});

const trend = ref([]);
const responses = ref([]);

const availableTeams = ref([]);
const availableAgents = ref([]);
const availableCategories = ref([]);

// ---------------------------------------------------------------------------
// Gauge calculations
// ---------------------------------------------------------------------------

const gaugeCircumference = computed(() => 2 * Math.PI * 85);

const gaugeOffset = computed(() => {
    const circ = gaugeCircumference.value;
    // NPS range is -100 to 100, map to 0 to 1 for the 75% arc
    const normalized = (nps.score + 100) / 200; // 0..1
    const arcLength = circ * 0.75; // 75% of circle used
    const filled = arcLength * normalized;
    return circ - filled;
});

const npsColor = computed(() => {
    if (nps.score >= 50) return '#22c55e';
    if (nps.score >= 20) return '#84cc16';
    if (nps.score >= 0) return '#eab308';
    if (nps.score >= -30) return '#f97316';
    return '#ef4444';
});

// ---------------------------------------------------------------------------
// Trend chart helpers
// ---------------------------------------------------------------------------

function trendBarColor(score) {
    if (score >= 50) return '#22c55e';
    if (score >= 20) return '#84cc16';
    if (score >= 0) return '#eab308';
    if (score >= -30) return '#f97316';
    return '#ef4444';
}

function trendBarHeight(score) {
    // Map -100..100 to 4..160
    const normalized = (score + 100) / 200;
    return Math.max(4, Math.round(normalized * 156) + 4);
}

// ---------------------------------------------------------------------------
// Score color helper
// ---------------------------------------------------------------------------

function scoreColor(score) {
    if (score >= 9) return '#22c55e';
    if (score >= 7) return '#eab308';
    return '#ef4444';
}

// ---------------------------------------------------------------------------
// Utility functions
// ---------------------------------------------------------------------------

function truncate(text, maxLen) {
    if (!text) return '';
    if (text.length <= maxLen) return text;
    return text.slice(0, maxLen) + '...';
}

function formatDate(iso) {
    if (!iso) return '--';
    try {
        const d = new Date(iso);
        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    } catch {
        return iso;
    }
}

function toggleExpand(id) {
    const s = new Set(expandedRows.value);
    if (s.has(id)) {
        s.delete(id);
    } else {
        s.add(id);
    }
    expandedRows.value = s;
}

// ---------------------------------------------------------------------------
// Data loading
// ---------------------------------------------------------------------------

function buildFilterParams() {
    const params = {};
    if (filters.period && filters.period !== 'all') {
        const days = parseInt(filters.period);
        if (!isNaN(days)) {
            const from = new Date();
            from.setDate(from.getDate() - days);
            params.date_from = from.toISOString().split('T')[0];
        }
    }
    if (filters.team_id) params.team_id = filters.team_id;
    if (filters.agent_id) params.agent_id = filters.agent_id;
    if (filters.category) params.category = filters.category;
    return params;
}

async function loadDashboard() {
    loading.value = true;

    try {
        if (npsService) {
            const params = buildFilterParams();
            const data = await npsService.fetchDashboardData(params);

            if (data.nps) {
                Object.assign(nps, {
                    score: data.nps.score ?? 0,
                    total: data.nps.total ?? 0,
                    promoters: data.nps.promoters ?? 0,
                    passives: data.nps.passives ?? 0,
                    detractors: data.nps.detractors ?? 0,
                    promoterPct: data.nps.promoter_pct ?? data.nps.promoterPct ?? 0,
                    passivePct: data.nps.passive_pct ?? data.nps.passivePct ?? 0,
                    detractorPct: data.nps.detractor_pct ?? data.nps.detractorPct ?? 0,
                });
            }

            if (data.trend) {
                trend.value = data.trend;
            }

            if (data.responses) {
                responses.value = data.responses;
            }

            // Populate filter options from breakdowns
            if (data.breakdown_by_team) {
                availableTeams.value = data.breakdown_by_team.map((t) => ({
                    id: t.team_id,
                    name: t.team_id,
                }));
            }
            if (data.breakdown_by_agent) {
                availableAgents.value = data.breakdown_by_agent.map((a) => ({
                    id: a.agent_id,
                    name: a.agent_id,
                }));
            }
            if (data.breakdown_by_category) {
                availableCategories.value = data.breakdown_by_category.map((c) => c.category);
            }
        } else {
            // Demo/fallback data when service is not injected
            loadDemoData();
        }
    } catch (err) {
        console.error('[nps] Failed to load dashboard:', err);
        loadDemoData();
    } finally {
        loading.value = false;
    }
}

function loadDemoData() {
    Object.assign(nps, {
        score: 42,
        total: 156,
        promoters: 87,
        passives: 38,
        detractors: 31,
        promoterPct: 55.8,
        passivePct: 24.4,
        detractorPct: 19.9,
    });

    trend.value = [
        { label: 'Sep 2025', score: 35, total: 22 },
        { label: 'Oct 2025', score: 28, total: 18 },
        { label: 'Nov 2025', score: 38, total: 30 },
        { label: 'Dec 2025', score: 45, total: 28 },
        { label: 'Jan 2026', score: 40, total: 34 },
        { label: 'Feb 2026', score: 42, total: 24 },
    ];

    responses.value = [
        {
            id: 'nps_demo_1',
            score: 10,
            contact_name: 'Alice Johnson',
            contact_email: 'alice@example.com',
            contact_id: 'c_001',
            ticket_id: '1042',
            comment: 'Great support! Resolved my issue quickly and the agent was very helpful.',
            follow_up_response: 'The response time was excellent and I felt heard throughout the process.',
            agent_id: 'agent_01',
            team_id: 'support',
            category: 'Billing',
            created_at: '2026-02-25T14:30:00Z',
        },
        {
            id: 'nps_demo_2',
            score: 8,
            contact_name: 'Bob Smith',
            contact_email: 'bob@example.com',
            contact_id: 'c_002',
            ticket_id: '1038',
            comment: 'Good experience overall, but took a bit longer than expected.',
            follow_up_response: '',
            agent_id: 'agent_02',
            team_id: 'support',
            category: 'Technical',
            created_at: '2026-02-24T09:15:00Z',
        },
        {
            id: 'nps_demo_3',
            score: 3,
            contact_name: 'Carol Davis',
            contact_email: 'carol@example.com',
            contact_id: 'c_003',
            ticket_id: '1035',
            comment: 'Had to explain my issue multiple times. Very frustrating experience.',
            follow_up_response: 'I was passed between three agents before someone could help me.',
            agent_id: 'agent_01',
            team_id: 'escalations',
            category: 'Technical',
            created_at: '2026-02-23T16:45:00Z',
        },
        {
            id: 'nps_demo_4',
            score: 9,
            contact_name: 'Dan Wilson',
            contact_email: 'dan@example.com',
            contact_id: 'c_004',
            ticket_id: '1030',
            comment: 'Excellent service!',
            follow_up_response: '',
            agent_id: 'agent_03',
            team_id: 'support',
            category: 'General',
            created_at: '2026-02-22T11:00:00Z',
        },
        {
            id: 'nps_demo_5',
            score: 6,
            contact_name: 'Eve Martinez',
            contact_email: 'eve@example.com',
            contact_id: 'c_005',
            ticket_id: '1025',
            comment: 'Average experience. Nothing special but got the job done.',
            follow_up_response: '',
            agent_id: 'agent_02',
            team_id: 'support',
            category: 'Billing',
            created_at: '2026-02-20T08:30:00Z',
        },
    ];
}

// ---------------------------------------------------------------------------
// Lifecycle
// ---------------------------------------------------------------------------

onMounted(() => {
    loadDashboard();
});
</script>
