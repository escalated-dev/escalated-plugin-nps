<?php

namespace Escalated\Plugins\Nps\Services;

class ScoringService
{
    /**
     * Classify a score into its NPS category.
     *
     * @param  int $score  Score from 0-10.
     * @return string      'promoter', 'passive', or 'detractor'.
     */
    public static function classify(int $score): string
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
    public static function calculate(array $responses): array
    {
        $total      = count($responses);
        $promoters  = 0;
        $passives   = 0;
        $detractors = 0;

        foreach ($responses as $response) {
            $score = (int) ($response['score'] ?? 0);
            $class = self::classify($score);

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
    public static function trend(int $months = 6, array $filters = []): array
    {
        $allResponses = ResponseService::query($filters);
        $trend        = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = date('Y-m-01', strtotime("-{$i} months"));
            $monthEnd   = date('Y-m-t', strtotime("-{$i} months"));
            $label      = date('M Y', strtotime($monthStart));

            $monthResponses = array_filter($allResponses, function ($r) use ($monthStart, $monthEnd) {
                $created = $r['created_at'] ?? '';
                return $created >= $monthStart && $created <= $monthEnd . ' 23:59:59';
            });

            $nps = self::calculate(array_values($monthResponses));

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
    public static function breakdownByAgent(array $filters = []): array
    {
        $responses = ResponseService::query($filters);
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
            $nps = self::calculate($agentResponses);
            $breakdown[] = array_merge($nps, ['agent_id' => $agentId]);
        }

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
    public static function breakdownByTeam(array $filters = []): array
    {
        $responses = ResponseService::query($filters);
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
            $nps = self::calculate($teamResponses);
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
    public static function breakdownByCategory(array $filters = []): array
    {
        $responses   = ResponseService::query($filters);
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
            $nps = self::calculate($catResponses);
            $breakdown[] = array_merge($nps, ['category' => $category]);
        }

        usort($breakdown, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $breakdown;
    }
}
