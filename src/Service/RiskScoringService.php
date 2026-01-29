<?php

namespace App\Service;

use App\Dto\RiskDataDTO;
use App\Dto\ScoringResultDTO;

class RiskScoringService
{
    public function __construct(
        private array $hazardWeights,
        private array $thresholds
    ) {
    }

    /**
     * @param RiskDataDTO[] $riskData
     */
    public function score(array $riskData, bool $hasBasement): ScoringResultDTO
    {
        $scores = [];
        $levels = [];
        $explanations = [];
        $dataSources = [];
        $confidenceTotal = 0;

        foreach ($riskData as $data) {
            $score = $data->normalizedScore;
            if ($data->hazard === 'flood' && $hasBasement) {
                $score = min(100, $score + 10);
            }

            $scores[$data->hazard] = $score;
            $levels[$data->hazard] = $this->levelForScore($score);
            $explanations[$data->hazard] = $data->explanation;
            $dataSources[] = $data->sourceMeta;
            $confidenceTotal += $data->confidence;
        }

        $globalScore = $this->computeGlobalScore($scores);
        $globalLevel = $this->levelForScore($globalScore);
        $confidenceScore = count($riskData) > 0
            ? (int) round($confidenceTotal / count($riskData))
            : 0;

        return new ScoringResultDTO(
            scores: $scores,
            levels: $levels,
            globalScore: $globalScore,
            globalLevel: $globalLevel,
            confidenceScore: $confidenceScore,
            explanations: $explanations,
            dataSources: $dataSources
        );
    }

    private function computeGlobalScore(array $scores): int
    {
        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach ($scores as $hazard => $score) {
            $weight = (float) ($this->hazardWeights[$hazard] ?? 1.0);
            $weightedSum += $score * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0.0) {
            return 0;
        }

        return (int) round($weightedSum / $totalWeight);
    }

    private function levelForScore(int $score): string
    {
        $low = (int) ($this->thresholds['low'] ?? 25);
        $medium = (int) ($this->thresholds['medium'] ?? 50);
        $high = (int) ($this->thresholds['high'] ?? 75);

        if ($score < $low) {
            return 'faible';
        }
        if ($score < $medium) {
            return 'moyen';
        }
        if ($score < $high) {
            return 'élevé';
        }

        return 'très_élevé';
    }
}
