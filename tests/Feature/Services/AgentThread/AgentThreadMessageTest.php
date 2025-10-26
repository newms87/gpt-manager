<?php

namespace Tests\Feature\Services\AgentThread;

use App\Models\Agent\AgentThreadMessage;
use Tests\AuthenticatedTestCase;

class AgentThreadMessageTest extends AuthenticatedTestCase
{
    public function test_getCleanContent_providesValidJsonWithExtraBackticksPresent(): void
    {
        // Given
        $content = "```json\n{\"key\": \"value\"}\n```";
        $message = new AgentThreadMessage(['content' => $content]);

        // When
        $cleanedContent = $message->getCleanContent();

        // Then
        $this->assertEquals('{"key": "value"}', $cleanedContent);
    }

    public function test_getCleanContent_providesValidJsonWithoutExtraBackticksPresent(): void
    {
        // Given
        $content = '{"key": "value"}';
        $message = new AgentThreadMessage(['content' => $content]);

        // When
        $cleanedContent = $message->getCleanContent();

        // Then
        $this->assertEquals('{"key": "value"}', $cleanedContent);
    }
}
