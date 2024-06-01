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

        $listUri = $apiConfig->getListUri();

        // Add the timestamp to the URL
        $timestamp = $apiConfig->getMinimumTimestamp();
        if ($contentSource->fetched_at) {
            $timestamp = $timestamp->max($contentSource->fetched_at);
        }
        $resolvedUri = str_replace('{timestamp}', $timestamp->format($apiConfig->getTimestampFormat()), $listUri);
        $page        = 1;

        // TODO: Implement pagination
        $items = $api->getItems($resolvedUri, [], $page);

        $this->storeWorkflowInputs($contentSource, $apiConfig, $items);

        return $items;
    }

    public function storeWorkflowInputs(ContentSource $contentSource, ConfigurableApiConfig $apiConfig, $items)
    {
        $idField   = $apiConfig->getItemIdField();
        $dateField = $apiConfig->getItemDateField();
        $nameField = $apiConfig->getItemNameField();

        foreach($items as $item) {
            $recordId   = $item[$idField] ?? null;
            $recordDate = $item[$dateField] ?? null;
            $recordName = $item[$nameField] ?? null;

            $contentSource->workflowInputs()->make()->forceFill([
                'team_id' => team()->id,
                'user_id' => user()->id,
                'name'    => $contentSource->name . ': ' . implode(' ', array_filter([$recordId, $recordName, $recordDate])),
                'data'    => $item,
            ])->save();
        }

        $contentSource->fetched_at = now();
        $contentSource->save();
    }
}
