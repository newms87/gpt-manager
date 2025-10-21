<?php

namespace App\Services\AgentThread;

/**
 * Standardized artifact filtering configuration (non-DB)
 * Used internally by AgentThreadBuilderService to filter artifact content
 */
class ArtifactFilter
{
    public function __construct(
        public bool $includeText = true,
        public bool $includeFiles = true,
        public bool $includeJson = true,
        public bool $includeMeta = true,
        public array $jsonFragmentSelector = [],
        public array $metaFragmentSelector = []
    ) {
    }
}
