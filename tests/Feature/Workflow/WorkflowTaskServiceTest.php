<?php

namespace Tests\Feature\Workflow;

use App\Services\Workflow\WorkflowTaskService;
use Tests\AuthenticatedTestCase;

class WorkflowTaskServiceTest extends AuthenticatedTestCase
{
    public function test_cleanContent_providesValidJsonWithExtraBackticksPresent(): void
    {
        // Given
        $content = "```json\n{\"key\": \"value\"}\n```";

        // When
        $cleanedContent = WorkflowTaskService::cleanContent($content);

        // Then
        $this->assertEquals('{"key": "value"}', $cleanedContent);
    }

    public function test_cleanContent_providesValidJsonWithoutExtraBackticksPresent(): void
    {
        // Given
        $content = "{\"key\": \"value\"}";

        // When
        $cleanedContent = WorkflowTaskService::cleanContent($content);

        // Then
        $this->assertEquals('{"key": "value"}', $cleanedContent);
    }
}
