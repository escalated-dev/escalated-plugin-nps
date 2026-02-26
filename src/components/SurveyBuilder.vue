<template>
    <div :class="['min-h-screen p-6', dark ? 'bg-gray-900 text-gray-100' : 'bg-gray-50 text-gray-900']">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">Survey Configuration</h1>
                <p :class="['text-sm mt-1', dark ? 'text-gray-400' : 'text-gray-500']">
                    Customize your NPS survey and trigger settings
                </p>
            </div>
            <div class="flex items-center gap-3">
                <!-- Enabled toggle -->
                <label class="flex items-center gap-2 cursor-pointer">
                    <span :class="['text-sm font-medium', dark ? 'text-gray-300' : 'text-gray-700']">
                        {{ config.enabled ? 'Enabled' : 'Disabled' }}
                    </span>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="config.enabled"
                        @click="config.enabled = !config.enabled"
                        :class="[
                            'relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                            config.enabled ? 'bg-blue-600' : dark ? 'bg-gray-600' : 'bg-gray-300',
                        ]"
                    >
                        <span
                            :class="[
                                'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                config.enabled ? 'translate-x-5' : 'translate-x-0',
                            ]"
                        ></span>
                    </button>
                </label>

                <!-- Save button -->
                <button
                    @click="handleSave"
                    :disabled="saving"
                    class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    {{ saving ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </div>

        <!-- Success message -->
        <div
            v-if="showSuccess"
            class="mb-6 p-3 rounded-lg bg-green-500/10 border border-green-500/20 text-green-500 text-sm flex items-center gap-2"
        >
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Settings saved successfully.
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <!-- Left column: Configuration -->
            <div class="space-y-6">
                <!-- Survey Questions -->
                <div
                    :class="[
                        'rounded-xl border p-6',
                        dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200',
                    ]"
                >
                    <h2 class="text-lg font-semibold mb-4">Survey Questions</h2>

                    <!-- Main question -->
                    <div class="mb-5">
                        <label
                            :class="['block text-sm font-medium mb-2', dark ? 'text-gray-300' : 'text-gray-700']"
                        >
                            NPS Question
                        </label>
                        <textarea
                            v-model="config.question"
                            rows="2"
                            :class="[
                                'w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none',
                                dark
                                    ? 'bg-gray-700 border-gray-600 text-gray-200 placeholder-gray-500'
                                    : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400',
                            ]"
                            placeholder="How likely are you to recommend us to a friend or colleague?"
                        ></textarea>
                        <p :class="['text-xs mt-1', dark ? 'text-gray-500' : 'text-gray-400']">
                            This is the main question shown with the 0-10 rating scale.
                        </p>
                    </div>

                    <!-- Follow-up question -->
                    <div>
                        <label
                            :class="['block text-sm font-medium mb-2', dark ? 'text-gray-300' : 'text-gray-700']"
                        >
                            Follow-up Question
                        </label>
                        <textarea
                            v-model="config.follow_up_question"
                            rows="2"
                            :class="[
                                'w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none',
                                dark
                                    ? 'bg-gray-700 border-gray-600 text-gray-200 placeholder-gray-500'
                                    : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400',
                            ]"
                            placeholder="What is the main reason for your score?"
                        ></textarea>
                        <p :class="['text-xs mt-1', dark ? 'text-gray-500' : 'text-gray-400']">
                            Shown after the customer selects a score, for optional feedback.
                        </p>
                    </div>
                </div>

                <!-- Trigger Settings -->
                <div
                    :class="[
                        'rounded-xl border p-6',
                        dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200',
                    ]"
                >
                    <h2 class="text-lg font-semibold mb-4">Trigger Settings</h2>

                    <!-- Delay hours -->
                    <div class="mb-5">
                        <label
                            :class="['block text-sm font-medium mb-2', dark ? 'text-gray-300' : 'text-gray-700']"
                        >
                            Delay After Resolution (hours)
                        </label>
                        <input
                            v-model.number="config.trigger_delay_hours"
                            type="number"
                            min="0"
                            max="720"
                            :class="[
                                'w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500',
                                dark
                                    ? 'bg-gray-700 border-gray-600 text-gray-200'
                                    : 'bg-white border-gray-300 text-gray-900',
                            ]"
                        />
                        <p :class="['text-xs mt-1', dark ? 'text-gray-500' : 'text-gray-400']">
                            How long to wait after a ticket is resolved before sending the survey. Set to 0 for immediate.
                        </p>
                    </div>

                    <!-- Frequency limit -->
                    <div>
                        <label
                            :class="['block text-sm font-medium mb-2', dark ? 'text-gray-300' : 'text-gray-700']"
                        >
                            Frequency Limit (days)
                        </label>
                        <input
                            v-model.number="config.frequency_limit_days"
                            type="number"
                            min="0"
                            max="365"
                            :class="[
                                'w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500',
                                dark
                                    ? 'bg-gray-700 border-gray-600 text-gray-200'
                                    : 'bg-white border-gray-300 text-gray-900',
                            ]"
                        />
                        <p :class="['text-xs mt-1', dark ? 'text-gray-500' : 'text-gray-400']">
                            Minimum days between surveys for the same contact. Set to 0 for no limit.
                        </p>
                    </div>
                </div>

                <!-- Branding -->
                <div
                    :class="[
                        'rounded-xl border p-6',
                        dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200',
                    ]"
                >
                    <h2 class="text-lg font-semibold mb-4">Branding</h2>

                    <!-- Primary color -->
                    <div class="mb-5">
                        <label
                            :class="['block text-sm font-medium mb-2', dark ? 'text-gray-300' : 'text-gray-700']"
                        >
                            Primary Color
                        </label>
                        <div class="flex items-center gap-3">
                            <input
                                v-model="config.branding.primary_color"
                                type="color"
                                class="w-10 h-10 rounded-lg border-0 cursor-pointer p-0"
                            />
                            <input
                                v-model="config.branding.primary_color"
                                type="text"
                                :class="[
                                    'flex-1 rounded-lg border px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500',
                                    dark
                                        ? 'bg-gray-700 border-gray-600 text-gray-200'
                                        : 'bg-white border-gray-300 text-gray-900',
                                ]"
                                placeholder="#3b82f6"
                            />
                        </div>
                        <p :class="['text-xs mt-1', dark ? 'text-gray-500' : 'text-gray-400']">
                            Used for the survey form buttons and highlights.
                        </p>
                    </div>

                    <!-- Logo URL -->
                    <div>
                        <label
                            :class="['block text-sm font-medium mb-2', dark ? 'text-gray-300' : 'text-gray-700']"
                        >
                            Logo URL
                        </label>
                        <input
                            v-model="config.branding.logo_url"
                            type="url"
                            :class="[
                                'w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500',
                                dark
                                    ? 'bg-gray-700 border-gray-600 text-gray-200 placeholder-gray-500'
                                    : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400',
                            ]"
                            placeholder="https://example.com/logo.png"
                        />
                        <p :class="['text-xs mt-1', dark ? 'text-gray-500' : 'text-gray-400']">
                            Displayed at the top of the survey form. Leave empty for no logo.
                        </p>
                        <!-- Logo preview -->
                        <div v-if="config.branding.logo_url" class="mt-3 flex items-center gap-2">
                            <img
                                :src="config.branding.logo_url"
                                alt="Logo preview"
                                class="max-h-10 rounded"
                                @error="logoError = true"
                            />
                            <span
                                v-if="logoError"
                                class="text-xs text-red-500"
                            >
                                Failed to load logo image.
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right column: Preview -->
            <div>
                <div
                    :class="[
                        'rounded-xl border p-6 sticky top-6',
                        dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200',
                    ]"
                >
                    <h2 class="text-lg font-semibold mb-4">Survey Preview</h2>
                    <p :class="['text-xs mb-4', dark ? 'text-gray-500' : 'text-gray-400']">
                        This is how the survey will appear to your customers.
                    </p>

                    <!-- Preview card -->
                    <div
                        class="rounded-xl border-2 p-6 max-w-md mx-auto"
                        :style="{ borderColor: config.branding.primary_color + '30' }"
                        :class="dark ? 'bg-gray-900' : 'bg-gray-50'"
                    >
                        <!-- Logo -->
                        <div v-if="config.branding.logo_url" class="text-center mb-4">
                            <img
                                :src="config.branding.logo_url"
                                alt="Logo"
                                class="max-h-10 mx-auto rounded"
                            />
                        </div>

                        <!-- Question -->
                        <h3
                            class="text-center text-base font-semibold mb-2"
                            :class="dark ? 'text-gray-100' : 'text-gray-900'"
                        >
                            {{ config.question || 'How likely are you to recommend us?' }}
                        </h3>

                        <!-- Scale description -->
                        <p :class="['text-center text-xs mb-4', dark ? 'text-gray-500' : 'text-gray-400']">
                            Click a number below to rate your experience
                        </p>

                        <!-- Number buttons (0-10) -->
                        <div class="flex flex-wrap justify-center gap-1.5 mb-3">
                            <button
                                v-for="n in 11"
                                :key="n - 1"
                                type="button"
                                @click="previewScore = n - 1"
                                class="w-9 h-9 rounded-lg text-sm font-semibold transition-all duration-150 focus:outline-none"
                                :class="[
                                    previewScore === n - 1
                                        ? 'text-white shadow-lg scale-110'
                                        : dark
                                            ? 'text-gray-300 hover:text-white'
                                            : 'text-gray-600 hover:text-white',
                                ]"
                                :style="{
                                    backgroundColor:
                                        previewScore === n - 1
                                            ? previewScoreColor(n - 1)
                                            : dark
                                                ? '#374151'
                                                : '#e5e7eb',
                                    ...(previewScore !== n - 1
                                        ? {}
                                        : {}),
                                }"
                                @mouseenter="$event.target.style.backgroundColor = previewScoreColor(n - 1)"
                                @mouseleave="
                                    previewScore !== n - 1
                                        ? ($event.target.style.backgroundColor = dark ? '#374151' : '#e5e7eb')
                                        : null
                                "
                            >
                                {{ n - 1 }}
                            </button>
                        </div>

                        <!-- Scale labels -->
                        <div class="flex justify-between px-1 mb-4">
                            <span class="text-[10px] text-red-500">Not likely</span>
                            <span class="text-[10px] text-green-500">Extremely likely</span>
                        </div>

                        <!-- Follow-up question (shown after score selection) -->
                        <div
                            v-if="previewScore !== null"
                            class="border-t pt-4 mt-2"
                            :class="dark ? 'border-gray-700' : 'border-gray-200'"
                        >
                            <!-- Score category label -->
                            <div class="text-center mb-3">
                                <span
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium text-white"
                                    :style="{ backgroundColor: previewScoreColor(previewScore) }"
                                >
                                    {{ previewScore >= 9 ? 'Promoter' : previewScore >= 7 ? 'Passive' : 'Detractor' }}
                                    - Score: {{ previewScore }}
                                </span>
                            </div>

                            <p
                                class="text-sm font-medium mb-2"
                                :class="dark ? 'text-gray-200' : 'text-gray-700'"
                            >
                                {{ config.follow_up_question || 'What is the main reason for your score?' }}
                            </p>
                            <textarea
                                disabled
                                rows="3"
                                :class="[
                                    'w-full rounded-lg border px-3 py-2 text-sm resize-none',
                                    dark
                                        ? 'bg-gray-800 border-gray-700 text-gray-400'
                                        : 'bg-white border-gray-200 text-gray-400',
                                ]"
                                placeholder="Type your feedback here..."
                            ></textarea>
                            <button
                                type="button"
                                disabled
                                class="mt-3 w-full py-2 rounded-lg text-sm font-medium text-white transition-colors"
                                :style="{ backgroundColor: config.branding.primary_color }"
                            >
                                Submit Feedback
                            </button>
                        </div>
                    </div>

                    <!-- Preview instructions -->
                    <p
                        v-if="previewScore === null"
                        :class="['text-xs text-center mt-4', dark ? 'text-gray-500' : 'text-gray-400']"
                    >
                        Click a score number above to see the follow-up question.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, inject, onMounted } from 'vue';

