<?php

namespace App\Services\ContentSource;

use App\Api\ConfigurableApi\ConfigurableApi;
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
        if ($contentSource->last_checkpoint) {
            $timestamp = $timestamp->max(carbon($contentSource->last_checkpoint));
        }
        $resolvedUri = str_replace('{timestamp}', $timestamp->format($apiConfig->getTimestampFormat()), $listUri);
        $page        = 1;

        $limit = 4;

        // Keep fetching pages and storing responses until we have all pages fetched
        do {
            $apiListResponse = $api->getItems($resolvedUri, [], $page);
            $this->storeWorkflowInputs($contentSource, $apiConfig, $apiListResponse->getItems());

            $contentSource->fetched_at      = now();
            $contentSource->last_checkpoint = $apiListResponse->getCheckpoint();
            $contentSource->save();

            dump('fetched page ' . $page . ' of ' . $contentSource->name . ': ' . $contentSource->last_checkpoint . ' -- ' . $apiListResponse->count() . ' / ' . $apiListResponse->getTotal(), $apiListResponse->hasMore());
            sleep(2000);
            if ($limit-- <= 0) {
                break;
            }
        } while($apiListResponse->hasMore());

        return true;
    }

    public function storeWorkflowInputs(ContentSource $contentSource, ConfigurableApiConfig $apiConfig, $items): void
    {
        $idField   = $apiConfig->getItemIdField();
        $dateField = $apiConfig->getItemDateField();
        $nameField = $apiConfig->getItemNameField();

        foreach($items as $item) {
            $recordId   = $item[$idField] ?? null;
            $recordDate = $item[$dateField] ?? null;
            $recordName = $item[$nameField] ?? null;

            $workflowInput = $contentSource->workflowInputs()->make()->forceFill([
                'team_id' => team()->id,
                'user_id' => user()->id,
                'name'    => $contentSource->name . ': ' . implode(' ', array_filter([$recordId, $recordName, $recordDate])),
                'data'    => $item,
            ]);
            $workflowInput->save();
            $workflowInput->addObjectTag('content-source', 'api');
        }
    }
}
