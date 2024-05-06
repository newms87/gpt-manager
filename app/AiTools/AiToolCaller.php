<?php

namespace App\AiTools;

use BadFunctionCallException;

class AiToolCaller
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

    public function call()
    {
        $tool = $this->getTool();

        if (!$tool) {
            throw new BadFunctionCallException("Tool not found: " . $this->name);
        }

        return $tool->execute($this->arguments);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
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
