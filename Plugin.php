<?php

/**
 * NPS Surveys Plugin for Escalated
 *
 * Net Promoter Score survey system with automated scheduling, analytics,
 * and contact-level history. Sends NPS surveys after ticket resolution
 * with configurable delay and frequency throttling. Provides NPS score
 * calculation, trend analysis, and breakdowns by agent/team/category.
 *
 * Survey configuration and response data are persisted as JSON files
 * in the plugin's config directory.
 */

// Prevent direct access
if (!defined('ESCALATED_LOADED')) {
    exit('Direct access not allowed.');
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

define('ESC_NPS_VERSION', '0.1.0');
define('ESC_NPS_SLUG', 'nps');
define('ESC_NPS_CONFIG_DIR', __DIR__ . '/config');
define('ESC_NPS_CONFIG_FILE', ESC_NPS_CONFIG_DIR . '/settings.json');
define('ESC_NPS_RESPONSES_FILE', ESC_NPS_CONFIG_DIR . '/responses.json');
define('ESC_NPS_PENDING_SURVEYS_FILE', ESC_NPS_CONFIG_DIR . '/pending_surveys.json');

// ---------------------------------------------------------------------------
// Default configuration
// ---------------------------------------------------------------------------

/**
 * Return the default survey configuration.
 *
 * Structure:
 *   question             - The NPS question text
 *   follow_up_question   - Follow-up question shown after score selection
 *   trigger_delay_hours  - Hours to wait after ticket resolution before sending
 *   frequency_limit_days - Minimum days between surveys for the same contact
 *   branding             - { primary_color, logo_url }
 *   enabled              - Whether the NPS system is active
 */
function esc_nps_default_config(): array
{
    return [
        'question'             => 'How likely are you to recommend us to a friend or colleague?',
        'follow_up_question'   => 'What is the main reason for your score?',
        'trigger_delay_hours'  => 24,
        'frequency_limit_days' => 90,
        'branding'             => [
            'primary_color' => '#3b82f6',
            'logo_url'      => '',
        ],
        'enabled'              => true,
    ];
}

// ---------------------------------------------------------------------------
// Configuration storage helpers
// ---------------------------------------------------------------------------

/**
 * Read the current NPS configuration.
 */
function esc_nps_get_config(): array
{
    if (!file_exists(ESC_NPS_CONFIG_FILE)) {
        return esc_nps_default_config();
    }

    $json = file_get_contents(ESC_NPS_CONFIG_FILE);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        return esc_nps_default_config();
    }

    return array_replace_recursive(esc_nps_default_config(), $data);
}

/**
 * Persist NPS configuration.
 */
function esc_nps_save_config(array $config): bool
{
    if (!is_dir(ESC_NPS_CONFIG_DIR)) {
        mkdir(ESC_NPS_CONFIG_DIR, 0755, true);
    }

    $config = array_replace_recursive(esc_nps_default_config(), $config);
    $json   = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return file_put_contents(ESC_NPS_CONFIG_FILE, $json, LOCK_EX) !== false;
}

// ---------------------------------------------------------------------------
// Response storage helpers
// ---------------------------------------------------------------------------

/**
 * Return an empty response template.
 *
 * Response structure:
 *   id                 - Unique response identifier (nps_xxxx)
 *   contact_id         - The contact who submitted the response
 *   ticket_id          - The ticket that triggered the survey
 *   score              - NPS score (0-10)
 *   comment            - Optional comment from the follow-up question
 *   follow_up_response - Optional extended follow-up text
 *   agent_id           - Agent who handled the ticket
 *   team_id            - Team the ticket was assigned to
 *   category           - Ticket category at time of survey
 *   created_at         - ISO-8601 timestamp of response submission
 */
function esc_nps_response_template(): array
{
    return [
        'id'                 => '',
        'contact_id'         => '',
        'ticket_id'          => '',
        'score'              => null,
        'comment'            => '',
        'follow_up_response' => '',
        'agent_id'           => '',
        'team_id'            => '',
        'category'           => '',
        'created_at'         => '',
    ];
}

/**
 * Read all NPS responses from the JSON file.
 */
