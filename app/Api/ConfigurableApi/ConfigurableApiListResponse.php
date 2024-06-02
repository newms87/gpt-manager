<?php

namespace App\Api\ConfigurableApi;

use Newms87\Danx\Input\Input;

class ConfigurableApiListResponse extends Input
{
    protected ConfigurableApiConfig $apiConfig;
    protected int                   $page;
    protected int                   $total;

    public function __construct(ConfigurableApiConfig $apiConfig, array $items, int $page, int $total)
    {
        parent::__construct($items);

        $this->apiConfig = $apiConfig;
        $this->page      = $page;
        $this->total     = $total;
    }

    public function getItems(): array
    {
        return $this->all();
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Checks if the response has more items to fetch
     */
    public function hasMore(): bool
    {
        $perPage = $this->apiConfig->getPerPage();

        return $this->total > $perPage * $this->page;
    }

    /**
     * Returns the configured checkpoint value from the items list.
     * This will return the value for the item that is the most recent
     */
    public function getCheckpoint()
    {
        $items = $this->getItems();

        if (empty($items)) {
            return null;
        }

        $checkpointField = $this->apiConfig->getCheckpointField();
        $checkpoint      = null;

        foreach($items as $item) {
            $itemCheckpoint = $item[$checkpointField] ?? null;

            if ($itemCheckpoint && $itemCheckpoint > $checkpoint) {
                $checkpoint = $itemCheckpoint;
            }
        }

        return $checkpoint;

    }
}
