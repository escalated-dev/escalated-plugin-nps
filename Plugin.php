<?php

/**
 * NPS Surveys Plugin for Escalated
 *
 * Net Promoter Score survey system with automated scheduling, analytics,
 * and contact-level history. Sends NPS surveys after ticket resolution
 * with configurable delay and frequency throttling. Provides NPS score
 * calculation, trend analysis, and breakdowns by agent/team/category.
 */

if (!defined('ESCALATED_LOADED')) {
    exit('Direct access not allowed.');
}

// Load plugin classes
require_once __DIR__ . '/Support/Config.php';
require_once __DIR__ . '/Services/ResponseService.php';
require_once __DIR__ . '/Services/ScoringService.php';
require_once __DIR__ . '/Services/SurveyService.php';
require_once __DIR__ . '/Handlers/EventHandler.php';

use Escalated\Plugins\Nps\Support\Config;
use Escalated\Plugins\Nps\Services\ResponseService;
use Escalated\Plugins\Nps\Services\ScoringService;
use Escalated\Plugins\Nps\Services\SurveyService;
use Escalated\Plugins\Nps\Handlers\EventHandler;

// ---------------------------------------------------------------------------
// Action hooks
// ---------------------------------------------------------------------------

escalated_add_action('ticket.resolved', [EventHandler::class, 'onTicketResolved'], 10);
escalated_add_action('escalated.cron.hourly', [EventHandler::class, 'onCronHourly'], 10);

// ---------------------------------------------------------------------------
// Filter hooks
// ---------------------------------------------------------------------------

escalated_add_filter('ticket.list.columns', function (array $columns) {
    $columns[] = [
        'key'       => 'nps_score',
        'label'     => 'NPS',
        'sortable'  => true,
        'width'     => '80px',
        'component' => 'NpsScoreBadge',
        'resolver'  => function ($ticket) {
            $ticketId  = $ticket['id'] ?? ($ticket->id ?? '');
            $responses = ResponseService::query(['ticket_id' => (string) $ticketId, 'limit' => 1]);

            if (empty($responses)) {
                return null;
            }

            return $responses[0]['score'] ?? null;
        },
    ];

    return $columns;
}, 10);

// ---------------------------------------------------------------------------
// UI registration
// ---------------------------------------------------------------------------

escalated_add_page_component('contact.show', 'sidebar', [
    'component' => 'NpsContactHistory',
    'props'     => ['pluginSlug' => Config::SLUG],
    'order'     => 20,
    'data'      => function ($context) {
        $contactId = $context['contact_id'] ?? '';

        if (empty($contactId)) {
            return ['responses' => [], 'nps' => ScoringService::calculate([])];
        }

        $responses = ResponseService::forContact($contactId);
        $nps       = ScoringService::calculate($responses);

        return [
            'responses' => array_slice($responses, 0, 10),
            'nps'       => $nps,
        ];
    },
]);

escalated_register_page('admin/nps', [
    'title'      => 'NPS Dashboard',
    'component'  => 'NpsDashboard',
    'capability' => 'view_reports',
    'props'      => ['pluginSlug' => Config::SLUG],
]);

escalated_register_menu_item([
    'id'         => 'nps-dashboard',
    'label'      => 'NPS Surveys',
    'icon'       => 'chart-bar',
    'route'      => '/admin/nps',
    'parent'     => 'reporting',
    'order'      => 20,
    'capability' => 'view_reports',
]);

escalated_register_dashboard_widget([
    'id'         => 'nps-score',
    'title'      => 'NPS Score',
    'component'  => 'NpsWidget',
    'size'       => 'small',
    'capability' => 'view_reports',
    'data'       => function () {
        $config    = Config::all();
        $responses = ResponseService::all();
        $nps       = ScoringService::calculate($responses);
        $trend     = ScoringService::trend(2);

        // Determine trend direction
        $currentScore  = $nps['score'];
        $previousScore = isset($trend[0]) ? $trend[0]['score'] : 0;
        $trendDir      = 'stable';

        if ($currentScore > $previousScore + 2) {
            $trendDir = 'up';
        } elseif ($currentScore < $previousScore - 2) {
            $trendDir = 'down';
        }

        return [
            'score'          => $nps['score'],
            'total'          => $nps['total'],
            'promoters'      => $nps['promoters'],
            'passives'       => $nps['passives'],
            'detractors'     => $nps['detractors'],
            'promoter_pct'   => $nps['promoter_pct'],
            'passive_pct'    => $nps['passive_pct'],
            'detractor_pct'  => $nps['detractor_pct'],
            'trend'          => $trendDir,
            'previous_score' => $previousScore,
            'enabled'        => !empty($config['enabled']),
        ];
    },
]);

// ---------------------------------------------------------------------------
// Lifecycle hooks
// ---------------------------------------------------------------------------

escalated_add_action('escalated_plugin_activated_nps', [Config::class, 'onActivate'], 10);
escalated_add_action('escalated_plugin_deactivated_nps', [Config::class, 'onDeactivate'], 10);