function esc_nps_get_all_responses(): array
{
    if (!file_exists(ESC_NPS_RESPONSES_FILE)) {
        return [];
    }

    $json = file_get_contents(ESC_NPS_RESPONSES_FILE);
    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

/**
 * Persist the full responses array.
 */
function esc_nps_save_all_responses(array $responses): bool
{
    if (!is_dir(ESC_NPS_CONFIG_DIR)) {
        mkdir(ESC_NPS_CONFIG_DIR, 0755, true);
    }

    $json = json_encode(array_values($responses), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return file_put_contents(ESC_NPS_RESPONSES_FILE, $json, LOCK_EX) !== false;
}

/**
 * Save a single NPS response. Returns the saved response.
 */
function esc_nps_save_response(array $response): array
{
    $responses = esc_nps_get_all_responses();
    $now       = gmdate('Y-m-d\TH:i:s\Z');

    // Assign an ID if new
    if (empty($response['id'])) {
        $response['id']         = 'nps_' . bin2hex(random_bytes(8));
        $response['created_at'] = $now;
    }

    // Merge with template to ensure all keys exist
    $response = array_merge(esc_nps_response_template(), $response);

    // Ensure score is an integer 0-10
    $response['score'] = max(0, min(10, (int) $response['score']));

    // Update existing or append new
    $found = false;
    foreach ($responses as $index => $existing) {
        if (($existing['id'] ?? '') === $response['id']) {
            $responses[$index] = $response;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $responses[] = $response;
    }

    esc_nps_save_all_responses($responses);

    return $response;
}

/**
 * Get responses filtered by optional criteria.
 *
 * @param  array $filters  Optional: { contact_id, ticket_id, agent_id, team_id, category, date_from, date_to, limit, offset }
 * @return array
 */
function esc_nps_get_responses(array $filters = []): array
{
    $responses = esc_nps_get_all_responses();

    // Sort newest first
    usort($responses, function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    // Apply filters
    if (!empty($filters['contact_id'])) {
        $responses = array_filter($responses, function ($r) use ($filters) {
            return (string) ($r['contact_id'] ?? '') === (string) $filters['contact_id'];
        });
    }

    if (!empty($filters['ticket_id'])) {
        $responses = array_filter($responses, function ($r) use ($filters) {
            return (string) ($r['ticket_id'] ?? '') === (string) $filters['ticket_id'];
        });
    }

    if (!empty($filters['agent_id'])) {
        $responses = array_filter($responses, function ($r) use ($filters) {
            return (string) ($r['agent_id'] ?? '') === (string) $filters['agent_id'];
        });
    }

    if (!empty($filters['team_id'])) {
        $responses = array_filter($responses, function ($r) use ($filters) {
            return (string) ($r['team_id'] ?? '') === (string) $filters['team_id'];
        });
    }

    if (!empty($filters['category'])) {
        $responses = array_filter($responses, function ($r) use ($filters) {
            return (string) ($r['category'] ?? '') === (string) $filters['category'];
        });
    }

    if (!empty($filters['date_from'])) {
        $fromTs = strtotime($filters['date_from']);
        $responses = array_filter($responses, function ($r) use ($fromTs) {
            return strtotime($r['created_at'] ?? '') >= $fromTs;
        });
    }

    if (!empty($filters['date_to'])) {
        $toTs = strtotime($filters['date_to'] . ' 23:59:59');
        $responses = array_filter($responses, function ($r) use ($toTs) {
            return strtotime($r['created_at'] ?? '') <= $toTs;
        });
    }

    $responses = array_values($responses);

    // Apply offset and limit
    $offset = (int) ($filters['offset'] ?? 0);
    $limit  = (int) ($filters['limit'] ?? 0);

    if ($offset > 0) {
        $responses = array_slice($responses, $offset);
    }

    if ($limit > 0) {
        $responses = array_slice($responses, 0, $limit);
    }

    return $responses;
}

/**
 * Get all responses for a specific contact (NPS history).
 */
function esc_nps_get_contact_responses(string $contact_id): array
{
    return esc_nps_get_responses(['contact_id' => $contact_id]);
}

// ---------------------------------------------------------------------------
// NPS score calculation
// ---------------------------------------------------------------------------

/**
 * Classify a score into its NPS category.
 *
 * @param  int $score  Score from 0-10.
 * @return string      'promoter', 'passive', or 'detractor'.
 */
function esc_nps_classify_score(int $score): string
{
    if ($score >= 9) {
        return 'promoter';
    }
    if ($score >= 7) {
        return 'passive';
    }
    return 'detractor';
}

/**
 * Calculate the NPS score from a set of responses.
 *
 * NPS = (promoters% - detractors%) where:
 *   promoters  = scores 9-10
 *   passives   = scores 7-8
 *   detractors = scores 0-6
 *
 * Returns a value from -100 to 100.
 *
 * @param  array $responses  Array of response records.
 * @return array  {
 *   score, total, promoters, passives, detractors,
 *   promoter_pct, passive_pct, detractor_pct
 * }
 */
function esc_nps_calculate_nps(array $responses): array
{
    $total      = count($responses);
    $promoters  = 0;
    $passives   = 0;
    $detractors = 0;

    foreach ($responses as $response) {
        $score = (int) ($response['score'] ?? 0);
        $class = esc_nps_classify_score($score);

        if ($class === 'promoter') {
            $promoters++;
        } elseif ($class === 'passive') {
            $passives++;
        } else {
            $detractors++;
        }
    }

    if ($total === 0) {
        return [
            'score'         => 0,
            'total'         => 0,
            'promoters'     => 0,
            'passives'      => 0,
            'detractors'    => 0,
            'promoter_pct'  => 0,
            'passive_pct'   => 0,
            'detractor_pct' => 0,
        ];
    }

    $promoterPct  = round(($promoters / $total) * 100, 1);
    $passivePct   = round(($passives / $total) * 100, 1);
    $detractorPct = round(($detractors / $total) * 100, 1);
    $npsScore     = round($promoterPct - $detractorPct);

    return [
        'score'         => (int) $npsScore,
        'total'         => $total,
        'promoters'     => $promoters,
        'passives'      => $passives,
        'detractors'    => $detractors,
        'promoter_pct'  => $promoterPct,
        'passive_pct'   => $passivePct,
        'detractor_pct' => $detractorPct,
    ];
}

/**
 * Get NPS score trend over the last N months.
 *
 * Returns an array of monthly NPS snapshots, ordered oldest to newest.
 *
 * @param  int   $months    Number of months to look back (default 6).
 * @param  array $filters   Optional filters: { agent_id, team_id, category }
 * @return array            [ { month, label, score, total, promoters, passives, detractors } ]
 */
function esc_nps_get_score_trend(int $months = 6, array $filters = []): array
{
    $allResponses = esc_nps_get_responses($filters);
    $trend        = [];

    for ($i = $months - 1; $i >= 0; $i--) {
        $monthStart = date('Y-m-01', strtotime("-{$i} months"));
        $monthEnd   = date('Y-m-t', strtotime("-{$i} months"));
        $label      = date('M Y', strtotime($monthStart));

        $monthResponses = array_filter($allResponses, function ($r) use ($monthStart, $monthEnd) {
            $created = $r['created_at'] ?? '';
            return $created >= $monthStart && $created <= $monthEnd . ' 23:59:59';
        });

        $nps = esc_nps_calculate_nps(array_values($monthResponses));

        $trend[] = [
            'month'      => $monthStart,
            'label'      => $label,
            'score'      => $nps['score'],
            'total'      => $nps['total'],
            'promoters'  => $nps['promoters'],
            'passives'   => $nps['passives'],
            'detractors' => $nps['detractors'],
        ];
    }

    return $trend;
}

/**
 * Get NPS breakdown by agent.
 *
 * @param  array $filters  Optional: { date_from, date_to }
 * @return array           [ { agent_id, score, total, promoters, passives, detractors } ]
 */
function esc_nps_get_breakdown_by_agent(array $filters = []): array
{
    $responses = esc_nps_get_responses($filters);
    $byAgent   = [];

    foreach ($responses as $response) {
        $agentId = $response['agent_id'] ?? 'unassigned';
        if (!isset($byAgent[$agentId])) {
            $byAgent[$agentId] = [];
        }
        $byAgent[$agentId][] = $response;
    }

    $breakdown = [];
    foreach ($byAgent as $agentId => $agentResponses) {
        $nps = esc_nps_calculate_nps($agentResponses);
        $breakdown[] = array_merge($nps, ['agent_id' => $agentId]);
    }

    // Sort by score descending
    usort($breakdown, function ($a, $b) {
        return $b['score'] - $a['score'];
    });

    return $breakdown;
}

/**
 * Get NPS breakdown by team.
 *
 * @param  array $filters  Optional: { date_from, date_to }
 * @return array           [ { team_id, score, total, promoters, passives, detractors } ]
 */
function esc_nps_get_breakdown_by_team(array $filters = []): array
{
    $responses = esc_nps_get_responses($filters);
    $byTeam    = [];

    foreach ($responses as $response) {
        $teamId = $response['team_id'] ?? 'unassigned';
        if (!isset($byTeam[$teamId])) {
            $byTeam[$teamId] = [];
        }
        $byTeam[$teamId][] = $response;
    }

    $breakdown = [];
    foreach ($byTeam as $teamId => $teamResponses) {
        $nps = esc_nps_calculate_nps($teamResponses);
        $breakdown[] = array_merge($nps, ['team_id' => $teamId]);
    }

    usort($breakdown, function ($a, $b) {
        return $b['score'] - $a['score'];
    });

    return $breakdown;
}

/**
 * Get NPS breakdown by ticket category.
 *
 * @param  array $filters  Optional: { date_from, date_to }
 * @return array           [ { category, score, total, promoters, passives, detractors } ]
 */
function esc_nps_get_breakdown_by_category(array $filters = []): array
{
    $responses   = esc_nps_get_responses($filters);
    $byCategory  = [];

    foreach ($responses as $response) {
        $category = $response['category'] ?? 'uncategorized';
        if (!isset($byCategory[$category])) {
            $byCategory[$category] = [];
        }
        $byCategory[$category][] = $response;
    }

    $breakdown = [];
    foreach ($byCategory as $category => $catResponses) {
        $nps = esc_nps_calculate_nps($catResponses);
        $breakdown[] = array_merge($nps, ['category' => $category]);
    }

    usort($breakdown, function ($a, $b) {
        return $b['score'] - $a['score'];
    });

    return $breakdown;
}

// ---------------------------------------------------------------------------
// Survey throttling
// ---------------------------------------------------------------------------

/**
 * Check if a survey can be sent to a contact based on the frequency limit.
 *
 * Returns true if enough time has passed since the contact's last survey.
 *
 * @param  string $contactId  The contact identifier.
 * @return bool
 */
function esc_nps_can_send_survey(string $contactId): bool
{
    $config        = esc_nps_get_config();
    $frequencyDays = (int) ($config['frequency_limit_days'] ?? 90);

    if ($frequencyDays <= 0) {
        return true; // No limit
    }

    $contactResponses = esc_nps_get_contact_responses($contactId);

    if (empty($contactResponses)) {
        // Also check pending surveys
        return !esc_nps_has_pending_survey($contactId);
    }

    // Responses are already sorted newest first
    $lastResponse  = $contactResponses[0];
    $lastCreatedAt = strtotime($lastResponse['created_at'] ?? '');

    if ($lastCreatedAt === false) {
        return true;
    }

    $daysSince = (time() - $lastCreatedAt) / 86400;

    return $daysSince >= $frequencyDays;
}

// ---------------------------------------------------------------------------
// Pending survey queue
// ---------------------------------------------------------------------------

/**
 * Read all pending surveys.
 */
function esc_nps_get_pending_surveys(): array
{
    if (!file_exists(ESC_NPS_PENDING_SURVEYS_FILE)) {
        return [];
    }

    $json = file_get_contents(ESC_NPS_PENDING_SURVEYS_FILE);
    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

/**
 * Persist the pending surveys array.
 */
function esc_nps_save_pending_surveys(array $surveys): bool
{
    if (!is_dir(ESC_NPS_CONFIG_DIR)) {
        mkdir(ESC_NPS_CONFIG_DIR, 0755, true);
    }

    $json = json_encode(array_values($surveys), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return file_put_contents(ESC_NPS_PENDING_SURVEYS_FILE, $json, LOCK_EX) !== false;
}

/**
 * Check if a contact already has a pending (unsent or unresponded) survey.
 */
function esc_nps_has_pending_survey(string $contactId): bool
{
    $pending = esc_nps_get_pending_surveys();

    foreach ($pending as $survey) {
        if (($survey['contact_id'] ?? '') === $contactId && ($survey['status'] ?? '') === 'pending') {
            return true;
        }
    }

    return false;
}

/**
 * Queue a survey to be sent after the configured delay.
 *
 * @param  string $contactId  Contact identifier.
 * @param  string $ticketId   Ticket identifier.
 * @param  string $agentId    Agent who handled the ticket.
 * @param  string $teamId     Team the ticket was assigned to.
 * @param  string $category   Ticket category.
 * @return array               The queued survey record.
 */
function esc_nps_queue_survey(
    string $contactId,
    string $ticketId,
    string $agentId = '',
    string $teamId = '',
    string $category = ''
): array {
    $config = esc_nps_get_config();
    $now    = gmdate('Y-m-d\TH:i:s\Z');
    $delay  = (int) ($config['trigger_delay_hours'] ?? 24);

    $sendAt = gmdate('Y-m-d\TH:i:s\Z', time() + ($delay * 3600));

    $survey = [
        'id'         => 'srv_' . bin2hex(random_bytes(8)),
        'contact_id' => $contactId,
        'ticket_id'  => $ticketId,
        'agent_id'   => $agentId,
        'team_id'    => $teamId,
        'category'   => $category,
        'status'     => 'pending',
        'queued_at'  => $now,
        'send_at'    => $sendAt,
        'sent_at'    => null,
        'token'      => bin2hex(random_bytes(16)),
    ];

    $pending   = esc_nps_get_pending_surveys();
    $pending[] = $survey;
    esc_nps_save_pending_surveys($pending);

    return $survey;
}

/**
 * Process the pending survey queue: send surveys that are due.
 *
 * This should be called periodically (e.g. via cron).
 */
function esc_nps_process_survey_queue(): array
{
    $pending   = esc_nps_get_pending_surveys();
    $config    = esc_nps_get_config();
    $now       = time();
    $processed = [];

    if (empty($config['enabled'])) {
        return [];
    }

    foreach ($pending as $index => $survey) {
        if (($survey['status'] ?? '') !== 'pending') {
            continue;
        }

        $sendAt = strtotime($survey['send_at'] ?? '');
        if ($sendAt === false || $sendAt > $now) {
            continue; // Not yet due
        }

        // Double-check throttling
        $contactId = $survey['contact_id'] ?? '';
        if (!esc_nps_can_send_survey($contactId)) {
            $pending[$index]['status'] = 'skipped';
            $processed[] = $pending[$index];
            continue;
        }

        // Build the survey URL
        $token     = $survey['token'] ?? '';
        $surveyUrl = esc_nps_get_survey_url($token);

        // Attempt to send via platform email
        $sent = false;
        if (function_exists('escalated_send_email')) {
            $sent = escalated_send_email([
                'to'      => $contactId, // The platform resolves contact_id to email
                'subject' => 'We\'d love your feedback',
                'body'    => esc_nps_build_survey_email($config, $surveyUrl),
            ]);
        }

        if ($sent || !function_exists('escalated_send_email')) {
            $pending[$index]['status']  = 'sent';
            $pending[$index]['sent_at'] = gmdate('Y-m-d\TH:i:s\Z');
        } else {
            $pending[$index]['status'] = 'failed';
        }

        $processed[] = $pending[$index];
    }

    esc_nps_save_pending_surveys($pending);

    return $processed;
}

/**
 * Build the survey URL for a given token.
 */
function esc_nps_get_survey_url(string $token): string
{
    $baseUrl = '';
    if (function_exists('escalated_url')) {
        $baseUrl = escalated_url('');
    }

    return rtrim($baseUrl, '/') . '/nps/survey/' . $token;
}

/**
 * Build a simple survey email body.
 */
function esc_nps_build_survey_email(array $config, string $surveyUrl): string
{
    $question = $config['question'] ?? 'How likely are you to recommend us?';
    $color    = $config['branding']['primary_color'] ?? '#3b82f6';

    $body  = "<div style=\"font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;\">\n";

    if (!empty($config['branding']['logo_url'])) {
        $body .= "<div style=\"text-align: center; margin-bottom: 20px;\"><img src=\"{$config['branding']['logo_url']}\" alt=\"Logo\" style=\"max-height: 48px;\"></div>\n";
    }

    $body .= "<h2 style=\"text-align: center; color: #333;\">{$question}</h2>\n";
    $body .= "<p style=\"text-align: center; color: #666; margin-bottom: 24px;\">Click a number below to rate your experience:</p>\n";
    $body .= "<div style=\"text-align: center;\">\n";

    for ($i = 0; $i <= 10; $i++) {
        $bgColor = '#ef4444';
        if ($i >= 9) {
            $bgColor = '#22c55e';
        } elseif ($i >= 7) {
            $bgColor = '#eab308';
        }

        $body .= "<a href=\"{$surveyUrl}?score={$i}\" style=\"display: inline-block; width: 36px; height: 36px; line-height: 36px; text-align: center; margin: 2px; border-radius: 6px; background: {$bgColor}; color: #fff; text-decoration: none; font-weight: 600;\">{$i}</a>\n";
    }

    $body .= "</div>\n";
    $body .= "<p style=\"text-align: center; margin-top: 12px; font-size: 12px; color: #999;\">0 = Not likely &nbsp;&nbsp; 10 = Extremely likely</p>\n";
    $body .= "</div>";

    return $body;
}

// ---------------------------------------------------------------------------
// Public survey submission endpoint (no auth required)
// ---------------------------------------------------------------------------

/**
 * Handle a public survey submission.
 *
 * This processes incoming survey responses via the public endpoint.
 * The token authenticates the survey without requiring login.
 *
 * @param  string $token             Survey token.
 * @param  int    $score             NPS score (0-10).
 * @param  string $comment           Optional comment.
 * @param  string $followUpResponse  Optional follow-up response.
 * @return array|null                The saved response or null if invalid.
 */
function esc_nps_submit_survey(string $token, int $score, string $comment = '', string $followUpResponse = ''): ?array
{
    $pending = esc_nps_get_pending_surveys();
    $survey  = null;

    foreach ($pending as $index => $s) {
        if (($s['token'] ?? '') === $token) {
            $survey     = $s;
            $surveyIdx  = $index;
            break;
        }
    }

    if ($survey === null) {
        return null;
    }

    if (($survey['status'] ?? '') === 'completed') {
        return null; // Already responded
    }

    // Save the response
    $response = esc_nps_save_response([
        'contact_id'         => $survey['contact_id'] ?? '',
        'ticket_id'          => $survey['ticket_id'] ?? '',
        'score'              => $score,
        'comment'            => $comment,
        'follow_up_response' => $followUpResponse,
        'agent_id'           => $survey['agent_id'] ?? '',
        'team_id'            => $survey['team_id'] ?? '',
        'category'           => $survey['category'] ?? '',
    ]);

    // Mark the pending survey as completed
    $pending[$surveyIdx]['status'] = 'completed';
    esc_nps_save_pending_surveys($pending);

    // Broadcast the new response event
    if (function_exists('escalated_broadcast')) {
        escalated_broadcast('admin', 'nps.response_received', [
            'response_id' => $response['id'],
            'contact_id'  => $response['contact_id'],
            'ticket_id'   => $response['ticket_id'],
            'score'       => $response['score'],
            'category'    => esc_nps_classify_score($response['score']),
            'timestamp'   => $response['created_at'],
        ]);
    }

    return $response;
}

// ---------------------------------------------------------------------------
// Ticket resolved action: trigger survey after configurable delay
// ---------------------------------------------------------------------------

/**
 * Handle the ticket.resolved event.
 *
 * Checks configuration and throttling, then queues a survey for the
 * ticket's contact.
 */
function esc_nps_on_ticket_resolved($ticket, $context = [])
{
    $config = esc_nps_get_config();

    if (empty($config['enabled'])) {
        return;
    }

    $ticketData = is_array($ticket) ? $ticket : (array) $ticket;
    $contactId  = (string) ($ticketData['contact_id'] ?? ($ticketData['requester_id'] ?? ''));
    $ticketId   = (string) ($ticketData['id'] ?? '');
    $agentId    = (string) ($ticketData['assignee_id'] ?? ($ticketData['agent_id'] ?? ''));
    $teamId     = (string) ($ticketData['team_id'] ?? '');
    $category   = (string) ($ticketData['category'] ?? '');

    if (empty($contactId) || empty($ticketId)) {
        return;
    }

    // Check throttling
    if (!esc_nps_can_send_survey($contactId)) {
        if (function_exists('escalated_log')) {
            escalated_log('nps', "Survey throttled for contact {$contactId} (ticket #{$ticketId})");
        }
        return;
    }

    // Check for already pending survey
    if (esc_nps_has_pending_survey($contactId)) {
        return;
    }

    // Queue the survey
    $survey = esc_nps_queue_survey($contactId, $ticketId, $agentId, $teamId, $category);

    if (function_exists('escalated_log')) {
        escalated_log('nps', "Survey queued for contact {$contactId} (ticket #{$ticketId}), send at: {$survey['send_at']}");
    }
}

escalated_add_action('ticket.resolved', 'esc_nps_on_ticket_resolved', 10);

// ---------------------------------------------------------------------------
// Cron handler: process pending survey queue
// ---------------------------------------------------------------------------

escalated_add_action('escalated.cron.hourly', 'esc_nps_process_survey_queue', 10);

// ---------------------------------------------------------------------------
// Filter: ticket.list.columns -- add NPS score column
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
            $responses = esc_nps_get_responses(['ticket_id' => (string) $ticketId, 'limit' => 1]);

            if (empty($responses)) {
                return null;
            }

            return $responses[0]['score'] ?? null;
        },
    ];

    return $columns;
}, 10);

// ---------------------------------------------------------------------------
// Page component: contact sidebar NPS history
// ---------------------------------------------------------------------------

escalated_add_page_component('contact.show', 'sidebar', [
    'component' => 'NpsContactHistory',
    'props'     => [
        'pluginSlug' => ESC_NPS_SLUG,
    ],
    'order'     => 20,
    'data'      => function ($context) {
        $contactId = $context['contact_id'] ?? '';

        if (empty($contactId)) {
            return ['responses' => [], 'nps' => esc_nps_calculate_nps([])];
        }

        $responses = esc_nps_get_contact_responses($contactId);
        $nps       = esc_nps_calculate_nps($responses);

        return [
            'responses' => array_slice($responses, 0, 10),
            'nps'       => $nps,
        ];
    },
]);

// ---------------------------------------------------------------------------
// Page registration: NPS dashboard
// ---------------------------------------------------------------------------

escalated_register_page('admin/nps', [
    'title'      => 'NPS Dashboard',
    'component'  => 'NpsDashboard',
    'capability' => 'view_reports',
    'props'      => [
        'pluginSlug' => ESC_NPS_SLUG,
    ],
]);

// ---------------------------------------------------------------------------
// Admin menu item (under Reporting)
// ---------------------------------------------------------------------------

escalated_register_menu_item([
    'id'         => 'nps-dashboard',
    'label'      => 'NPS Surveys',
    'icon'       => 'chart-bar',
    'route'      => '/admin/nps',
    'parent'     => 'reporting',
    'order'      => 20,
    'capability' => 'view_reports',
]);

// ---------------------------------------------------------------------------
// Dashboard widget: NPS score
// ---------------------------------------------------------------------------

escalated_register_dashboard_widget([
    'id'         => 'nps-score',
    'title'      => 'NPS Score',
    'component'  => 'NpsWidget',
    'size'       => 'small',
    'capability' => 'view_reports',
    'data'       => function () {
        $config    = esc_nps_get_config();
        $responses = esc_nps_get_all_responses();
        $nps       = esc_nps_calculate_nps($responses);
        $trend     = esc_nps_get_score_trend(2);

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
// Activation hook
// ---------------------------------------------------------------------------

escalated_add_action('escalated_plugin_activated_nps', function () {
    // Ensure config directory exists
    if (!is_dir(ESC_NPS_CONFIG_DIR)) {
        mkdir(ESC_NPS_CONFIG_DIR, 0755, true);
    }

    // Create default config if it does not exist
    if (!file_exists(ESC_NPS_CONFIG_FILE)) {
        esc_nps_save_config(esc_nps_default_config());
    }

    // Create empty responses file if it does not exist
    if (!file_exists(ESC_NPS_RESPONSES_FILE)) {
        esc_nps_save_all_responses([]);
    }

    // Create empty pending surveys file if it does not exist
    if (!file_exists(ESC_NPS_PENDING_SURVEYS_FILE)) {
        esc_nps_save_pending_surveys([]);
    }

    // Store plugin version
    if (function_exists('escalated_update_option')) {
        escalated_update_option('nps_plugin_version', ESC_NPS_VERSION);
    }
}, 10);

// ---------------------------------------------------------------------------
// Deactivation hook
// ---------------------------------------------------------------------------

escalated_add_action('escalated_plugin_deactivated_nps', function () {
    // Preserve survey data so re-activation restores state.
    // Full cleanup only happens on uninstall.

    if (function_exists('escalated_broadcast')) {
        escalated_broadcast('admin', 'nps.deactivated', [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }
}, 10);
