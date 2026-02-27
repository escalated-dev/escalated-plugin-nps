<?php

namespace Escalated\Plugins\Nps\Services;

use Escalated\Plugins\Nps\Support\Config;

class SurveyService
{
    /**
     * Check if a survey can be sent to a contact based on the frequency limit.
     *
     * Returns true if enough time has passed since the contact's last survey.
     *
     * @param  string $contactId  The contact identifier.
     * @return bool
     */
    public static function canSend(string $contactId): bool
    {
        $config        = Config::all();
        $frequencyDays = (int) ($config['frequency_limit_days'] ?? 90);

        if ($frequencyDays <= 0) {
            return true; // No limit
        }

        $contactResponses = ResponseService::forContact($contactId);

        if (empty($contactResponses)) {
            // Also check pending surveys
            return !self::hasPending($contactId);
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

    // -------------------------------------------------------------------------
    // Pending survey queue
    // -------------------------------------------------------------------------

    /**
     * Read all pending surveys.
     */
    public static function allPending(): array
    {
        if (!file_exists(Config::PENDING_SURVEYS_FILE)) {
            return [];
        }

        $json = file_get_contents(Config::PENDING_SURVEYS_FILE);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Persist the pending surveys array.
     */
    public static function savePending(array $surveys): bool
    {
        if (!is_dir(Config::CONFIG_DIR)) {
            mkdir(Config::CONFIG_DIR, 0755, true);
        }

        $json = json_encode(array_values($surveys), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return file_put_contents(Config::PENDING_SURVEYS_FILE, $json, LOCK_EX) !== false;
    }

    /**
     * Check if a contact already has a pending (unsent or unresponded) survey.
     */
    public static function hasPending(string $contactId): bool
    {
        $pending = self::allPending();

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
    public static function queue(
        string $contactId,
        string $ticketId,
        string $agentId = '',
        string $teamId = '',
        string $category = ''
    ): array {
        $config = Config::all();
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

        $pending   = self::allPending();
        $pending[] = $survey;
        self::savePending($pending);

        return $survey;
    }

    /**
     * Process the pending survey queue: send surveys that are due.
     *
     * This should be called periodically (e.g. via cron).
     */
    public static function processQueue(): array
    {
        $pending   = self::allPending();
        $config    = Config::all();
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
            if (!self::canSend($contactId)) {
                $pending[$index]['status'] = 'skipped';
                $processed[] = $pending[$index];
                continue;
            }

            // Build the survey URL
            $token     = $survey['token'] ?? '';
            $surveyUrl = self::buildSurveyUrl($token);

            // Attempt to send via platform email
            $sent = false;
            if (function_exists('escalated_send_email')) {
                $sent = escalated_send_email([
                    'to'      => $contactId, // The platform resolves contact_id to email
                    'subject' => 'We\'d love your feedback',
                    'body'    => self::buildEmailBody($config, $surveyUrl),
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

        self::savePending($pending);

        return $processed;
    }

    /**
     * Build the survey URL for a given token.
     */
    public static function buildSurveyUrl(string $token): string
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
    public static function buildEmailBody(array $config, string $surveyUrl): string
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
    public static function submit(string $token, int $score, string $comment = '', string $followUpResponse = ''): ?array
    {
        $pending = self::allPending();
        $survey  = null;

        foreach ($pending as $index => $s) {
            if (($s['token'] ?? '') === $token) {
                $survey    = $s;
                $surveyIdx = $index;
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
        $response = ResponseService::save([
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
        self::savePending($pending);

        // Broadcast the new response event
        if (function_exists('escalated_broadcast')) {
            escalated_broadcast('admin', 'nps.response_received', [
                'response_id' => $response['id'],
                'contact_id'  => $response['contact_id'],
                'ticket_id'   => $response['ticket_id'],
                'score'       => $response['score'],
                'category'    => ScoringService::classify($response['score']),
                'timestamp'   => $response['created_at'],
            ]);
        }

        return $response;
    }
}
