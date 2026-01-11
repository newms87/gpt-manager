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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%John Smith%']],
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Only returns objects from current team
        $this->assertCount(1, $candidates);
        $this->assertEquals($currentTeamObject->id, $candidates->first()->id);
        $this->assertNotContains($otherTeamObject->id, $candidates->pluck('id'));
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%John%']],
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Only returns Client objects
        $this->assertCount(1, $candidates);
        $this->assertEquals($client->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%Test%']],
            parentObjectId: null,
            schemaDefinitionId: $schema1->id
        );

        // Then: Only returns objects from schema1
        $this->assertCount(1, $candidates);
        $this->assertEquals($object1->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%Test%']],
            parentObjectId: $parentObject->id,
            schemaDefinitionId: null
        );

        // Then: Only returns objects with matching parent
        $this->assertCount(1, $candidates);
        $this->assertEquals($childObject->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [],
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Returns limited number of results (limit 50 in executeSearchQuery, then take 20)
        $this->assertLessThanOrEqual(50, $candidates->count());
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%Client%']],
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Results are ordered by most recent first
        $ids = $candidates->pluck('id')->toArray();
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%Lichtenberg%']],
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Only returns matching name
        $this->assertCount(1, $candidates);
        $this->assertEquals($lichtenberg->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['title' => '%MD%']],
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Only returns matching attribute
        $this->assertCount(1, $candidates);
        $this->assertEquals($candidate->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%John Smith%']],
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Returns all 3 (within 1-5 optimal range)
        $this->assertCount(3, $candidates);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [
                ['name' => '%Smith%', 'title' => null],          // Loose: all 11 Smiths
                ['name' => '%Smith%', 'title' => '%MD%'],        // Restrictive: only MD
            ],
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Returns the more specific match
        $this->assertCount(1, $candidates);
        $this->assertEquals($specificMatch->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [
                ['name' => '%Smith%'],                           // Returns 3
                ['name' => '%Smith%', 'title' => '%Nonexistent%'], // Returns 0
            ],
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Falls back to first query results
        $this->assertCount(3, $candidates);
    }

    #[Test]
    public function quickMatchCheck_returns_exact_match_when_name_matches(): void
    {
        // Given: Candidate with exact name match
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
        ]);

        $candidates = collect([$candidate]);

        // When: Quick matching with exact name
        $match = $this->resolver->quickMatchCheck(
            extractedData: ['name' => 'John Smith'],
            candidates: $candidates
        );

        // Then: Returns the exact match
        $this->assertNotNull($match);
        $this->assertEquals($candidate->id, $match->id);
    }

    #[Test]
    public function quickMatchCheck_returns_null_when_no_exact_match(): void
    {
        // Given: Candidate with different name
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
        ]);

        $candidates = collect([$candidate]);

        // When: Quick matching with different name
        $match = $this->resolver->quickMatchCheck(
            extractedData: ['name' => 'Jane Doe'],
            candidates: $candidates
        );

        // Then: Returns null
        $this->assertNull($match);
    }

    #[Test]
    public function quickMatchCheck_matches_date_fields(): void
    {
        // Given: Candidate with date
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
            'date'    => '2024-01-15',
        ]);

        $candidates = collect([$candidate]);

        // When: Quick matching with matching date
        $match = $this->resolver->quickMatchCheck(
            extractedData: ['date' => '2024-01-15'],
            candidates: $candidates
        );

        // Then: Returns the match
        $this->assertNotNull($match);
        $this->assertEquals($candidate->id, $match->id);
    }

    #[Test]
    public function quickMatchCheck_matches_multiple_fields(): void
    {
        // Given: Candidate with multiple matching fields
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
            'date'    => '1990-05-20',
        ]);

        $candidates = collect([$candidate]);

        // When: Quick matching with all fields matching
        $match = $this->resolver->quickMatchCheck(
            extractedData: ['name' => 'John Smith', 'date' => '1990-05-20'],
            candidates: $candidates
        );

        // Then: Returns the match
        $this->assertNotNull($match);
        $this->assertEquals($candidate->id, $match->id);
    }

    #[Test]
    public function quickMatchCheck_matches_team_object_attributes(): void
    {
        // Given: TeamObject with a TeamObjectAttribute
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
        ]);

        // Create a TeamObjectAttribute with text_value (uses getValue() method)
        TeamObjectAttribute::factory()->create([
            'team_object_id' => $candidate->id,
            'name'           => 'accident_date',
            'text_value'     => '2024-01-15',
        ]);

        // Reload to get the attributes relationship
        $candidate->load('attributes');
        $candidates = collect([$candidate]);

        // When: Quick matching with extracted data containing the attribute field
        $match = $this->resolver->quickMatchCheck(
            extractedData: ['accident_date' => '2024-01-15'],
            candidates: $candidates
        );

        // Then: Returns the match (this tests that getValue() is used correctly)
        $this->assertNotNull($match, 'Expected match on TeamObjectAttribute field using getValue()');
        $this->assertEquals($candidate->id, $match->id);
    }

    #[Test]
    public function quickMatchCheck_treats_empty_extracted_value_and_missing_candidate_field_as_match(): void
    {
        // Given: TeamObject with only name set (no date)
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
            'date'    => null,
        ]);

        $candidate->load('attributes');
        $candidates = collect([$candidate]);

        // When: Quick matching with name that matches and date that is empty string
        // Using identity fields to explicitly include 'date' in the comparison
        $match = $this->resolver->quickMatchCheck(
            extractedData: ['name' => 'Test Client', 'date' => ''],
            candidates: $candidates,
            identityFields: ['name', 'date']
        );

        // Then: Returns match because both extracted (empty string) and candidate (missing) are effectively empty
        $this->assertNotNull($match, 'Expected match when both extracted and candidate values are empty');
        $this->assertEquals($candidate->id, $match->id);
    }

    #[Test]
    public function quickMatchCheck_returns_null_when_extracted_has_value_but_candidate_field_missing(): void
    {
        // Given: TeamObject with only name set (no date)
        $candidate = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
            'date'    => null,
        ]);

        $candidate->load('attributes');
        $candidates = collect([$candidate]);

        // When: Quick matching with name that matches but date has a value while candidate has none
        // Using identity fields to explicitly include 'date' in the comparison
        $match = $this->resolver->quickMatchCheck(
            extractedData: ['name' => 'Test Client', 'date' => '2024-01-15'],
            candidates: $candidates,
            identityFields: ['name', 'date']
        );

        // Then: Returns null because extracted has a date value but candidate does not
        $this->assertNull($match, 'Expected no match when extracted has value but candidate field is missing');
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Accident',
            searchQueries: [['date' => '%10/23/2017%']], // US date format
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Finds the object because date pattern is normalized
        $this->assertCount(1, $candidates);
        $this->assertEquals($object->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Accident',
            searchQueries: [['date' => '%2017-10-23%']],
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Still finds the object
        $this->assertCount(1, $candidates);
        $this->assertEquals($object->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Demand',
            searchQueries: [['accident_date' => '%10/23/2017%']], // US date format
            parentObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds the object because date attribute pattern is normalized
        $this->assertCount(1, $candidates);
        $this->assertEquals($object->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['injury_date' => '%01/15/2024%']],
            parentObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds the object because schema defines injury_date as a date field
        $this->assertCount(1, $candidates);
        $this->assertEquals($object->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['notes' => '%10/23/2017%']],
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Finds the object (pattern NOT normalized, matches raw text)
        $this->assertCount(1, $candidates);
        $this->assertEquals($object->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['name' => '%John%']],
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Only finds matching name
        $this->assertCount(1, $candidates);
        $this->assertEquals($matchingObject->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['is_active' => 'true']],
            parentObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Only finds active client
        $this->assertCount(1, $candidates);
        $this->assertEquals($activeObject->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Product',
            searchQueries: [['price' => '%99.99%']],
            parentObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds matching product
        $this->assertCount(1, $candidates);
        $this->assertEquals($matchingProduct->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Product',
            searchQueries: [['quantity' => '%42%']],
            parentObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds matching product
        $this->assertCount(1, $candidates);
        $this->assertEquals($matchingProduct->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['email' => '%example.com%']],
            parentObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds matching client
        $this->assertCount(1, $candidates);
        $this->assertEquals($matchingClient->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Event',
            searchQueries: [['start_time' => '%01/15/2024%']],
            parentObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds event due to date normalization
        $this->assertCount(1, $candidates);
        $this->assertEquals($event->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['is_verified' => '1']],
            parentObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds verified client because '1' is normalized to 'true'
        $this->assertCount(1, $candidates);
        $this->assertEquals($verifiedClient->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['is_premium' => 'false']],
            parentObjectId: null,
            schemaDefinitionId: $schemaDefinition->id
        );

        // Then: Finds regular client
        $this->assertCount(1, $candidates);
        $this->assertEquals($regularClient->id, $candidates->first()->id);
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
        $candidates = $this->resolver->findCandidates(
            objectType: 'Client',
            searchQueries: [['custom_field' => '%custom value%']],
            parentObjectId: null,
            schemaDefinitionId: null
        );

        // Then: Finds matching object using string LIKE pattern
        $this->assertCount(1, $candidates);
        $this->assertEquals($matchingObject->id, $candidates->first()->id);
    }
}
