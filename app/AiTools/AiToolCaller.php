<?php

namespace App\AiTools;

use App\Api\AgentApiContracts\AgentMessageFormatterContract;
use BadFunctionCallException;

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

    public function call(): array
    {
        $tool = $this->getTool();

        if (!$tool) {
            throw new BadFunctionCallException("Tool not found: " . $this->name);
        }

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
}