const dark = inject('esc-dark', false);
const npsService = inject('nps', null);

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

const config = reactive({
    question: 'How likely are you to recommend us to a friend or colleague?',
    follow_up_question: 'What is the main reason for your score?',
    trigger_delay_hours: 24,
    frequency_limit_days: 90,
    branding: {
        primary_color: '#3b82f6',
        logo_url: '',
    },
    enabled: true,
});

const saving = ref(false);
const showSuccess = ref(false);
const logoError = ref(false);
const previewScore = ref(null);

// ---------------------------------------------------------------------------
// Score color helper
// ---------------------------------------------------------------------------

function previewScoreColor(score) {
    if (score >= 9) return '#22c55e';
    if (score >= 7) return '#eab308';
    return '#ef4444';
}

// ---------------------------------------------------------------------------
// Save handler
// ---------------------------------------------------------------------------

async function handleSave() {
    saving.value = true;
    showSuccess.value = false;

    try {
        if (npsService) {
            await npsService.saveConfig({ ...config });
        }
        showSuccess.value = true;
        setTimeout(() => {
            showSuccess.value = false;
        }, 3000);
    } catch (err) {
        console.error('[nps] Failed to save config:', err);
    } finally {
        saving.value = false;
    }
}

// ---------------------------------------------------------------------------
// Load config
// ---------------------------------------------------------------------------

async function loadConfig() {
    try {
        if (npsService) {
            await npsService.fetchConfig();
            const svcConfig = npsService.state.config;
            if (svcConfig) {
                Object.assign(config, {
                    question: svcConfig.question || config.question,
                    follow_up_question: svcConfig.follow_up_question || config.follow_up_question,
                    trigger_delay_hours: svcConfig.trigger_delay_hours ?? config.trigger_delay_hours,
                    frequency_limit_days: svcConfig.frequency_limit_days ?? config.frequency_limit_days,
                    branding: {
                        primary_color: svcConfig.branding?.primary_color || config.branding.primary_color,
                        logo_url: svcConfig.branding?.logo_url ?? config.branding.logo_url,
                    },
                    enabled: svcConfig.enabled ?? config.enabled,
                });
            }
        }
    } catch (err) {
        console.error('[nps] Failed to load config:', err);
    }
}

// ---------------------------------------------------------------------------
// Lifecycle
// ---------------------------------------------------------------------------

onMounted(() => {
    loadConfig();
});
</script>
