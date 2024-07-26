<?php

namespace App\AiTools;

use App\Api\GoogleSerpApi\GoogleSerpApi;
use BadFunctionCallException;
use Illuminate\Support\Facades\Log;

class GoogleSerpAiTool implements AiToolContract
{
    const string NAME        = 'google-serp';
    const string DESCRIPTION = 'Search the web using Google Search Engine and get the results';
    const array  PARAMETERS  = [
        'type'       => 'object',
        'properties' => [
            'q' => [
                'type'        => 'string',
                'description' => 'The search query terms',
            ],
        ],
    ];

    public function execute($params): AiToolResponse
    {
        $query = $params['q'] ?? null;
        Log::debug("Performing Google SERP search: $query");

        if (!$query) {
            throw new BadFunctionCallException("Google SERP Api Tool requires a URL");
        }

        $results = app(GoogleSerpApi::class)->search(['q' => $query]);

        $response = new AiToolResponse();

        return $response->addContent("Google SERP Search Query: $query\n\n$results");
    }
}
