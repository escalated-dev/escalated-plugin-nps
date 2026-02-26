import { defineEscalatedPlugin } from '@escalated-dev/escalated';
import NpsDashboard from './components/NpsDashboard.vue';

export default defineEscalatedPlugin({
    name: 'NPS Surveys',
    slug: 'nps',
    version: '0.1.0',
    description: 'Net Promoter Score surveys with scheduling and analytics',

    extensions: {
        reportWidgets: [
            {
                id: 'nps-score-widget',
                title: 'NPS Score',
                component: NpsDashboard,
                size: 'medium',
            },
        ],
        menuItems: [
            {
                id: 'nps-dashboard',
                label: 'NPS Surveys',
                icon: 'chart-bar',
                route: '/nps',
            },
        ],
        settingsPanels: [
            {
                id: 'nps-settings',
                title: 'NPS Surveys',
                component: NpsDashboard,
                icon: 'chart-bar',
                category: 'features',
            },
        ],
        ticketActions: [
            {
                id: 'nps-send-survey',
                label: 'Send NPS Survey',
                icon: 'star',
                handler: (ticket) => {
                    // Send NPS survey to ticket requester
                },
            },
        ],
        pageComponents: {
            'nps-dashboard': NpsDashboard,
        },
    },

    hooks: {
        'ticket.resolved': (ticket) => {
            // Schedule NPS survey after ticket resolution
        },
    },

    setup(context) {
        context.provide('nps', {
            // NPS service will be provided here
        });
    },
});
