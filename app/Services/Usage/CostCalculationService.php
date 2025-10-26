<?php

namespace App\Services\Usage;

class CostCalculationService
{
    public function calculateAiCosts(string $modelName, array $usage): array
    {
        $pricing = config("ai.models.$modelName");

        if (!$pricing) {
            return [
                'input_cost'  => null,
                'output_cost' => null,
            ];
        }

        $inputCost = $this->calculateTokenCost(
            $usage['input_tokens']        ?? 0,
            $pricing['input']             ?? 0,
            $usage['cached_input_tokens'] ?? 0,
            $pricing['cached_input']      ?? null
        );

        $outputCost = $this->calculateTokenCost(
            $usage['output_tokens'] ?? 0,
            $pricing['output']      ?? 0
        );

        $requestCost = $this->calculateRequestCost(
            $usage['request_count'] ?? 1,
            $pricing['per_request'] ?? 0
        );

        return [
            'input_cost'  => $inputCost + $requestCost,
            'output_cost' => $outputCost,
        ];
    }

    public function calculateApiCosts(string $apiName, array $usage): array
    {
        $pricing = config("apis.$apiName.pricing");

        if (!$pricing) {
            return ['input_cost' => null, 'output_cost' => null];
        }

        $requestCost = $this->calculateRequestCost(
            $usage['request_count'] ?? 1,
            $pricing['per_request'] ?? 0
        );

        $dataCost = ($usage['data_volume'] ?? 0) * ($pricing['per_unit'] ?? 0);

        return [
            'input_cost'  => $requestCost + $dataCost,
            'output_cost' => 0,
        ];
    }

    private function calculateTokenCost(
        int $tokens,
        float $costPerToken,
        int $cachedTokens = 0,
        ?float $cachedCostPerToken = null
    ): float {
        $cost = $tokens * $costPerToken;

        if ($cachedTokens > 0 && $cachedCostPerToken !== null) {
            $cost += $cachedTokens * $cachedCostPerToken;
        }

        return $cost;
    }

    private function calculateRequestCost(int $requests, float $costPerRequest): float
    {
        return $requests * $costPerRequest;
    }
}
