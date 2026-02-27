<?php

namespace Escalated\Plugins\Nps\Handlers;

use Escalated\Plugins\Nps\Support\Config;
use Escalated\Plugins\Nps\Services\SurveyService;

class EventHandler
{
    /**
     * Handle the ticket.resolved event.
     *
     * Checks configuration and throttling, then queues a survey for the
     * ticket's contact.
     */
    public static function onTicketResolved($ticket, $context = []): void
    {
        $config = Config::all();

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
        if (!SurveyService::canSend($contactId)) {
            if (function_exists('escalated_log')) {
                escalated_log('nps', "Survey throttled for contact {$contactId} (ticket #{$ticketId})");
            }
            return;
        }

        // Check for already pending survey
        if (SurveyService::hasPending($contactId)) {
            return;
        }

        // Queue the survey
        $survey = SurveyService::queue($contactId, $ticketId, $agentId, $teamId, $category);

        if (function_exists('escalated_log')) {
            escalated_log('nps', "Survey queued for contact {$contactId} (ticket #{$ticketId}), send at: {$survey['send_at']}");
        }
    }

    /**
     * Cron handler: process pending survey queue.
     */
    public static function onCronHourly(): array
    {
        return SurveyService::processQueue();
    }
}
