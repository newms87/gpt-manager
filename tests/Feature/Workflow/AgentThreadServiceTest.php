<?php

namespace Tests\Feature\Workflow;

use App\Services\AgentThread\AgentThreadService;
use Tests\AuthenticatedTestCase;

class AgentThreadServiceTest extends AuthenticatedTestCase
{
    public function test_cleanContent_providesValidJsonWithExtraBackticksPresent(): void
    {
        // Given
        $content = "```json\n{\"key\": \"value\"}\n```";

        // When
        $cleanedContent = AgentThreadService::cleanContent($content);

        // Then
        $this->assertEquals('{"key": "value"}', $cleanedContent);
    }

    public function test_cleanContent_providesValidJsonWithoutExtraBackticksPresent(): void
    {
        // Given
        $content = "{\"key\": \"value\"}";

        // When
        $cleanedContent = AgentThreadService::cleanContent($content);

        // Then
        $this->assertEquals('{"key": "value"}', $cleanedContent);
    }
}
