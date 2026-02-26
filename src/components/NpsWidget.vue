<template>
    <div
        :class="[
            'rounded-xl border p-5',
            dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200',
        ]"
    >
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <h3 :class="['text-sm font-medium', dark ? 'text-gray-400' : 'text-gray-500']">
                NPS Score
            </h3>
            <!-- Trend arrow -->
            <div v-if="trendDirection !== 'stable'" class="flex items-center gap-1">
                <svg
                    v-if="trendDirection === 'up'"
                    class="w-4 h-4 text-green-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2.5"
                        d="M5 15l7-7 7 7"
                    />
                </svg>
                <svg
                    v-else-if="trendDirection === 'down'"
                    class="w-4 h-4 text-red-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2.5"
                        d="M19 9l-7 7-7-7"
                    />
                </svg>
                <span
                    :class="[
                        'text-xs font-medium',
                        trendDirection === 'up' ? 'text-green-500' : 'text-red-500',
                    ]"
                >
                    {{ trendDirection === 'up' ? '+' : '' }}{{ scoreDelta }}
                </span>
            </div>
            <div v-else class="flex items-center gap-1">
                <svg
                    class="w-4 h-4"
                    :class="dark ? 'text-gray-500' : 'text-gray-400'"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2.5"
                        d="M5 12h14"
                    />
                </svg>
                <span :class="['text-xs', dark ? 'text-gray-500' : 'text-gray-400']">Stable</span>
            </div>
        </div>

        <!-- Large NPS score -->
        <div class="text-center mb-4">
            <span
                class="text-5xl font-bold leading-none transition-colors duration-300"
                :style="{ color: scoreColor }"
            >
                {{ score }}
            </span>
            <div :class="['text-xs mt-2', dark ? 'text-gray-500' : 'text-gray-400']">
                from {{ totalResponses }} response{{ totalResponses !== 1 ? 's' : '' }}
            </div>
        </div>

        <!-- Mini stacked breakdown bar -->
        <div class="mb-3">
            <div
                :class="[
                    'w-full h-2.5 rounded-full overflow-hidden flex',
                    dark ? 'bg-gray-700' : 'bg-gray-200',
                ]"
            >
                <div
                    v-if="promoterPct > 0"
                    class="bg-green-500 transition-all duration-500"
                    :style="{ width: promoterPct + '%' }"
                ></div>
                <div
                    v-if="passivePct > 0"
                    class="bg-yellow-500 transition-all duration-500"
                    :style="{ width: passivePct + '%' }"
                ></div>
                <div
                    v-if="detractorPct > 0"
                    class="bg-red-500 transition-all duration-500"
                    :style="{ width: detractorPct + '%' }"
                ></div>
            </div>
        </div>

        <!-- Legend -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                <span :class="['text-[10px]', dark ? 'text-gray-400' : 'text-gray-500']">
                    {{ promoters }} P
                </span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                <span :class="['text-[10px]', dark ? 'text-gray-400' : 'text-gray-500']">
                    {{ passives }} N
                </span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                <span :class="['text-[10px]', dark ? 'text-gray-400' : 'text-gray-500']">
                    {{ detractors }} D
                </span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue';

const dark = inject('esc-dark', false);
const npsService = inject('nps', null);

const props = defineProps({
    data: {
        type: Object,
        default: null,
    },
});

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

const score = ref(0);
const totalResponses = ref(0);
const promoters = ref(0);
const passives = ref(0);
const detractors = ref(0);
const promoterPct = ref(0);
const passivePct = ref(0);
const detractorPct = ref(0);
const trendDirection = ref('stable');
const previousScore = ref(0);

// ---------------------------------------------------------------------------
// Computed
// ---------------------------------------------------------------------------

const scoreColor = computed(() => {
    const s = score.value;
    if (s >= 50) return '#22c55e';
    if (s >= 20) return '#84cc16';
    if (s >= 0) return '#eab308';
    if (s >= -30) return '#f97316';
    return '#ef4444';
});

const scoreDelta = computed(() => {
    const diff = score.value - previousScore.value;
    return diff > 0 ? diff : diff;
});

// ---------------------------------------------------------------------------
// Data loading
// ---------------------------------------------------------------------------

function applyData(data) {
    if (!data) return;
    score.value = data.score ?? 0;
    totalResponses.value = data.total ?? 0;
    promoters.value = data.promoters ?? 0;
    passives.value = data.passives ?? 0;
    detractors.value = data.detractors ?? 0;
    promoterPct.value = data.promoter_pct ?? data.promoterPct ?? 0;
    passivePct.value = data.passive_pct ?? data.passivePct ?? 0;
    detractorPct.value = data.detractor_pct ?? data.detractorPct ?? 0;
    trendDirection.value = data.trend ?? 'stable';
    previousScore.value = data.previous_score ?? data.previousScore ?? 0;
}

async function loadData() {
    // Use prop data if provided (server-side widget data)
    if (props.data) {
        applyData(props.data);
        return;
    }

    // Try to load from service
    if (npsService) {
        try {
            const data = await npsService.fetchDashboardData({});
            if (data.nps) {
                applyData({
                    ...data.nps,
                    trend: data.trend_direction || 'stable',
                    previous_score: data.previous_score || 0,
                });
            }
        } catch (err) {
            console.error('[nps] Widget failed to load data:', err);
            loadDemoData();
        }
    } else {
        loadDemoData();
    }
}

function loadDemoData() {
    applyData({
        score: 42,
        total: 156,
        promoters: 87,
        passives: 38,
        detractors: 31,
        promoter_pct: 55.8,
        passive_pct: 24.4,
        detractor_pct: 19.9,
        trend: 'up',
        previous_score: 35,
    });
}

// ---------------------------------------------------------------------------
// Lifecycle
// ---------------------------------------------------------------------------

onMounted(() => {
    loadData();
});
</script>
