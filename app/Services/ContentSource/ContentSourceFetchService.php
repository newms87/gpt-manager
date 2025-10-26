<?php

namespace App\Services\ContentSource;

use App\Api\ConfigurableApi\ConfigurableApi;
use App\Api\ConfigurableApi\ConfigurableApiConfig;
use App\Models\ContentSource\ContentSource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

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
            $timestamp = $timestamp->max($apiConfig->parseTimestamp($contentSource->last_checkpoint));
        }

        $resolvedUri = str_replace('{timestamp}', $apiConfig->formatTimestamp($timestamp), $listUri);
        $page        = 1;

        // Keep fetching pages and storing responses until we have all pages fetched
        do {
            $apiListResponse = $api->getItems($resolvedUri, [], $page);
            $this->storeWorkflowInputs($contentSource, $apiConfig, $apiListResponse->getItems());

            // Set the last checkpoint to the most recent date in the response OR today if the response has checkpoints in the future (which are probably mistakes)
            $contentSource->last_checkpoint = $apiConfig->parseTimestamp($apiListResponse->getCheckpoint())->max();
            $contentSource->fetched_at      = now();
            $contentSource->save();

            if ($page >= 1) {
                break;
            }
            $page++;
            sleep(2);
        } while ($apiListResponse->hasMore());

        return true;
    }

    public function storeWorkflowInputs(ContentSource $contentSource, ConfigurableApiConfig $apiConfig, $items): void
    {
        $idField = $apiConfig->getItemIdField();

        foreach ($items as $item) {
            $recordId = Arr::get($item, $idField);

            // If no record ID is resolved, uniquely identify the content
            if (!$recordId) {
                $recordId = md5(json_encode($item));
            }

            $name = $contentSource->name . ': ' . $recordId;

            $workflowInput = $contentSource->workflowInputs()->where('team_id', team()->id)
                ->where('name', $name)
                ->first();

            if (!$workflowInput) {
                $workflowInput = $contentSource->workflowInputs()->make()->forceFill([
                    'team_id' => $contentSource->team_id,
                    'user_id' => user()->id,
                    'name'    => $name,
                ]);
            }

            $workflowInput->data = $item;
            $workflowInput->save();
            $workflowInput->addObjectTags('content-source', ['api', $contentSource->name]);

            Log::debug("$contentSource " . ($workflowInput->wasRecentlyCreated ? 'created' : 'updated') . " $workflowInput");
        }
    }
}
