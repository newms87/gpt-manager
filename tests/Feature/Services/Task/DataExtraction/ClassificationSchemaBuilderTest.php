<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Services\Task\DataExtraction\ClassificationSchemaBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassificationSchemaBuilderTest extends TestCase
{
    private ClassificationSchemaBuilder $builder;

    public function setUp(): void
    {
        parent::setUp();
        $this->builder = app(ClassificationSchemaBuilder::class);
    }

    #[Test]
    public function buildSchemaFromGroups_creates_categories_from_extraction_groups(): void
    {
        // Given: Extraction plan with identities and remaining groups at level 0
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'object_type'       => 'Patient',
                            'identity_fields'   => ['name'],
                            'skim_fields'       => ['name', 'dob'],
                            'search_mode'       => 'skim',
                            'fragment_selector' => [],
                        ],
                    ],
                    'remaining'  => [
                        [
                            'name'              => 'Medical Records',
                            'description'       => 'Medical treatment documentation',
                            'fragment_selector' => [],
                        ],
                        [
                            'name'              => 'Billing Documents',
                            'description'       => 'Insurance and billing records',
                            'fragment_selector' => [],
                        ],
                    ],
                ],
            ],
        ];

        // When: Building classification schema from groups at level 0
        $schema = $this->builder->buildSchemaFromGroups($plan, 0);

        // Then: Schema contains categories for each group (identity + remaining)
        $this->assertArrayHasKey('categories', $schema);
        $this->assertArrayHasKey('patient_identification', $schema['categories']);
        $this->assertArrayHasKey('medical_records', $schema['categories']);
        $this->assertArrayHasKey('billing_documents', $schema['categories']);
        $this->assertEquals('Patient Identification', $schema['categories']['patient_identification']['name']);
        $this->assertEquals('Medical Records', $schema['categories']['medical_records']['name']);
        $this->assertEquals('Billing Documents', $schema['categories']['billing_documents']['name']);
    }

    #[Test]
    public function buildSchemaFromGroups_generates_snake_case_category_keys(): void
    {
        // Given: Extraction plan with complex group names
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [],
                    'remaining'  => [
                        [
                            'name'              => 'Medical Diagnoses and Complaints',
                            'description'       => 'Patient complaints and diagnoses',
                            'fragment_selector' => [],
                        ],
                        [
                            'name'              => 'Treatment Plans',
                            'description'       => 'Treatment documentation',
                            'fragment_selector' => [],
                        ],
                    ],
                ],
            ],
        ];

        // When: Building classification schema
        $schema = $this->builder->buildSchemaFromGroups($plan, 0);

        // Then: Category keys are snake_case
        $this->assertArrayHasKey('medical_diagnoses_and_complaints', $schema['categories']);
        $this->assertArrayHasKey('treatment_plans', $schema['categories']);
    }

    #[Test]
    public function buildSchemaFromGroups_enables_multi_label_classification(): void
    {
        // Given: Extraction plan with groups
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [],
                    'remaining'  => [
                        ['name' => 'Group A', 'fragment_selector' => []],
                        ['name' => 'Group B', 'fragment_selector' => []],
                    ],
                ],
            ],
        ];

        // When: Building classification schema
        $schema = $this->builder->buildSchemaFromGroups($plan, 0);

        // Then: Multi-label classification is enabled
        $this->assertTrue($schema['allow_multiple']);
    }

    #[Test]
    public function buildSchemaFromGroups_includes_exclude_category(): void
    {
        // Given: Extraction plan with groups
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [],
                    'remaining'  => [
                        ['name' => 'Group A', 'fragment_selector' => []],
                    ],
                ],
            ],
        ];

        // When: Building classification schema
        $schema = $this->builder->buildSchemaFromGroups($plan, 0);

        // Then: Exclude category is included
        $this->assertTrue($schema['include_exclude']);
    }

    #[Test]
    public function buildSchemaFromGroups_returns_empty_schema_when_no_groups(): void
    {
        // Given: Extraction plan with no groups at level
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [],
                    'remaining'  => [],
                ],
            ],
        ];

        // When: Building classification schema
        $schema = $this->builder->buildSchemaFromGroups($plan, 0);

        // Then: Returns empty schema with default settings
        $this->assertArrayHasKey('categories', $schema);
        $this->assertEmpty($schema['categories']);
        $this->assertTrue($schema['allow_multiple']);
        $this->assertTrue($schema['include_exclude']);
    }

    #[Test]
    public function buildSchemaFromGroups_only_includes_groups_from_specified_level(): void
    {
        // Given: Extraction plan with groups at multiple levels
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [],
                    'remaining'  => [
                        ['name' => 'LevelZeroGroup', 'fragment_selector' => []],
                    ],
                ],
                [
                    'level'      => 1,
                    'identities' => [],
                    'remaining'  => [
                        ['name' => 'LevelOneGroup', 'fragment_selector' => []],
                    ],
                ],
            ],
        ];

        // When: Building classification schema for level 0
        $schema = $this->builder->buildSchemaFromGroups($plan, 0);

        // Then: Only level 0 groups are included
        $this->assertCount(1, $schema['categories']);
        $this->assertArrayHasKey('level_zero_group', $schema['categories']);
        $this->assertArrayNotHasKey('level_one_group', $schema['categories']);
    }

    #[Test]
    public function buildSchemaFromAllGroups_includes_groups_from_all_levels(): void
    {
        // Given: Extraction plan with groups at multiple levels
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [],
                    'remaining'  => [
                        ['name' => 'LevelZeroGroupA', 'fragment_selector' => []],
                        ['name' => 'LevelZeroGroupB', 'fragment_selector' => []],
                    ],
                ],
                [
                    'level'      => 1,
                    'identities' => [],
                    'remaining'  => [
                        ['name' => 'LevelOneGroupC', 'fragment_selector' => []],
                    ],
                ],
            ],
        ];

        // When: Building classification schema from all groups
        $schema = $this->builder->buildSchemaFromAllGroups($plan);

        // Then: Groups from all levels are included
        $this->assertCount(3, $schema['categories']);
        $this->assertArrayHasKey('level_zero_group_a', $schema['categories']);
        $this->assertArrayHasKey('level_zero_group_b', $schema['categories']);
        $this->assertArrayHasKey('level_one_group_c', $schema['categories']);
    }

    #[Test]
    public function buildSchemaFromAllGroups_handles_empty_plan(): void
    {
        // Given: Empty extraction plan
        $plan = [
            'levels' => [],
        ];

        // When: Building classification schema
        $schema = $this->builder->buildSchemaFromAllGroups($plan);

        // Then: Returns empty schema
        $this->assertArrayHasKey('categories', $schema);
        $this->assertEmpty($schema['categories']);
    }

    #[Test]
    public function buildSchemaFromGroups_preserves_group_descriptions(): void
    {
        // Given: Extraction plan with detailed descriptions
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [],
                    'remaining'  => [
                        [
                            'name'              => 'Medical History',
                            'description'       => 'Patient medical history including past diagnoses, surgeries, and chronic conditions',
                            'fragment_selector' => [],
                        ],
                    ],
                ],
            ],
        ];

        // When: Building classification schema
        $schema = $this->builder->buildSchemaFromGroups($plan, 0);

        // Then: Description is preserved in category
        $category = $schema['categories']['medical_history'];
        $this->assertEquals('Medical History', $category['name']);
        $this->assertEquals('Patient medical history including past diagnoses, surgeries, and chronic conditions', $category['description']);
    }

    #[Test]
    public function buildSchemaFromGroups_handles_groups_with_missing_descriptions(): void
    {
        // Given: Extraction plan with group missing description
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [],
                    'remaining'  => [
                        [
                            'name'              => 'Test Group',
                            'fragment_selector' => [],
                            // No description
                        ],
                    ],
                ],
            ],
        ];

        // When: Building classification schema
        $schema = $this->builder->buildSchemaFromGroups($plan, 0);

        // Then: Category has empty description
        $category = $schema['categories']['test_group'];
        $this->assertEquals('Test Group', $category['name']);
        $this->assertEquals('', $category['description']);
    }

    #[Test]
    public function buildBooleanSchema_creates_boolean_properties_for_all_groups(): void
    {
        // Given: Extraction plan with identities and remaining groups across multiple levels
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'object_type'       => 'Patient',
                            'identity_fields'   => ['name'],
                            'skim_fields'       => ['name', 'dob'],
                            'search_mode'       => 'skim',
                            'fragment_selector' => [],
                        ],
                    ],
                    'remaining'  => [
                        [
                            'name'              => 'Medical Records',
                            'description'       => 'Medical treatment documentation',
                            'fragment_selector' => [],
                        ],
                    ],
                ],
                [
                    'level'      => 1,
                    'identities' => [],
                    'remaining'  => [
                        [
                            'name'              => 'Billing Documents',
                            'description'       => 'Insurance and billing records',
                            'fragment_selector' => [],
                        ],
                    ],
                ],
            ],
        ];

        // When: Building boolean schema
        $schema = $this->builder->buildBooleanSchema($plan);

        // Then: Schema has correct JSON schema structure
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);

        // Then: All groups are represented as boolean properties
        $this->assertArrayHasKey('patient_identification', $schema['properties']);
        $this->assertArrayHasKey('medical_records', $schema['properties']);
        $this->assertArrayHasKey('billing_documents', $schema['properties']);

        // Then: Each property is boolean type with description
        $this->assertEquals('boolean', $schema['properties']['patient_identification']['type']);
        $this->assertEquals('Pages relevant for identifying Patient objects', $schema['properties']['patient_identification']['description']);

        $this->assertEquals('boolean', $schema['properties']['medical_records']['type']);
        $this->assertEquals('Medical treatment documentation', $schema['properties']['medical_records']['description']);

        $this->assertEquals('boolean', $schema['properties']['billing_documents']['type']);
        $this->assertEquals('Insurance and billing records', $schema['properties']['billing_documents']['description']);
    }

    #[Test]
    public function buildBooleanSchema_includes_all_properties_in_required_array(): void
    {
        // Given: Extraction plan with multiple groups
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [],
                    'remaining'  => [
                        ['name' => 'Group A', 'description' => 'First group', 'fragment_selector' => []],
                        ['name' => 'Group B', 'description' => 'Second group', 'fragment_selector' => []],
                        ['name' => 'Group C', 'description' => 'Third group', 'fragment_selector' => []],
                    ],
                ],
            ],
        ];

        // When: Building boolean schema
        $schema = $this->builder->buildBooleanSchema($plan);

        // Then: All property keys are in required array
        $this->assertCount(3, $schema['required']);
        $this->assertContains('group_a', $schema['required']);
        $this->assertContains('group_b', $schema['required']);
        $this->assertContains('group_c', $schema['required']);
    }

    #[Test]
    public function buildBooleanSchema_handles_empty_plan(): void
    {
        // Given: Empty extraction plan
        $plan = [
            'levels' => [],
        ];

        // When: Building boolean schema
        $schema = $this->builder->buildBooleanSchema($plan);

        // Then: Returns valid JSON schema structure with empty properties
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertEmpty($schema['properties']);
        $this->assertArrayHasKey('required', $schema);
        $this->assertEmpty($schema['required']);
    }

    #[Test]
    public function buildBooleanSchema_generates_snake_case_property_keys(): void
    {
        // Given: Extraction plan with complex group names
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [],
                    'remaining'  => [
                        [
                            'name'              => 'Medical Diagnoses and Complaints',
                            'description'       => 'Patient complaints and diagnoses',
                            'fragment_selector' => [],
                        ],
                        [
                            'name'              => 'Treatment Plans',
                            'description'       => 'Treatment documentation',
                            'fragment_selector' => [],
                        ],
                    ],
                ],
            ],
        ];

        // When: Building boolean schema
        $schema = $this->builder->buildBooleanSchema($plan);

        // Then: Property keys are snake_case
        $this->assertArrayHasKey('medical_diagnoses_and_complaints', $schema['properties']);
        $this->assertArrayHasKey('treatment_plans', $schema['properties']);
    }

    #[Test]
    public function buildBooleanSchema_handles_groups_with_missing_descriptions(): void
    {
        // Given: Extraction plan with group missing description
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [],
                    'remaining'  => [
                        [
                            'name'              => 'Test Group',
                            'fragment_selector' => [],
                            // No description
                        ],
                    ],
                ],
            ],
        ];

        // When: Building boolean schema
        $schema = $this->builder->buildBooleanSchema($plan);

        // Then: Property has empty description
        $this->assertEquals('boolean', $schema['properties']['test_group']['type']);
        $this->assertEquals('', $schema['properties']['test_group']['description']);
    }
}
