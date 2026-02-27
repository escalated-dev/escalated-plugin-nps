<?php

namespace Escalated\Plugins\Nps\Services;

use Escalated\Plugins\Nps\Support\Config;

class ResponseService
{
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
    public static function template(): array
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
    public static function all(): array
    {
        if (!file_exists(Config::RESPONSES_FILE)) {
            return [];
        }

        $json = file_get_contents(Config::RESPONSES_FILE);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Persist the full responses array.
     */
    public static function saveAll(array $responses): bool
    {
        if (!is_dir(Config::CONFIG_DIR)) {
            mkdir(Config::CONFIG_DIR, 0755, true);
        }

        $json = json_encode(array_values($responses), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return file_put_contents(Config::RESPONSES_FILE, $json, LOCK_EX) !== false;
    }

    /**
     * Save a single NPS response. Returns the saved response.
     */
    public static function save(array $response): array
    {
        $responses = self::all();
        $now       = gmdate('Y-m-d\TH:i:s\Z');

        // Assign an ID if new
        if (empty($response['id'])) {
            $response['id']         = 'nps_' . bin2hex(random_bytes(8));
            $response['created_at'] = $now;
        }

        // Merge with template to ensure all keys exist
        $response = array_merge(self::template(), $response);

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

        self::saveAll($responses);

        return $response;
    }

    /**
     * Get responses filtered by optional criteria.
     *
     * @param  array $filters  Optional: { contact_id, ticket_id, agent_id, team_id, category, date_from, date_to, limit, offset }
     * @return array
     */
    public static function query(array $filters = []): array
    {
        $responses = self::all();

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
    public static function forContact(string $contactId): array
    {
        return self::query(['contact_id' => $contactId]);
    }
}
