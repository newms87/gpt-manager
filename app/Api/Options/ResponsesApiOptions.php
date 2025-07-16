<?php

namespace App\Api\Options;

use InvalidArgumentException;

class ResponsesApiOptions
{
    public const string
        REASONING_EFFORT_LOW = 'low',
        REASONING_EFFORT_MEDIUM = 'medium',
        REASONING_EFFORT_HIGH = 'high';

    public const string
        REASONING_SUMMARY_AUTO = 'auto',
        REASONING_SUMMARY_DETAILED = 'detailed';

    public const string
        SERVICE_TIER_AUTO = 'auto',
        SERVICE_TIER_DEFAULT = 'default',
        SERVICE_TIER_FLEX = 'flex';

    protected ?array $reasoning = null;

    protected string  $serviceTier        = self::SERVICE_TIER_AUTO;
    protected bool    $stream             = false;
    protected ?string $instructions       = null;
    protected ?array  $textFormat         = null;
    protected ?string $previousResponseId = null;
    protected ?float  $temperature        = null;
    protected ?array  $mcpServers         = null;

    public function __construct(array $options = [])
    {
        if (isset($options['reasoning'])) {
            $this->setReasoning($options['reasoning']);
        }

        if (isset($options['service_tier'])) {
            $this->setServiceTier($options['service_tier']);
        }

        if (isset($options['stream'])) {
            $this->setStream($options['stream']);
        }

        if (isset($options['instructions'])) {
            $this->setInstructions($options['instructions']);
        }

        if (isset($options['text'])) {
            $this->setTextFormat($options['text']);
        }

        if (isset($options['previous_response_id'])) {
            $this->setPreviousResponseId($options['previous_response_id']);
        }

        if (isset($options['temperature'])) {
            $this->setTemperature($options['temperature']);
        }

        if (isset($options['mcp_servers'])) {
            $this->setMcpServers($options['mcp_servers']);
        }

    }

    public function setReasoning(?array $reasoning): self
    {
        if (!$reasoning) {
            $this->reasoning = null;

            return $this;
        }

        // Initialize reasoning array if needed
        $this->reasoning = [];

        if (isset($reasoning['effort'])) {
            if (!in_array($reasoning['effort'], [self::REASONING_EFFORT_LOW, self::REASONING_EFFORT_MEDIUM, self::REASONING_EFFORT_HIGH])) {
                throw new InvalidArgumentException("Invalid reasoning effort: {$reasoning['effort']}");
            }
            $this->reasoning['effort'] = $reasoning['effort'];
        }

        if (isset($reasoning['summary'])) {
            if (!in_array($reasoning['summary'], [self::REASONING_SUMMARY_AUTO, self::REASONING_SUMMARY_DETAILED, null])) {
                throw new InvalidArgumentException("Invalid reasoning summary: {$reasoning['summary']}");
            }
            $this->reasoning['summary'] = $reasoning['summary'];
        }

        return $this;
    }

    public function setServiceTier(string $tier): self
    {
        if (!in_array($tier, [self::SERVICE_TIER_AUTO, self::SERVICE_TIER_DEFAULT, self::SERVICE_TIER_FLEX])) {
            throw new InvalidArgumentException("Invalid service tier: $tier");
        }

        $this->serviceTier = $tier;

        return $this;
    }

    public function setResponseJsonSchema(array $jsonSchema): self
    {
        return $this->setTextFormat([
            'format' => [
                'type'   => 'json_schema',
                'name'   => $jsonSchema['name'] ?? 'response',
                'schema' => $jsonSchema['schema'] ?? $jsonSchema,
            ],
        ]);
    }

    public function setStream(bool $stream): self
    {
        $this->stream = $stream;

        return $this;
    }

    public function setInstructions(string $instructions): self
    {
        $this->instructions = $instructions;

        return $this;
    }

    public function addInstructions(string $instructions): self
    {
        if ($this->instructions) {
            $this->instructions .= "\n\n" . $instructions;
        } else {
            $this->instructions = $instructions;
        }

        return $this;
    }

    public function setTextFormat(array $textFormat): self
    {
        $this->textFormat = $textFormat;

        return $this;
    }

    public function setPreviousResponseId(?string $previousResponseId): self
    {
        $this->previousResponseId = $previousResponseId;

        return $this;
    }

    public function setTemperature(?float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function setMcpServers(?array $mcpServers): self
    {
        $this->mcpServers = $mcpServers;

        return $this;
    }


    public function getReasoning(): ?array
    {
        return $this->reasoning;
    }

    public function getServiceTier(): string
    {
        return $this->serviceTier;
    }

    public function isStreaming(): bool
    {
        return $this->stream;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function getTextFormat(): ?array
    {
        return $this->textFormat;
    }

    public function getPreviousResponseId(): ?string
    {
        return $this->previousResponseId;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function getMcpServers(): ?array
    {
        return $this->mcpServers;
    }


    /**
     * Convert to array for API request
     */
    public function toArray(string $model = null): array
    {
        $options = [
            'service_tier' => $this->serviceTier,
            'stream'       => $this->stream,
        ];

        // Add reasoning only if specified
        if ($this->reasoning) {
            $options['reasoning'] = $this->reasoning;
        }

        if ($this->instructions) {
            $options['instructions'] = $this->instructions;
        }

        if ($this->textFormat) {
            $options['text'] = $this->textFormat;
        }

        if ($this->previousResponseId) {
            $options['previous_response_id'] = $this->previousResponseId;
        }

        if ($this->temperature !== null) {
            $modelConfig = config("ai.models.$model");
            if (!empty($modelConfig['features']['temperature'])) {
                $options['temperature'] = $this->temperature;
            }
        }

        if ($this->mcpServers) {
            $options['tools'] = $this->mcpServers;
        }

        return $options;
    }

    /**
     * Create from array (e.g., from database JSON)
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Set default text format for simple text responses
     */
    public function setDefaultTextFormat(): self
    {
        $this->textFormat = [
            'format' => [
                'type' => 'text',
            ],
        ];

        return $this;
    }
}
