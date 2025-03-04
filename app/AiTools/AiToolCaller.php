<?php

namespace App\AiTools;

use App\Api\AgentApiContracts\AgentMessageFormatterContract;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use Illuminate\Support\Facades\Log;

abstract class AiToolCaller
{
    protected string $id;
    protected string $name;
    protected array  $arguments;

    public function __construct(string $id, string $name, array $arguments = [])
    {
        $this->id        = $id;
        $this->name      = $name;
        $this->arguments = $arguments;
    }

    abstract public function getFormatter(): AgentMessageFormatterContract;

    public function call(AgentThreadRun $threadRun): array
    {
        $tool = $this->getTool();

        if (!$tool) {
            return $this->handleToolNotFound($threadRun);
        }

        $tool->setThreadRun($threadRun);

        $response = $tool->execute($this->arguments);

        return $this->getFormatter()->toolResponse($this->getId(), $this->getName(), $response);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    protected function getTool(): ?AiToolContract
    {
        $availableTools = config('ai.tools');
        foreach($availableTools as $availableTool) {
            if ($availableTool['name'] === $this->name) {
                if (class_exists($availableTool['class'])) {
                    return new $availableTool['class'];
                }
            }
        }

        return null;
    }

    protected function handleToolNotFound(AgentThreadRun $threadRun): array
    {
        Log::error("$threadRun called an unknown tool: $this->name");

        // Delete the last tool message as this message was invalid
        $threadRun->agentThread->messages()->get()->last()->delete();
        $threadRun->agentThread->messages()->create([
            'content' => "The tool $this->name does not exist. DO NOT USE",
            'role'    => AgentThreadMessage::ROLE_USER,
        ]);

        return [];
    }
}
