<?php

namespace App\Services\Usage;

use Illuminate\Support\Facades\Log;

class PricingService
{
    private array $apiNameMappings = [
        'openai' => 'OpenAI',
        'perplexityai' => 'PerplexityAI',
        'perplexity' => 'PerplexityAI',
    ];

    public function getModelPricing(string $apiName, string $modelName): ?array
    {
        // Try exact match first
        $pricing = config("ai.models.{$apiName}.{$modelName}");
        if ($pricing) {
            return $pricing;
        }

        // Try with normalized API name
        $normalizedApiName = $this->normalizeApiName($apiName);
        $pricing = config("ai.models.{$normalizedApiName}.{$modelName}");
        
        if (!$pricing) {
            Log::warning("No pricing found for API: {$apiName}, Model: {$modelName}");
        }
        
        return $pricing;
    }

    public function getApiPricing(string $apiName): ?array
    {
        return config("apis.{$apiName}.pricing");
    }

    private function normalizeApiName(string $apiName): string
    {
        return $this->apiNameMappings[strtolower($apiName)] ?? $apiName;
    }
}