<?php

namespace App\AiTools;

use App\AiTools\Traits\HasOutputImagesTrait;
use App\Models\Agent\Message;
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

        $content = $tool->execute($this->arguments);

        if (in_array(HasOutputImagesTrait::class, class_uses($tool) ?: [])) {
            $images = $tool->getOutputImages();
        } else {
            $images = [];
        }

        return $this->getResponseMessages($content, $images);
    }

    /**
     * Get the response messages to send back to the AI completion API
     */
    public function getResponseMessages($content, $images): array
    {
        $messages = [
            [
                'role'    => Message::ROLE_TOOL,
                'content' => is_string($content) ? $content : json_encode($content),
                'data'    => [
                    'tool_call_id' => $this->getId(),
                    'name'         => $this->getName(),
                ],
            ],
        ];

        // If there are images, attach them as user messages
        if ($images) {
            $imageParts = [];
            foreach($images as $image) {
                $imageParts[] = [
                    'type'      => 'image_url',
                    'image_url' => [
                        'url'    => $image,
                        'detail' => 'high',
                    ],
                ];
            }

            $messages[] = [
                'role'    => Message::ROLE_USER,
                'content' => json_encode($imageParts),
            ];
        }

        return $messages;
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
