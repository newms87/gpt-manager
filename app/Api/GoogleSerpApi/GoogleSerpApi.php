<?php

namespace App\Api\GoogleSerpApi;

use Newms87\Danx\Api\Api;
use Newms87\Danx\Helpers\CacheHelper;

class GoogleSerpApi extends Api
{
    protected array $rateLimits = [
        ['limit' => 1000, 'interval' => 3600, 'waitPerAttempt' => 5],
    ];

    public static string $serviceName = 'Google SERP';

    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('google-serp.api_key');
    }

    public function getBaseApiUrl(): string
    {
        return config('google-serp.url');
    }

    public function search($params = []): string
    {
        $params = [
            'google_domain' => 'google.com',
            'gl'            => 'us',
            'hl'            => 'en',
            'api_key'       => $this->apiKey,
            'device'        => 'desktop',
            'num'           => 5,
        ] + $params;

        // Cache results for 24 hours
        return CacheHelper::cacheResult($params, function ($params) {
            $results = $this->get('search.json', $params)->json();

            return $this->skimResults($results);
        });
    }

    /**
     * Skim the results to only include the relevant data and strip out extra parameters
     */
    public function skimResults($results): string
    {
        $organicResults = $results['organic_results'] ?? null;
        if (empty($organicResults)) {
            return '';
        }

        $skimmed = '';

        $count = 1;
        foreach ($organicResults as $organicResult) {
            $result = $this->formatResult($organicResult);
            if ($result) {
                $skimmed .= "$count: $result";
                $count++;
            }

            foreach ($organicResult['related_results'] ?? [] as $relatedPage) {
                $result = $this->formatResult($relatedPage);
                if ($result) {
                    $skimmed .= "$count: $result";
                    $count++;
                }
            }
        }

        return $skimmed;
    }

    public function formatResult($result): string|bool
    {
        $title   = $result['title']   ?? 'No Title';
        $link    = $result['link']    ?? null;
        $snippet = $result['snippet'] ?? '';

        if (!$link) {
            return false;
        }

        return "[$title]($link)\n$snippet\n\n";
    }
}
