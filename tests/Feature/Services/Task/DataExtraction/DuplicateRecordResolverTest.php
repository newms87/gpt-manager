<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;
use App\Models\Team\Team;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Services\Task\DataExtraction\DuplicateRecordResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class DuplicateRecordResolverTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private DuplicateRecordResolver $resolver;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->resolver = app(DuplicateRecordResolver::class);
    }

    #[Test]
    public function findCandidates_returns_objects_within_team_scope(): void
    {
        // Given: TeamObjects in current team and another team
        $currentTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
        ]);

        $otherTeam       = Team::factory()->create();
        $otherTeamObject = TeamObject::factory()->create([
            'team_id' => $otherTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
        ]);

        // When: Finding candidates with LIKE pattern search
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%John Smith%']],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Only returns objects from current team
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($currentTeamObject->id, $result->candidates->first()->id);
        $this->assertNotContains($otherTeamObject->id, $result->candidates->pluck('id'));
    }

    #[Test]
    public function findCandidates_filters_by_object_type(): void
    {
        // Given: TeamObjects with different types
        $client = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
        ]);

        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'name'    => 'Car Accident',
        ]);

        // When: Finding candidates for Client type
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%John%']],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Only returns Client objects
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($client->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_by_schema_definition(): void
    {
        // Given: TeamObjects with different schema definitions
        $schema1 = SchemaDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $schema2 = SchemaDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $object1 = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Test Client',
            'schema_definition_id' => $schema1->id,
        ]);

        TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Test Client',
            'schema_definition_id' => $schema2->id,
        ]);

        // When: Finding candidates for schema1
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%Test%']],
            rootObjectId: null,
            schemaDefinitionId: $schema1->id
        );

        // Then: Only returns objects from schema1
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($object1->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_by_parent_object(): void
    {
        // Given: TeamObjects with and without parent
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Case',
        ]);

        $childObject = TeamObject::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Client',
            'name'           => 'Test Client',
            'root_object_id' => $parentObject->id,
        ]);

        TeamObject::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Client',
            'name'           => 'Other Client',
            'root_object_id' => null,
        ]);

        // When: Finding candidates with parent scope
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%Test%']],
            rootObjectId: $parentObject->id,
            schemaDefinitionId: null
        );

        // Then: Only returns objects with matching parent
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($childObject->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_limits_results_to_prevent_performance_issues(): void
    {
        // Given: Many TeamObjects
        TeamObject::factory()->count(100)->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Client Name',
        ]);

        // When: Finding candidates with empty search query (falls back to scope-only)
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Returns limited number of results (limit 50 in executeSearchQuery, then take 20)
        $this->assertLessThanOrEqual(50, $result->candidates->count());
    }

    #[Test]
    public function findCandidates_orders_by_most_recent_first(): void
    {
        // Given: TeamObjects created at different times
        $oldest = TeamObject::factory()->create([
            'team_id'    => $this->user->currentTeam->id,
            'type'       => 'Client',
            'name'       => 'Client',
            'created_at' => now()->subDays(2),
        ]);

        $middle = TeamObject::factory()->create([
            'team_id'    => $this->user->currentTeam->id,
            'type'       => 'Client',
            'name'       => 'Client',
            'created_at' => now()->subDay(),
        ]);

        $newest = TeamObject::factory()->create([
            'team_id'    => $this->user->currentTeam->id,
            'type'       => 'Client',
            'name'       => 'Client',
            'created_at' => now(),
        ]);

        // When: Finding candidates
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%Client%']],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Results are ordered by most recent first
        $ids = $result->candidates->pluck('id')->toArray();
        $this->assertEquals($newest->id, $ids[0]);
        $this->assertEquals($middle->id, $ids[1]);
        $this->assertEquals($oldest->id, $ids[2]);
    }

    #[Test]
    public function findCandidates_applies_like_pattern_to_name_field(): void
    {
        // Given: TeamObjects with similar names
        $lichtenberg = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Dr. Lichtenberg',
        ]);

        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Dr. Smith',
        ]);

        // When: Searching with LIKE pattern
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%Lichtenberg%']],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Only returns matching name
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($lichtenberg->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_applies_like_pattern_to_attributes(): void
    {
        // Given: TeamObjects with attributes
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $candidate->id,
            'name'           => 'title',
            'text_value'     => 'MD',
        ]);

        $otherCandidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Other Client',
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $otherCandidate->id,
            'name'           => 'title',
            'text_value'     => 'PhD',
        ]);

        // When: Searching with LIKE pattern on attribute
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['title' => '%MD%']],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Only returns matching attribute
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($candidate->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_returns_optimal_set_when_1_to_5_results(): void
    {
        // Given: 3 matching TeamObjects
        TeamObject::factory()->count(3)->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
        ]);

        // When: Searching
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%John Smith%']],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Returns all 3 (within 1-5 optimal range)
        $this->assertCount(3, $result->candidates);
    }

    #[Test]
    public function findCandidates_tries_more_restrictive_query_when_too_many_results(): void
    {
        // Given: Many TeamObjects, some with additional attribute
        TeamObject::factory()->count(10)->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Smith',
        ]);

        $specificMatch = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Smith',
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $specificMatch->id,
            'name'           => 'title',
            'text_value'     => 'MD',
        ]);

        // When: Searching with progressive queries (loose then restrictive)
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [
                ['name' => '%Smith%', 'title' => null],          // Loose: all 11 Smiths
                ['name' => '%Smith%', 'title' => '%MD%'],        // Restrictive: only MD
            ],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Returns the more specific match
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($specificMatch->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_falls_back_to_previous_when_too_restrictive(): void
    {
        // Given: TeamObjects that match loose query but not restrictive
        TeamObject::factory()->count(3)->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Smith',
        ]);

        // When: Second query is too restrictive (returns 0)
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [
                ['name' => '%Smith%'],                           // Returns 3
                ['name' => '%Smith%', 'title' => '%Nonexistent%'], // Returns 0
            ],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Falls back to first query results
        $this->assertCount(3, $result->candidates);
    }

    // ========================================================================
    // Exact Match Edge Case Tests (testing isExactMatch behavior via findCandidates)
    // ========================================================================

    #[Test]
    public function findCandidates_treats_empty_extracted_value_and_missing_candidate_field_as_match(): void
    {
        // Given: TeamObject with only name set (no date)
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
            'date'    => null,
        ]);

        // When: Finding candidates with name that matches and date that is empty string
        // Using identity fields to explicitly include 'date' in the comparison
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%Test Client%']],
            rootObjectId: null,
            schemaDefinitionId: null,
            extractedData: ['name' => 'Test Client', 'date' => ''],
            identityFields: ['name', 'date']
        );

        // Then: Returns exact match because both extracted (empty string) and candidate (missing) are effectively empty
        $this->assertTrue($result->hasExactMatch(), 'Expected exact match when both extracted and candidate values are empty');
        $this->assertEquals($candidate->id, $result->exactMatchId);
    }

    #[Test]
    public function findCandidates_returns_no_exact_match_when_extracted_has_value_but_candidate_field_missing(): void
    {
        // Given: TeamObject with only name set (no date)
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
            'date'    => null,
        ]);

        // When: Finding candidates with name that matches but date has a value while candidate has none
        // Using identity fields to explicitly include 'date' in the comparison
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%Test Client%']],
            rootObjectId: null,
            schemaDefinitionId: null,
            extractedData: ['name' => 'Test Client', 'date' => '2024-01-15'],
            identityFields: ['name', 'date']
        );

        // Then: No exact match because extracted has a date value but candidate does not
        // Still returns candidate for LLM resolution
        $this->assertFalse($result->hasExactMatch(), 'Expected no exact match when extracted has value but candidate field is missing');
        $this->assertCount(1, $result->candidates);
    }

    #[Test]
    public function findCandidates_exact_matches_team_object_attributes(): void
    {
        // Given: TeamObject with a TeamObjectAttribute
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'name'    => 'Test Accident',
        ]);

        // Create a TeamObjectAttribute with text_value (uses getValue() method)
        TeamObjectAttribute::factory()->create([
            'team_object_id' => $candidate->id,
            'name'           => 'accident_date',
            'text_value'     => '2024-01-15',
        ]);

        // When: Finding candidates with extracted data containing the attribute field
        $result = $this->resolver->findCandidates(
            objectType: 'Accident',
            searchQueries: [['name' => '%Test Accident%']],
            rootObjectId: null,
            schemaDefinitionId: null,
            extractedData: ['name' => 'Test Accident', 'accident_date' => '2024-01-15'],
            identityFields: ['name', 'accident_date']
        );

        // Then: Returns exact match (this tests that getValue() is used correctly)
        $this->assertTrue($result->hasExactMatch(), 'Expected exact match on TeamObjectAttribute field using getValue()');
        $this->assertEquals($candidate->id, $result->exactMatchId);
    }

    #[Test]
    public function findCandidates_normalizes_date_pattern_from_mmddyyyy_format(): void
    {
        // Given: TeamObject with ISO date stored in database
        $object = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'name'    => 'Test Accident',
            'date'    => '2017-10-23', // ISO format in DB
        ]);

        // When: Searching with MM/DD/YYYY format (how LLM might extract it)
        $result = $this->resolver->findCandidates(
            objectType: 'Accident',
            searchQueries: [['date' => '%10/23/2017%']], // US date format
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Finds the object because date pattern is normalized
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($object->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_handles_iso_date_pattern_unchanged(): void
    {
        // Given: TeamObject with date
        $object = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'name'    => 'Test Accident',
            'date'    => '2017-10-23',
        ]);

        // When: Searching with already ISO format
        $result = $this->resolver->findCandidates(
            objectType: 'Accident',
            searchQueries: [['date' => '%2017-10-23%']],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Still finds the object
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($object->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_normalizes_date_attribute_pattern(): void
    {
        // Given: Schema definition with accident_date as a date field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'accident_date' => [
                        'type'   => 'string',
                        'format' => 'date',
                    ],
                ],
            ],
        ]);

        // Given: TeamObject with accident_date attribute in ISO format
        $object = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Demand',
            'name'                 => 'Test Demand',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $object->id,
            'name'           => 'accident_date',
            'text_value'     => '2017-10-23', // ISO format stored
        ]);

        // When: Searching with MM/DD/YYYY format on attribute (schema identifies it as a date field)
        $result = $this->resolver->findCandidates(
            objectType: 'Demand',
            searchQueries: [['accident_date' => '%10/23/2017%']], // US date format
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds the object because date attribute pattern is normalized
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($object->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_recognizes_date_field_from_schema(): void
    {
        // Given: Schema definition with injury_date as a date field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'injury_date' => [
                        'type'   => 'string',
                        'format' => 'date',
                    ],
                ],
            ],
        ]);

        // Given: TeamObject with injury_date attribute
        $object = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Test Client',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $object->id,
            'name'           => 'injury_date',
            'text_value'     => '2024-01-15',
        ]);

        // When: Searching with non-ISO format (schema identifies it as a date field)
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['injury_date' => '%01/15/2024%']],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds the object because schema defines injury_date as a date field
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($object->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_date_search_uses_iso_format_only(): void
    {
        // Given: Schema definition with accident_date as a date field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'accident_date' => [
                        'type'   => 'string',
                        'format' => 'date',
                    ],
                ],
            ],
        ]);

        // Given: TeamObject with accident_date stored in ISO format (the ONLY supported format)
        $object = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Demand',
            'name'                 => 'Test Demand',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $object->id,
            'name'           => 'accident_date',
            'text_value'     => '2017-10-23', // ISO format (the only format stored)
        ]);

        // When: Searching with raw format pattern (e.g., from LLM extraction)
        // The search pattern is normalized to ISO format before querying
        $result = $this->resolver->findCandidates(
            objectType: 'Demand',
            searchQueries: [['accident_date' => '%10/23/2017%']], // Raw US date format in search
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds the object because search pattern is normalized to ISO format
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($object->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_does_not_normalize_non_date_fields(): void
    {
        // Given: TeamObject with regular attribute
        $object = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $object->id,
            'name'           => 'notes',
            'text_value'     => 'Meeting scheduled for 10/23/2017',
        ]);

        // When: Searching with pattern containing date-like string in non-date field
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['notes' => '%10/23/2017%']],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Finds the object (pattern NOT normalized, matches raw text)
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($object->id, $result->candidates->first()->id);
    }

    // ========================================================================
    // Type-Aware Search Query Tests
    // ========================================================================

    #[Test]
    public function findCandidates_filters_by_native_name_column(): void
    {
        // Given: TeamObjects with different names
        $matchingObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
        ]);

        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Jane Doe',
        ]);

        // When: Searching by name pattern
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%John%']],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Only finds matching name
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($matchingObject->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_boolean_attribute_from_schema(): void
    {
        // Given: Schema with boolean field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'is_active' => ['type' => 'boolean'],
                ],
            ],
        ]);

        $activeObject = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Active Client',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $activeObject->id,
            'name'           => 'is_active',
            'text_value'     => 'true',
        ]);

        $inactiveObject = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Inactive Client',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $inactiveObject->id,
            'name'           => 'is_active',
            'text_value'     => 'false',
        ]);

        // When: Searching for active clients
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['is_active' => 'true']],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Only finds active client
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($activeObject->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_number_attribute_from_schema(): void
    {
        // Given: Schema with number field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Product',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'price' => ['type' => 'number'],
                ],
            ],
        ]);

        $matchingProduct = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Product',
            'name'                 => 'Product A',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $matchingProduct->id,
            'name'           => 'price',
            'text_value'     => '99.99',
        ]);

        $otherProduct = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Product',
            'name'                 => 'Product B',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $otherProduct->id,
            'name'           => 'price',
            'text_value'     => '49.99',
        ]);

        // When: Searching by price pattern
        $result = $this->resolver->findCandidates(
            objectType: 'Product',
            searchQueries: [['price' => '%99.99%']],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds matching product
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($matchingProduct->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_integer_attribute_from_schema(): void
    {
        // Given: Schema with integer field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Product',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'quantity' => ['type' => 'integer'],
                ],
            ],
        ]);

        $matchingProduct = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Product',
            'name'                 => 'Product A',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $matchingProduct->id,
            'name'           => 'quantity',
            'text_value'     => '42',
        ]);

        $otherProduct = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Product',
            'name'                 => 'Product B',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $otherProduct->id,
            'name'           => 'quantity',
            'text_value'     => '100',
        ]);

        // When: Searching by quantity pattern
        $result = $this->resolver->findCandidates(
            objectType: 'Product',
            searchQueries: [['quantity' => '%42%']],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds matching product
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($matchingProduct->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_string_attribute_from_schema(): void
    {
        // Given: Schema with string field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'email' => ['type' => 'string'],
                ],
            ],
        ]);

        $matchingClient = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Test Client',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $matchingClient->id,
            'name'           => 'email',
            'text_value'     => 'john@example.com',
        ]);

        $otherClient = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Other Client',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $otherClient->id,
            'name'           => 'email',
            'text_value'     => 'jane@different.org',
        ]);

        // When: Searching by email pattern
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['email' => '%example.com%']],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds matching client
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($matchingClient->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_normalizes_datetime_attribute_from_schema(): void
    {
        // Given: Schema with date-time field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Event',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'start_time' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
        ]);

        $event = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Event',
            'name'                 => 'Test Event',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $event->id,
            'name'           => 'start_time',
            'text_value'     => '2024-01-15',
        ]);

        // When: Searching with non-ISO format
        $result = $this->resolver->findCandidates(
            objectType: 'Event',
            searchQueries: [['start_time' => '%01/15/2024%']],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds event due to date normalization
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($event->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_boolean_with_different_truthy_values(): void
    {
        // Given: Schema with boolean field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'is_verified' => ['type' => 'boolean'],
                ],
            ],
        ]);

        $verifiedClient = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Verified Client',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $verifiedClient->id,
            'name'           => 'is_verified',
            'text_value'     => 'true',
        ]);

        $unverifiedClient = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Unverified Client',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $unverifiedClient->id,
            'name'           => 'is_verified',
            'text_value'     => 'false',
        ]);

        // When: Searching with '1' (truthy value)
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['is_verified' => '1']],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds verified client because '1' is normalized to 'true'
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($verifiedClient->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_boolean_with_false_value(): void
    {
        // Given: Schema with boolean field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'is_premium' => ['type' => 'boolean'],
                ],
            ],
        ]);

        $premiumClient = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Premium Client',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $premiumClient->id,
            'name'           => 'is_premium',
            'text_value'     => 'true',
        ]);

        $regularClient = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Regular Client',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $regularClient->id,
            'name'           => 'is_premium',
            'text_value'     => 'false',
        ]);

        // When: Searching for non-premium clients
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['is_premium' => 'false']],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds regular client
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($regularClient->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_finds_date_field_in_nested_schema(): void
    {
        // Given: Schema definition with nested structure (accident_date inside demand object)
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Case',
            'name'    => 'Nested Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'demand' => [
                        'type'       => 'object',
                        'properties' => [
                            'accident_date' => [
                                'type'   => 'string',
                                'format' => 'date',
                            ],
                            'client_name' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Given: TeamObject with accident_date attribute in ISO format
        $object = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Case',
            'name'                 => 'Test Case',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $object->id,
            'name'           => 'accident_date',
            'text_value'     => '2017-10-23', // ISO format stored
        ]);

        // When: Searching with MM/DD/YYYY format
        // The resolver should find accident_date in the nested schema and recognize it as a date field
        $result = $this->resolver->findCandidates(
            objectType: 'Case',
            searchQueries: [['accident_date' => '%10/23/2017%']], // US date format
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds the object because the resolver searches nested schema properties
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($object->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_finds_field_in_array_items_nested_schema(): void
    {
        // Given: Schema definition with array items nesting
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Invoice',
            'name'    => 'Array Items Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'line_items' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'due_date' => [
                                    'type'   => 'string',
                                    'format' => 'date',
                                ],
                                'amount' => [
                                    'type' => 'number',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Given: TeamObject with due_date attribute
        $object = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Invoice',
            'name'                 => 'Test Invoice',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $object->id,
            'name'           => 'due_date',
            'text_value'     => '2024-03-15', // ISO format stored
        ]);

        // When: Searching with MM/DD/YYYY format
        // The resolver should find due_date in the array items schema
        $result = $this->resolver->findCandidates(
            objectType: 'Invoice',
            searchQueries: [['due_date' => '%03/15/2024%']], // US date format
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds the object because the resolver searches array items schema
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($object->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_type_detection_falls_back_to_string_without_schema(): void
    {
        // Given: TeamObjects without schema definition
        $matchingObject = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Test Client',
            'schema_definition_id' => null,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $matchingObject->id,
            'name'           => 'custom_field',
            'text_value'     => 'custom value 123',
        ]);

        $otherObject = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Other Client',
            'schema_definition_id' => null,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $otherObject->id,
            'name'           => 'custom_field',
            'text_value'     => 'different value',
        ]);

        // When: Searching without schema (type defaults to string)
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['custom_field' => '%custom value%']],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Finds matching object using string LIKE pattern
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($matchingObject->id, $result->candidates->first()->id);
    }

    // ========================================================================
    // Structured Search Criteria Tests (operator-based queries)
    // ========================================================================

    #[Test]
    public function findCandidates_filters_native_date_with_equals_operator(): void
    {
        // Given: TeamObjects with different dates
        $matchingObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'name'    => 'Accident 1',
            'date'    => '2017-10-23',
        ]);

        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'name'    => 'Accident 2',
            'date'    => '2017-10-24',
        ]);

        // When: Searching with structured date criteria (equals operator)
        $result = $this->resolver->findCandidates(
            objectType: 'Accident',
            searchQueries: [['date' => ['operator' => '=', 'value' => '2017-10-23']]],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Only finds matching date
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($matchingObject->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_native_date_with_between_operator(): void
    {
        // Given: TeamObjects with different dates
        $inRange1 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'name'    => 'Accident 1',
            'date'    => '2017-06-15',
        ]);

        $inRange2 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'name'    => 'Accident 2',
            'date'    => '2017-09-01',
        ]);

        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'name'    => 'Accident 3',
            'date'    => '2018-01-01',
        ]);

        // When: Searching with between operator
        $result = $this->resolver->findCandidates(
            objectType: 'Accident',
            searchQueries: [[
                'date' => [
                    'operator' => 'between',
                    'value'    => '2017-01-01',
                    'value2'   => '2017-12-31',
                ],
            ]],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Finds objects within the date range
        $this->assertCount(2, $result->candidates);
        $candidateIds = $result->candidates->pluck('id')->toArray();
        $this->assertContains($inRange1->id, $candidateIds);
        $this->assertContains($inRange2->id, $candidateIds);
    }

    #[Test]
    public function findCandidates_filters_native_date_with_greater_than_operator(): void
    {
        // Given: TeamObjects with different dates
        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'name'    => 'Old Accident',
            'date'    => '2017-01-01',
        ]);

        $newAccident = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'name'    => 'New Accident',
            'date'    => '2024-06-15',
        ]);

        // When: Searching with greater than operator
        $result = $this->resolver->findCandidates(
            objectType: 'Accident',
            searchQueries: [['date' => ['operator' => '>', 'value' => '2020-01-01']]],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Only finds newer accident
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($newAccident->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_date_attribute_with_equals_operator(): void
    {
        // Given: Schema with date field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'accident_date' => ['type' => 'string', 'format' => 'date'],
                ],
            ],
        ]);

        $matchingObject = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Demand',
            'name'                 => 'Demand 1',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $matchingObject->id,
            'name'           => 'accident_date',
            'text_value'     => '2017-10-23',
        ]);

        $otherObject = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Demand',
            'name'                 => 'Demand 2',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $otherObject->id,
            'name'           => 'accident_date',
            'text_value'     => '2017-10-24',
        ]);

        // When: Searching with structured criteria
        $result = $this->resolver->findCandidates(
            objectType: 'Demand',
            searchQueries: [['accident_date' => ['operator' => '=', 'value' => '2017-10-23']]],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Only finds matching date
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($matchingObject->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_date_attribute_with_between_operator(): void
    {
        // Given: Schema with date field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'accident_date' => ['type' => 'string', 'format' => 'date'],
                ],
            ],
        ]);

        $inRangeObject = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Demand',
            'name'                 => 'Demand 1',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $inRangeObject->id,
            'name'           => 'accident_date',
            'text_value'     => '2017-06-15',
        ]);

        $outOfRangeObject = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Demand',
            'name'                 => 'Demand 2',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $outOfRangeObject->id,
            'name'           => 'accident_date',
            'text_value'     => '2018-06-15',
        ]);

        // When: Searching with between operator
        $result = $this->resolver->findCandidates(
            objectType: 'Demand',
            searchQueries: [[
                'accident_date' => [
                    'operator' => 'between',
                    'value'    => '2017-01-01',
                    'value2'   => '2017-12-31',
                ],
            ]],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Only finds object within range
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($inRangeObject->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_boolean_attribute_with_direct_boolean(): void
    {
        // Given: Schema with boolean field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'is_active' => ['type' => 'boolean'],
                ],
            ],
        ]);

        $activeClient = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Active Client',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $activeClient->id,
            'name'           => 'is_active',
            'text_value'     => 'true',
        ]);

        $inactiveClient = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Inactive Client',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $inactiveClient->id,
            'name'           => 'is_active',
            'text_value'     => 'false',
        ]);

        // When: Searching with direct boolean value (new format)
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['is_active' => true]],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Only finds active client
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($activeClient->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_boolean_attribute_with_false_boolean(): void
    {
        // Given: Schema with boolean field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'is_premium' => ['type' => 'boolean'],
                ],
            ],
        ]);

        $premiumClient = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Premium Client',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $premiumClient->id,
            'name'           => 'is_premium',
            'text_value'     => 'true',
        ]);

        $regularClient = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Client',
            'name'                 => 'Regular Client',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $regularClient->id,
            'name'           => 'is_premium',
            'text_value'     => 'false',
        ]);

        // When: Searching with direct false boolean
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['is_premium' => false]],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Only finds regular client
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($regularClient->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_numeric_attribute_with_equals_operator(): void
    {
        // Given: Schema with number field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Product',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'price' => ['type' => 'number'],
                ],
            ],
        ]);

        $matchingProduct = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Product',
            'name'                 => 'Product A',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $matchingProduct->id,
            'name'           => 'price',
            'text_value'     => '99.99',
        ]);

        $otherProduct = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Product',
            'name'                 => 'Product B',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $otherProduct->id,
            'name'           => 'price',
            'text_value'     => '49.99',
        ]);

        // When: Searching with structured numeric criteria
        $result = $this->resolver->findCandidates(
            objectType: 'Product',
            searchQueries: [['price' => ['operator' => '=', 'value' => 99.99]]],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds matching product
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($matchingProduct->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_numeric_attribute_with_greater_than_or_equal_operator(): void
    {
        // Given: Schema with integer field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Person',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'age' => ['type' => 'integer'],
                ],
            ],
        ]);

        $adult = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Person',
            'name'                 => 'Adult',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $adult->id,
            'name'           => 'age',
            'text_value'     => '25',
        ]);

        $minor = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Person',
            'name'                 => 'Minor',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $minor->id,
            'name'           => 'age',
            'text_value'     => '15',
        ]);

        // When: Searching for age >= 18
        $result = $this->resolver->findCandidates(
            objectType: 'Person',
            searchQueries: [['age' => ['operator' => '>=', 'value' => 18]]],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Only finds adult
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($adult->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_filters_numeric_attribute_with_between_operator(): void
    {
        // Given: Schema with number field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Product',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'price' => ['type' => 'number'],
                ],
            ],
        ]);

        $midRangeProduct = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Product',
            'name'                 => 'Mid-Range Product',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $midRangeProduct->id,
            'name'           => 'price',
            'text_value'     => '75.00',
        ]);

        $cheapProduct = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Product',
            'name'                 => 'Cheap Product',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $cheapProduct->id,
            'name'           => 'price',
            'text_value'     => '10.00',
        ]);

        $expensiveProduct = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Product',
            'name'                 => 'Expensive Product',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $expensiveProduct->id,
            'name'           => 'price',
            'text_value'     => '200.00',
        ]);

        // When: Searching for price between 50 and 100
        $result = $this->resolver->findCandidates(
            objectType: 'Product',
            searchQueries: [[
                'price' => [
                    'operator' => 'between',
                    'value'    => 50,
                    'value2'   => 100,
                ],
            ]],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Only finds mid-range product
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($midRangeProduct->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_handles_mixed_criteria_types(): void
    {
        // Given: Schema with mixed field types
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'client_name'   => ['type' => 'string'],
                    'accident_date' => ['type' => 'string', 'format' => 'date'],
                    'is_active'     => ['type' => 'boolean'],
                ],
            ],
        ]);

        $matchingDemand = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Demand',
            'name'                 => 'John Smith Demand',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $matchingDemand->id,
            'name'           => 'client_name',
            'text_value'     => 'John Smith',
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $matchingDemand->id,
            'name'           => 'accident_date',
            'text_value'     => '2017-10-23',
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $matchingDemand->id,
            'name'           => 'is_active',
            'text_value'     => 'true',
        ]);

        $otherDemand = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Demand',
            'name'                 => 'Jane Doe Demand',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $otherDemand->id,
            'name'           => 'client_name',
            'text_value'     => 'Jane Doe',
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $otherDemand->id,
            'name'           => 'accident_date',
            'text_value'     => '2017-10-23',
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $otherDemand->id,
            'name'           => 'is_active',
            'text_value'     => 'false',
        ]);

        // When: Searching with mixed criteria types (string LIKE, date operator, boolean)
        $result = $this->resolver->findCandidates(
            objectType: 'Demand',
            searchQueries: [[
                'client_name'   => '%John Smith%',                                       // String LIKE
                'accident_date' => ['operator' => '=', 'value' => '2017-10-23'],         // Date operator
                'is_active'     => true,                                                  // Boolean
            ]],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Only finds the matching demand
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($matchingDemand->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_skips_empty_array_criteria(): void
    {
        // Given: TeamObject
        $object = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        // When: Searching with empty array criteria (should be skipped)
        $result = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%Test%', 'some_field' => []]],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Finds object (empty array criteria was skipped)
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($object->id, $result->candidates->first()->id);
    }

    // ========================================================================
    // Exact Name Match Tests
    // ========================================================================

    #[Test]
    public function findCandidates_returns_exact_name_match_before_search_queries(): void
    {
        // Given: TeamObject with exact name
        $existingObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Diagnosis',
            'name'    => 'Cervical neck pain',
        ]);

        // When: Finding candidates with extracted name that exactly matches
        $result = $this->resolver->findCandidates(
            objectType: 'Diagnosis',
            searchQueries: [['name' => '%Cervical%']],
            rootObjectId: null,
            schemaDefinitionId: null,
            extractedData: ['name' => 'Cervical neck pain'],
            identityFields: ['name']
        );

        // Then: Returns exact match immediately with exactMatchId set
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($existingObject->id, $result->candidates->first()->id);
        $this->assertTrue($result->hasExactMatch());
        $this->assertEquals($existingObject->id, $result->exactMatchId);
    }

    #[Test]
    public function findCandidates_exact_name_match_is_case_insensitive(): void
    {
        // Given: TeamObject with mixed case name
        $existingObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Diagnosis',
            'name'    => 'Cervical Neck Pain',
        ]);

        // When: Finding with different case
        $result = $this->resolver->findCandidates(
            objectType: 'Diagnosis',
            searchQueries: [],
            rootObjectId: null,
            schemaDefinitionId: null,
            extractedData: ['name' => 'cervical neck pain'],
            identityFields: ['name']
        );

        // Then: Still finds exact match (case-insensitive)
        $this->assertTrue($result->hasExactMatch());
        $this->assertEquals($existingObject->id, $result->exactMatchId);
    }

    #[Test]
    public function findCandidates_exact_name_match_respects_parent_scope(): void
    {
        // Given: Two TeamObjects with same name but different parents
        $parent1 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Case',
        ]);

        $parent2 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Case',
        ]);

        $child1 = TeamObject::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Diagnosis',
            'name'           => 'Back pain',
            'root_object_id' => $parent1->id,
        ]);

        TeamObject::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Diagnosis',
            'name'           => 'Back pain',
            'root_object_id' => $parent2->id,
        ]);

        // When: Finding with parent scope
        $result = $this->resolver->findCandidates(
            objectType: 'Diagnosis',
            searchQueries: [],
            rootObjectId: $parent1->id,
            schemaDefinitionId: null,
            extractedData: ['name' => 'Back pain'],
            identityFields: ['name']
        );

        // Then: Only finds the child under parent1
        $this->assertTrue($result->hasExactMatch());
        $this->assertEquals($child1->id, $result->exactMatchId);
    }

    #[Test]
    public function findCandidates_proceeds_to_search_when_no_exact_name_match(): void
    {
        // Given: TeamObject with similar but not exact name
        $existingObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Diagnosis',
            'name'    => 'Cervical spine pain',
        ]);

        // When: Finding with different name but matching search query
        $result = $this->resolver->findCandidates(
            objectType: 'Diagnosis',
            searchQueries: [['name' => '%Cervical%']],
            rootObjectId: null,
            schemaDefinitionId: null,
            extractedData: ['name' => 'Cervical neck pain'],
            identityFields: ['name']
        );

        // Then: No exact match, but finds via search query
        $this->assertFalse($result->hasExactMatch());
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($existingObject->id, $result->candidates->first()->id);
    }

    // ========================================================================
    // Keyword Array Search Tests
    // ========================================================================

    #[Test]
    public function findCandidates_filters_name_with_keyword_array(): void
    {
        // Given: TeamObjects with different names
        $matchingObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Diagnosis',
            'name'    => 'Cervical neck pain syndrome',
        ]);

        $partialMatch = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Diagnosis',
            'name'    => 'Cervical disc herniation',
        ]);

        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Diagnosis',
            'name'    => 'Lower back pain',
        ]);

        // When: Searching with keyword array (ALL must be present)
        $result = $this->resolver->findCandidates(
            objectType: 'Diagnosis',
            searchQueries: [['name' => ['cervical', 'neck', 'pain']]],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Only finds object with ALL keywords present
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($matchingObject->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_keyword_array_order_independent(): void
    {
        // Given: TeamObject with words in different order
        $object1 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Diagnosis',
            'name'    => 'Neck pain cervical',
        ]);

        $object2 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Diagnosis',
            'name'    => 'Cervical neck pain',
        ]);

        $object3 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Diagnosis',
            'name'    => 'Pain in cervical neck region',
        ]);

        // When: Searching with keywords in any order
        $result = $this->resolver->findCandidates(
            objectType: 'Diagnosis',
            searchQueries: [['name' => ['cervical', 'neck', 'pain']]],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Finds all objects regardless of word order
        $this->assertCount(3, $result->candidates);
        $candidateIds = $result->candidates->pluck('id')->toArray();
        $this->assertContains($object1->id, $candidateIds);
        $this->assertContains($object2->id, $candidateIds);
        $this->assertContains($object3->id, $candidateIds);
    }

    #[Test]
    public function findCandidates_keyword_array_case_insensitive(): void
    {
        // Given: TeamObject with mixed case
        $object = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Diagnosis',
            'name'    => 'CERVICAL Neck PAIN',
        ]);

        // When: Searching with different case keywords
        $result = $this->resolver->findCandidates(
            objectType: 'Diagnosis',
            searchQueries: [['name' => ['Cervical', 'NECK', 'pain']]],
            rootObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Finds object (case-insensitive)
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($object->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_keyword_array_on_string_attribute(): void
    {
        // Given: Schema with string field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Diagnosis',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'description' => ['type' => 'string'],
                ],
            ],
        ]);

        $matchingObject = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Diagnosis',
            'name'                 => 'Test Diagnosis',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $matchingObject->id,
            'name'           => 'description',
            'text_value'     => 'Chronic cervical spine pain with radiculopathy',
        ]);

        $nonMatchingObject = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Diagnosis',
            'name'                 => 'Other Diagnosis',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $nonMatchingObject->id,
            'name'           => 'description',
            'text_value'     => 'Chronic lumbar spine pain',
        ]);

        // When: Searching with keyword array on attribute
        $result = $this->resolver->findCandidates(
            objectType: 'Diagnosis',
            searchQueries: [['description' => ['cervical', 'spine', 'pain']]],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Only finds object with ALL keywords in attribute
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($matchingObject->id, $result->candidates->first()->id);
    }

    #[Test]
    public function findCandidates_mixed_keyword_array_and_string_criteria(): void
    {
        // Given: Schema with multiple fields
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Diagnosis',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'category' => ['type' => 'string'],
                ],
            ],
        ]);

        $matchingObject = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Diagnosis',
            'name'                 => 'Cervical neck pain',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $matchingObject->id,
            'name'           => 'category',
            'text_value'     => 'Spinal',
        ]);

        $partialMatch = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Diagnosis',
            'name'                 => 'Cervical neck pain',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $partialMatch->id,
            'name'           => 'category',
            'text_value'     => 'Neurological',
        ]);

        // When: Searching with keyword array for name and string LIKE for category
        $result = $this->resolver->findCandidates(
            objectType: 'Diagnosis',
            searchQueries: [[
                'name'     => ['cervical', 'neck'],
                'category' => '%Spinal%',
            ]],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Only finds object matching both criteria
        $this->assertCount(1, $result->candidates);
        $this->assertEquals($matchingObject->id, $result->candidates->first()->id);
    }

    // ========================================================================
    // Exact Match Date Normalization Tests
    // ========================================================================

    #[Test]
    public function findCandidates_exact_match_normalizes_native_date_formats(): void
    {
        // Given: TeamObject with native date column in ISO format (how database stores it)
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'name'    => 'Test Accident',
            'date'    => '2024-12-04', // ISO format in database
        ]);

        // When: Finding candidates with extracted date in MM/DD/YYYY format
        $result = $this->resolver->findCandidates(
            objectType: 'Accident',
            searchQueries: [['name' => '%Test Accident%']],
            rootObjectId: null,
            schemaDefinitionId: null,
            extractedData: ['name' => 'Test Accident', 'date' => '12/04/2024'], // US date format
            identityFields: ['name', 'date']
        );

        // Then: Returns exact match because dates are normalized before comparison
        $this->assertTrue($result->hasExactMatch(), 'Expected exact match when dates represent the same day in different formats');
        $this->assertEquals($candidate->id, $result->exactMatchId);
    }

    #[Test]
    public function findCandidates_exact_match_normalizes_schema_date_attribute_formats(): void
    {
        // Given: Schema definition with accident_date as a date field
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'accident_date' => [
                        'type'   => 'string',
                        'format' => 'date',
                    ],
                ],
            ],
        ]);

        // Given: TeamObject with accident_date attribute in ISO format
        $candidate = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Demand',
            'name'                 => 'Test Demand',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $candidate->id,
            'name'           => 'accident_date',
            'text_value'     => '2024-12-04', // ISO format stored
        ]);

        // When: Finding candidates with extracted date in MM/DD/YYYY format
        $result = $this->resolver->findCandidates(
            objectType: 'Demand',
            searchQueries: [['name' => '%Test Demand%']],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id,
            extractedData: ['name' => 'Test Demand', 'accident_date' => '12/04/2024'], // US date format
            identityFields: ['name', 'accident_date']
        );

        // Then: Returns exact match because date attribute is normalized
        $this->assertTrue($result->hasExactMatch(), 'Expected exact match when schema date attributes represent the same day in different formats');
        $this->assertEquals($candidate->id, $result->exactMatchId);
    }

    #[Test]
    public function findCandidates_exact_match_handles_various_date_formats(): void
    {
        // Given: TeamObject with native date in ISO format
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Event',
            'name'    => 'Test Event',
            'date'    => '2024-01-15', // ISO format
        ]);

        // Test various date format representations that Carbon can parse unambiguously
        // Note: Ambiguous formats like DD/MM/YYYY (e.g., '15/01/2024') may parse differently
        // depending on locale, so we only test unambiguous formats
        $dateFormats = [
            '01/15/2024',        // US format MM/DD/YYYY
            'January 15, 2024',  // Full month name
            'Jan 15, 2024',      // Abbreviated month
            '2024-01-15',        // ISO format (should always match)
            '15 January 2024',   // Day Month Year (unambiguous due to month name)
        ];

        foreach ($dateFormats as $dateFormat) {
            $result = $this->resolver->findCandidates(
                objectType: 'Event',
                searchQueries: [['name' => '%Test Event%']],
                rootObjectId: null,
                schemaDefinitionId: null,
                extractedData: ['name' => 'Test Event', 'date' => $dateFormat],
                identityFields: ['name', 'date']
            );

            $this->assertTrue(
                $result->hasExactMatch(),
                "Expected exact match for date format: {$dateFormat}"
            );
        }
    }

    #[Test]
    public function findCandidates_exact_match_no_match_for_different_dates(): void
    {
        // Given: TeamObject with specific date
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'name'    => 'Test Accident',
            'date'    => '2024-12-04',
        ]);

        // When: Finding candidates with different date (even in same format)
        $result = $this->resolver->findCandidates(
            objectType: 'Accident',
            searchQueries: [['name' => '%Test Accident%']],
            rootObjectId: null,
            schemaDefinitionId: null,
            extractedData: ['name' => 'Test Accident', 'date' => '12/05/2024'], // Different day
            identityFields: ['name', 'date']
        );

        // Then: No exact match because dates are different
        $this->assertFalse($result->hasExactMatch(), 'Expected no exact match for different dates');
        $this->assertCount(1, $result->candidates); // Still returns as candidate for LLM resolution
    }

    #[Test]
    public function findCandidates_exact_match_does_not_normalize_non_date_fields(): void
    {
        // Given: TeamObject with string field that looks like a date but isn't defined as one
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Document',
            'name'    => 'Test Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'reference_code' => ['type' => 'string'], // NOT a date field
                ],
            ],
        ]);

        $candidate = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Document',
            'name'                 => 'Test Doc',
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $candidate->id,
            'name'           => 'reference_code',
            'text_value'     => '12/04/2024', // Looks like a date but stored as-is
        ]);

        // When: Finding candidates with same value
        $result = $this->resolver->findCandidates(
            objectType: 'Document',
            searchQueries: [['name' => '%Test Doc%']],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id,
            extractedData: ['name' => 'Test Doc', 'reference_code' => '12/04/2024'],
            identityFields: ['name', 'reference_code']
        );

        // Then: Exact match because strings match exactly
        $this->assertTrue($result->hasExactMatch());

        // When: Finding with ISO format (which would match if it were normalized as a date)
        $result2 = $this->resolver->findCandidates(
            objectType: 'Document',
            searchQueries: [['name' => '%Test Doc%']],
            rootObjectId: null,
            schemaDefinitionId: $schemaDefinition->id,
            extractedData: ['name' => 'Test Doc', 'reference_code' => '2024-12-04'],
            identityFields: ['name', 'reference_code']
        );

        // Then: No exact match because string comparison doesn't normalize
        $this->assertFalse($result2->hasExactMatch(), 'Non-date string fields should not be date-normalized');
    }
}
