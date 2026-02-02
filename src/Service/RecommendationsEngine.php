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
        ?string $criticality,
        array $levels = [],
        int $confidenceScore = 100
    ): array {
        $actions = $this->actionRepository->findBy(['active' => true]);

        $availability = $this->availabilityFromLevels($scores, $levels);
        $hazardRanking = $this->topHazards($scores, 2, $availability['unavailable']);
        $criticalityBoost = $this->criticalityBoost($criticality);
        $confidenceMultiplier = $this->confidenceMultiplier($confidenceScore);
        $maxCostLevel = $this->maxCostLevel($criticality);

        $ranked = [];
        foreach ($actions as $action) {
            if (!$this->matchesSector($action, $sector)) {
                continue;
            }
            if (!$this->matchesBuilding($action, $buildingType)) {
                continue;
            }
            if (!$this->meetsPrerequisites($action, $hasBasement)) {
                continue;
            }
            if (!$this->matchesHazards($action, $hazardRanking)) {
                continue;
            }

            $costLevel = $this->costLevel($action->getCost());
            if ($maxCostLevel !== null && $costLevel > $maxCostLevel) {
                continue;
            }

            $scored = $this->scoreAction(
                $action,
                $scores,
                $availability['available'],
                $hazardRanking,
                $criticality,
                $criticalityBoost,
                $hasBasement,
                $confidenceMultiplier,
                $sector,
                $buildingType,
                $costLevel,
                $confidenceScore
            );
            $ranked[] = [
                'action' => $action,
                'priority' => $scored['score'],
                'reasons' => $scored['reasons'],
            ];
        }

        usort($ranked, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        $selected = $this->applyDiversityRules($ranked);

        return array_map(
            fn ($row) => $this->snapshot($row['action'], $row['priority'], $row['reasons']),
            $selected
        );
    }

    private function availabilityFromLevels(array $scores, array $levels): array
    {
        $available = [];
        $unavailable = [];

        foreach ($scores as $hazard => $_score) {
            if ($hazard === 'global') {
                continue;
            }
            if (($levels[$hazard] ?? null) === 'indisponible') {
                $unavailable[] = $hazard;
            } else {
                $available[] = $hazard;
            }
        }

        return ['available' => $available, 'unavailable' => $unavailable];
    }

    private function topHazards(array $scores, int $limit, array $unavailable): array
    {
        $filtered = [];
        foreach ($scores as $hazard => $score) {
            if ($hazard === 'global') {
                continue;
            }
            if (in_array($hazard, $unavailable, true)) {
                continue;
            }
            $filtered[$hazard] = $score;
        }
        if ($filtered === []) {
            foreach ($scores as $hazard => $score) {
                if ($hazard === 'global') {
                    continue;
                }
                $filtered[$hazard] = $score;
            }
        }
        arsort($filtered);

        return array_slice(array_keys($filtered), 0, $limit);
    }

    private function scoreAction(
        Action $action,
        array $scores,
        array $availableHazards,
        array $hazardRanking,
        ?string $criticality,
        float $criticalityBoost,
        bool $hasBasement,
        float $confidenceMultiplier,
        ?string $sector,
        ?string $buildingType,
        int $costLevel,
        int $confidenceScore
    ): array {
        $score = 0.0;
        $reasons = [];
        $hazardTags = $action->getHazardTags();

        $matchedHazards = array_values(array_intersect($hazardTags, $availableHazards));
        foreach ($matchedHazards as $hazard) {
            $score += (float) ($scores[$hazard] ?? 0) * 0.6;
        }

        foreach ($hazardRanking as $hazard) {
            if (in_array($hazard, $hazardTags, true)) {
                $score += 12;
                $reasons[] = 'Alea prioritaire: '.$this->hazardLabel($hazard);
            }
        }

        if ($hazardTags === []) {
            $reasons[] = 'Action transversale (tous aleas).';
        } elseif ($matchedHazards !== []) {
            $primary = $matchedHazards[0];
            $reasons[] = sprintf(
                'Cible l\'alea %s (score %s/100).',
                $this->hazardLabel($primary),
                (int) ($scores[$primary] ?? 0)
            );
        }

        $score += $this->effortWeight($action->getEffort());
        $score += $this->impactWeight($action->getImpact());
        $score += $this->horizonWeight($action->getHorizon());
        $score += $this->costWeight($costLevel, $criticality);

        $reasons[] = sprintf(
            'Impact %s, effort %s, horizon %s.',
            $this->impactLabel($action->getImpact()),
            $this->effortLabel($action->getEffort()),
            $this->horizonLabel($action->getHorizon())
        );

        $costLabel = $this->costLabel($costLevel);
        if ($costLabel !== '') {
            $reasons[] = 'Cout estime: '.$costLabel.'.';
        }

        if ($sector !== null) {
            if ($action->getSectorTags() === []) {
                $score -= 4;
                $reasons[] = 'Action transverse (tous secteurs).';
            } else {
                $reasons[] = 'Secteur: '.$this->sectorLabel($sector).'.';
            }
        }

        if ($buildingType !== null) {
            if ($action->getBuildingTags() === []) {
                $score -= 4;
                $reasons[] = 'Batiment: action generique.';
            } else {
                $reasons[] = 'Batiment: '.$this->buildingLabel($buildingType).'.';
            }
        }

        if ($criticality === 'high') {
            $reasons[] = 'Criticite haute: priorite renforcee.';
        } elseif ($criticality === 'medium') {
            $reasons[] = 'Criticite moyenne: priorite renforcee.';
        }

        $score *= $criticalityBoost;

        if ($hasBasement && in_array('flood', $hazardTags, true)) {
            $score += 8;
            $reasons[] = 'Sous-sol: risque inondation renforce.';
        }

        $score *= $confidenceMultiplier;
        if ($confidenceScore < 80) {
            $reasons[] = 'Confiance donnees: '.$confidenceScore.'/100.';
        }

        return [
            'score' => $score,
            'reasons' => array_slice(array_unique($reasons), 0, 4),
        ];
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

    private function matchesBuilding(Action $action, ?string $buildingType): bool
    {
        $tags = $action->getBuildingTags();
        if (empty($tags) || $buildingType === null) {
            return true;
        }

        return in_array($buildingType, $tags, true);
    }

    private function meetsPrerequisites(Action $action, bool $hasBasement): bool
    {
        $prerequisites = $action->getPrerequisites();
        if ($prerequisites === null || $prerequisites === '') {
            return true;
        }

        if (stripos($prerequisites, 'sous-sol') !== false && !$hasBasement) {
            return false;
        }

        return true;
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

    private function costLevel(string $cost): int
    {
        $normalized = str_replace(['â‚¬', 'Â€'], '€', $cost);
        $count = substr_count($normalized, '€');
        if ($count <= 0) {
            return 1;
        }

        return min(3, $count);
    }

    private function maxCostLevel(?string $criticality): ?int
    {
        if ($criticality === 'low') {
            return 2;
        }

        return null;
    }

    private function costWeight(int $costLevel, ?string $criticality): float
    {
        return match ($criticality) {
            'high' => match ($costLevel) {
                1 => 2,
                2 => 1,
                3 => 0,
                default => 1,
            },
            'medium' => match ($costLevel) {
                1 => 4,
                2 => 2,
                3 => -1,
                default => 1,
            },
            default => match ($costLevel) {
                1 => 6,
                2 => 2,
                3 => -4,
                default => 1,
            },
        };
    }

    private function costLabel(int $costLevel): string
    {
        return match ($costLevel) {
            1 => 'faible',
            2 => 'moyen',
            3 => 'eleve',
            default => '',
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

    private function confidenceMultiplier(int $confidenceScore): float
    {
        $normalized = max(0, min(100, $confidenceScore));

        return max(0.6, $normalized / 100);
    }

    private function hazardLabel(string $hazard): string
    {
        return match ($hazard) {
            'heat' => 'Chaleur',
            'flood' => 'Inondation',
            'drought_clay' => 'Secheresse argiles',
            'cavites' => 'Cavites souterraines',
            default => $hazard,
        };
    }

    private function sectorLabel(string $sector): string
    {
        return match ($sector) {
            'tertiaire' => 'Tertiaire',
            'industrie' => 'Industrie',
            'agri' => 'Agricole',
            'collectivite' => 'Collectivite',
            'autre' => 'Autre',
            default => $sector,
        };
    }

    private function buildingLabel(string $buildingType): string
    {
        return match ($buildingType) {
            'bureau' => 'Bureau',
            'entrepot' => 'Entrepot',
            'erp' => 'ERP',
            'logement' => 'Logement',
            'autre' => 'Autre',
            default => $buildingType,
        };
    }

    private function effortLabel(string $effort): string
    {
        return match ($effort) {
            'low' => 'faible',
            'med' => 'moyen',
            'high' => 'eleve',
            default => $effort,
        };
    }

    private function impactLabel(string $impact): string
    {
        return match ($impact) {
            'low' => 'faible',
            'med' => 'moyen',
            'high' => 'eleve',
            default => $impact,
        };
    }

    private function horizonLabel(string $horizon): string
    {
        return match ($horizon) {
            'now' => 'immediat',
            '3m' => '3 mois',
            '12m' => '12 mois',
            default => $horizon,
        };
    }

    private function snapshot(Action $action, float $priorityScore, array $reasons): array
    {
        return [
            'id' => $action->getId(),
            'title' => $action->getTitle(),
            'description' => $action->getDescription(),
            'hazardTags' => $action->getHazardTags(),
            'sectorTags' => $action->getSectorTags(),
            'buildingTags' => $action->getBuildingTags(),
            'effort' => $action->getEffort(),
            'cost' => $action->getCost(),
            'impact' => $action->getImpact(),
            'horizon' => $action->getHorizon(),
            'prerequisites' => $action->getPrerequisites(),
            'priorityScore' => round($priorityScore, 2),
            'reasons' => $reasons,
        ];
    }
}
