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

    #[Test]
    public function getLeafKey_returns_fallback_object_type_for_flat_structure(): void
    {
        // Given: A flat fragment_selector where ALL children are scalar types (no nested objects)
        // This represents root-level extraction like "Demand" where fields are at the root
        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'name'          => ['type' => 'string'],
                'accident_date' => ['type' => 'string'],
                'description'   => ['type' => 'string'],
            ],
        ];

        // When: Getting leaf key with fallback object type
        $leafKey = $this->service->getLeafKey($fragmentSelector, 'Demand');

        // Then: Should return the fallback object_type as snake_case since structure is flat
        // (the root IS the leaf - no nested hierarchy to traverse)
        $this->assertEquals('demand', $leafKey,
            'For flat structures (all scalar children), should return object_type as snake_case');
    }

    #[Test]
    public function getLeafKey_returns_first_key_for_nested_structure(): void
    {
        // Given: A fragment_selector with nested object structure
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

        // When: Getting leaf key
        $leafKey = $this->service->getLeafKey($fragmentSelector, 'Client');

        // Then: Should return 'client' as the leaf key (the nested object key)
        $this->assertEquals('client', $leafKey);
    }
}
