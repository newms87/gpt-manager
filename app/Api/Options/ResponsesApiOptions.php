<?php

namespace App\Api\Options;

use InvalidArgumentException;

class ResponsesApiOptions
{
    public const REASONING_EFFORT_LOW = 'low';
    public const REASONING_EFFORT_MEDIUM = 'medium';
    public const REASONING_EFFORT_HIGH = 'high';
    
    public const REASONING_SUMMARY_AUTO = 'auto';
    public const REASONING_SUMMARY_DETAILED = 'detailed';
    
    public const SERVICE_TIER_AUTO = 'auto';
    public const SERVICE_TIER_DEFAULT = 'default';
    public const SERVICE_TIER_FLEX = 'flex';
    
    protected array $reasoning = [
        'effort' => self::REASONING_EFFORT_MEDIUM,
        'summary' => self::REASONING_SUMMARY_AUTO,
    ];
    
    protected string $serviceTier = self::SERVICE_TIER_AUTO;
    protected bool $stream = false;
    protected ?string $instructions = null;
    protected ?string $text = null;
    protected ?array $input = null;
    protected ?string $previousResponseId = null;
    
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
            $this->setText($options['text']);
        }
        
        if (isset($options['input'])) {
            $this->setInput($options['input']);
        }
        
        if (isset($options['previous_response_id'])) {
            $this->setPreviousResponseId($options['previous_response_id']);
        }
    }
    
    public function setReasoning(array $reasoning): self
    {
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
    
    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }
    
    public function setInput(array $input): self
    {
        $this->input = $input;
        return $this;
    }
    
    public function setPreviousResponseId(?string $previousResponseId): self
    {
        $this->previousResponseId = $previousResponseId;
        return $this;
    }
    
    public function getReasoning(): array
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
    
    public function getText(): ?string
    {
        return $this->text;
    }
    
    public function getInput(): ?array
    {
        return $this->input;
    }
    
    public function getPreviousResponseId(): ?string
    {
        return $this->previousResponseId;
    }
    
    /**
     * Convert to array for API request
     */
    public function toArray(): array
    {
        $options = [
            'reasoning' => $this->reasoning,
            'service_tier' => $this->serviceTier,
            'stream' => $this->stream,
        ];
        
        if ($this->instructions !== null) {
            $options['instructions'] = $this->instructions;
        }
        
        if ($this->text !== null) {
            $options['text'] = $this->text;
        }
        
        if ($this->input !== null) {
            $options['input'] = $this->input;
        }
        
        if ($this->previousResponseId !== null) {
            $options['previous_response_id'] = $this->previousResponseId;
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
}