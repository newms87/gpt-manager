<?php

namespace App\AiTools;

use BadFunctionCallException;

class AiToolCaller
{
    protected string $name;
    protected array  $arguments;

    public function __construct(string $name, array $arguments = [])
    {
        $this->name      = $name;
        $this->arguments = $arguments;
    }

    public function call()
    {
        $tool = $this->getTool();

        if (!$tool) {
            throw new BadFunctionCallException("Tool not found: " . $this->name);
        }

        return $tool->execute($this->arguments);
    }

    protected function getTool(): ?AiToolContract
    {
        $availableTools = config('ai.tools');
        foreach($availableTools as $availableTool) {
            if ($availableTool['name'] === $this->name) {
                return new $availableTool['class'];
            }
        }

        return null;
    }
}
