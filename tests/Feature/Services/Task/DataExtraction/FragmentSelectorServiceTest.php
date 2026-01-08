<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Services\Task\DataExtraction\FragmentSelectorService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FragmentSelectorServiceTest extends TestCase
{
    private FragmentSelectorService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new FragmentSelectorService();
    }

    #[Test]
    public function getNestingKeys_stops_at_scalar_fields(): void
    {
        // Given: A fragment_selector with one object level then scalar fields
        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'client' => [
                    'type'     => 'object',
                    'children' => [
                        'name'          => ['type' => 'string'],
                        'date_of_birth' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        // When: Getting nesting keys
        $keys = $this->service->getNestingKeys($fragmentSelector);

        // Then: Should only include 'client', not the scalar fields
        $this->assertEquals(['client'], $keys);
    }

    #[Test]
    public function getParentType_returns_null_for_single_level_object(): void
    {
        // Given: A fragment_selector with one object level (no parent)
        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'client' => [
                    'type'     => 'object',
                    'children' => [
                        'name'          => ['type' => 'string'],
                        'date_of_birth' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        // When: Getting parent type
        $parentType = $this->service->getParentType($fragmentSelector);

        // Then: Should be null (no parent for root objects)
        $this->assertNull($parentType);
    }

    #[Test]
    public function getNestingKeys_handles_multi_level_hierarchy(): void
    {
        // Given: A fragment_selector with multiple nested levels
        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'provider' => [
                    'type'     => 'array',
                    'children' => [
                        'care_summary' => [
                            'type'     => 'object',
                            'children' => [
                                'professional' => [
                                    'type'     => 'object',
                                    'children' => [
                                        'name'  => ['type' => 'string'],
                                        'title' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When: Getting nesting keys
        $keys = $this->service->getNestingKeys($fragmentSelector);

        // Then: Should include all object/array keys but not scalar field names
        $this->assertEquals(['provider', 'care_summary', 'professional'], $keys);
    }

    #[Test]
    public function getParentType_returns_second_to_last_for_multi_level(): void
    {
        // Given: A fragment_selector with multiple nested levels
        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'provider' => [
                    'type'     => 'array',
                    'children' => [
                        'care_summary' => [
                            'type'     => 'object',
                            'children' => [
                                'professional' => [
                                    'type'     => 'object',
                                    'children' => [
                                        'name' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When: Getting parent type
        $parentType = $this->service->getParentType($fragmentSelector);

        // Then: Should be 'Care Summary' (second-to-last in path)
        $this->assertEquals('Care Summary', $parentType);
    }
}
