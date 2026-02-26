<template>
    <div class="nps-dashboard">
        <div class="nps-dashboard-header">
            <h3>NPS Score</h3>
            <span class="nps-badge">--</span>
        </div>
        <div class="nps-dashboard-body">
            <p class="nps-placeholder">NPS survey analytics are coming soon.</p>
            <p class="nps-info">
                Track customer loyalty with Net Promoter Score surveys. Schedule automated surveys after ticket
                resolution, analyze trends, and segment by promoters, passives, and detractors.
            </p>
            <div class="nps-categories">
                <div class="nps-category promoters">
                    <span class="category-label">Promoters (9-10)</span>
                    <span class="category-value">--</span>
                </div>
                <div class="nps-category passives">
                    <span class="category-label">Passives (7-8)</span>
                    <span class="category-value">--</span>
                </div>
                <div class="nps-category detractors">
                    <span class="category-label">Detractors (0-6)</span>
                    <span class="category-value">--</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    ticketId: {
        type: [String, Number],
        default: null,
    },
});

const npsScore = ref(null);
</script>

<style scoped>
.nps-dashboard {
    background: var(--escalated-bg-secondary, #1e1e2e);
    border: 1px solid var(--escalated-border, #2e2e3e);
    border-radius: 8px;
    padding: 16px;
    color: var(--escalated-text, #e0e0e0);
}

.nps-dashboard-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.nps-dashboard-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}

.nps-badge {
    font-size: 20px;
    font-weight: 700;
    color: var(--escalated-text-muted, #888);
}

.nps-placeholder {
    font-size: 13px;
    color: var(--escalated-text-muted, #888);
    margin-bottom: 8px;
}

.nps-info {
    font-size: 12px;
    color: var(--escalated-text-muted, #666);
    line-height: 1.5;
    margin-bottom: 16px;
}

.nps-categories {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.nps-category {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    border-radius: 6px;
    background: var(--escalated-bg-tertiary, #2e2e3e);
}

.category-label {
    font-size: 12px;
    font-weight: 500;
}

.category-value {
    font-size: 14px;
    font-weight: 600;
}

.nps-category.promoters .category-value {
    color: #22c55e;
}

.nps-category.passives .category-value {
    color: #eab308;
}

.nps-category.detractors .category-value {
    color: #ef4444;
}
</style>
