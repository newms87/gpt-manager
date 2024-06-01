<?php

namespace App\Services\ContentSource;

use App\Api\ConfigurableApi;
use App\Api\ConfigurableApi\ConfigurableApiConfig;
use App\Models\ContentSource\ContentSource;

class ContentSourceFetchService
{
    public function fetch(ContentSource $contentSource)
    {
        // Fetch content source data
        $apiConfig = new ConfigurableApiConfig($contentSource->config);
        $api       = new ConfigurableApi($contentSource->name, $contentSource->url, $apiConfig);

        $records = $api->listRecords();

        dump('got records', $records);
    }
}
