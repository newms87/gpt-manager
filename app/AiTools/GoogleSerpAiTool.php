<?php

namespace App\AiTools;

use App\Api\GoogleSerpApi\GoogleSerpApi;
use BadFunctionCallException;

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

    public function execute($params): array
    {
        $query = $params['q'] ?? null;

        if (!$query) {
            throw new BadFunctionCallException("Google SERP Api Tool requires a URL");
        }

        $results = app(GoogleSerpApi::class)->search(['q' => $query]);

        return [
            [
                'type' => 'text',
                'text' => 'Google SERP Search Query: ' . $query,
            ],
            [
                'type' => 'text',
                'text' => $results,
            ],
        ];
    }
}
