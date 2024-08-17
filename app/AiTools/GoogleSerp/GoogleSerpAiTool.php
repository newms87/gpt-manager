<?php

namespace App\AiTools\GoogleSerp;

use App\AiTools\AiToolAbstract;
use App\AiTools\AiToolContract;
use App\AiTools\AiToolResponse;
use App\Api\GoogleSerpApi\GoogleSerpApi;
use BadFunctionCallException;
use Illuminate\Support\Facades\Log;

class GoogleSerpAiTool extends AiToolAbstract implements AiToolContract
{
    public static string $name = 'google-serp';

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
