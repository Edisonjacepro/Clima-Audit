<?php

namespace App\Service;

use App\Entity\Action;
use App\Repository\ActionRepository;

class RecommendationsEngine
{
    public function __construct(private ActionRepository $actionRepository)
    {
    }

    public function buildTopActions(
        array $scores,
        ?string $sector,
        ?string $buildingType,
        bool $hasBasement,
        ?string $criticality
    ): array {
        $actions = $this->actionRepository->findBy(['active' => true]);

        $hazardRanking = $this->topHazards($scores, 2);
        $criticalityBoost = $this->criticalityBoost($criticality);

        $ranked = [];
        foreach ($actions as $action) {
            if (!$this->matchesSector($action, $sector)) {
                continue;
            }
            if (!$this->matchesHazards($action, $hazardRanking)) {
                continue;
            }

            $priorityScore = $this->scoreAction($action, $scores, $criticalityBoost, $hasBasement);
            $ranked[] = ['action' => $action, 'priority' => $priorityScore];
        }

        usort($ranked, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        $selected = $this->applyDiversityRules($ranked);

        return array_map(fn ($row) => $this->snapshot($row['action'], $row['priority']), $selected);
    }

    private function topHazards(array $scores, int $limit): array
    {
        arsort($scores);

        return array_slice(array_keys($scores), 0, $limit);
    }

    private function scoreAction(
        Action $action,
        array $scores,
        float $criticalityBoost,
        bool $hasBasement
    ): float {
        $score = 0.0;

        foreach ($action->getHazardTags() as $hazard) {
            $score += (float) ($scores[$hazard] ?? 0) * 0.6;
        }

        $score += $this->effortWeight($action->getEffort());
        $score += $this->impactWeight($action->getImpact());
        $score += $this->horizonWeight($action->getHorizon());
        $score *= $criticalityBoost;

        if ($hasBasement && in_array('flood', $action->getHazardTags(), true)) {
            $score += 8;
        }

        return $score;
    }

    private function applyDiversityRules(array $ranked): array
    {
        $selected = [];
        $highEffortCount = 0;
        $lowEffortCount = 0;

        foreach ($ranked as $row) {
            /** @var Action $action */
            $action = $row['action'];

            if ($action->getEffort() === 'high' && $highEffortCount >= 3) {
                continue;
            }

            $selected[] = $row;

            if ($action->getEffort() === 'high') {
                $highEffortCount++;
            }
            if ($action->getEffort() === 'low') {
                $lowEffortCount++;
            }

            if (count($selected) >= 10) {
                break;
            }
        }

        if ($lowEffortCount < 2) {
            $selected = $this->injectLowEffort($selected, $ranked, 2 - $lowEffortCount);
        }

        return $selected;
    }

    private function injectLowEffort(array $selected, array $ranked, int $needed): array
    {
        $existingIds = array_map(fn ($row) => $row['action']->getId(), $selected);
        foreach ($ranked as $row) {
            if ($needed <= 0) {
                break;
            }

            $action = $row['action'];
            if ($action->getEffort() !== 'low') {
                continue;
            }
            if (in_array($action->getId(), $existingIds, true)) {
                continue;
            }

            array_pop($selected);
            $selected[] = $row;
            $needed--;
        }

        return $selected;
    }

    private function matchesSector(Action $action, ?string $sector): bool
    {
        $tags = $action->getSectorTags();
        if (empty($tags) || $sector === null) {
            return true;
        }

        return in_array($sector, $tags, true);
    }

    private function matchesHazards(Action $action, array $hazards): bool
    {
        $tags = $action->getHazardTags();
        if (empty($tags)) {
            return true;
        }

        foreach ($hazards as $hazard) {
            if (in_array($hazard, $tags, true)) {
                return true;
            }
        }

        return false;
    }

    private function effortWeight(string $effort): float
    {
        return match ($effort) {
            'low' => 18,
            'med' => 8,
            'high' => 2,
            default => 5,
        };
    }

    private function impactWeight(string $impact): float
    {
        return match ($impact) {
            'high' => 15,
            'med' => 8,
            'low' => 2,
            default => 5,
        };
    }

    private function horizonWeight(string $horizon): float
    {
        return match ($horizon) {
            'now' => 12,
            '3m' => 6,
            '12m' => 2,
            default => 4,
        };
    }

    private function criticalityBoost(?string $criticality): float
    {
        return match ($criticality) {
            'high' => 1.2,
            'medium' => 1.1,
            default => 1.0,
        };
    }

    private function snapshot(Action $action, float $priorityScore): array
    {
        return [
            'id' => $action->getId(),
            'title' => $action->getTitle(),
            'description' => $action->getDescription(),
            'hazardTags' => $action->getHazardTags(),
            'sectorTags' => $action->getSectorTags(),
            'effort' => $action->getEffort(),
            'cost' => $action->getCost(),
            'impact' => $action->getImpact(),
            'horizon' => $action->getHorizon(),
            'prerequisites' => $action->getPrerequisites(),
            'priorityScore' => round($priorityScore, 2),
        ];
    }
}
