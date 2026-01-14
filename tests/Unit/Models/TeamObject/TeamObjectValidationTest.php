<?php

namespace Tests\Unit\Models\TeamObject;

use App\Models\Schema\SchemaDefinition;
use App\Models\TeamObject\TeamObject;
use Newms87\Danx\Exceptions\ValidationError;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TeamObjectValidationTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    #[Test]
    public function validate_same_name_and_date_throws_validation_error(): void
    {
        // Given - Create first object
        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Injection',
            'name'    => 'Test Injection',
            'date'    => '2024-12-04',
        ]);

        // When - Try to create duplicate with same name and date
        $duplicate = new TeamObject([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Injection',
            'name'    => 'Test Injection',
            'date'    => '2024-12-04',
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $duplicate->validate();
    }

    #[Test]
    public function validate_same_name_different_date_passes(): void
    {
        // Given - Create first object with Dec 4
        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Injection',
            'name'    => 'Test Injection',
            'date'    => '2024-12-04',
        ]);

        // When - Create second with same name but Dec 12
        $second = new TeamObject([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Injection',
            'name'    => 'Test Injection',
            'date'    => '2024-12-12',
        ]);

        // Then - should pass validation
        $result = $second->validate();
        $this->assertSame($second, $result);
    }

    #[Test]
    public function validate_same_name_null_date_vs_non_null_date_passes(): void
    {
        // Given - Create object with null date
        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Injection',
            'name'    => 'Test Injection',
            'date'    => null,
        ]);

        // When - Create object with same name but with a date
        $withDate = new TeamObject([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Injection',
            'name'    => 'Test Injection',
            'date'    => '2024-12-04',
        ]);

        // Then - should pass validation
        $result = $withDate->validate();
        $this->assertSame($withDate, $result);
    }

    #[Test]
    public function validate_same_name_non_null_date_vs_null_date_passes(): void
    {
        // Given - Create object with a specific date
        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Injection',
            'name'    => 'Test Injection',
            'date'    => '2024-12-04',
        ]);

        // When - Create object with same name but null date
        $withoutDate = new TeamObject([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Injection',
            'name'    => 'Test Injection',
            'date'    => null,
        ]);

        // Then - should pass validation
        $result = $withoutDate->validate();
        $this->assertSame($withoutDate, $result);
    }

    #[Test]
    public function validate_same_name_same_date_different_schema_passes(): void
    {
        // Given - Create schema definition for first object
        $schema1 = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $schema2 = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Injection',
            'name'                 => 'Test Injection',
            'date'                 => '2024-12-04',
            'schema_definition_id' => $schema1->id,
        ]);

        // When - Create object with same name+date but different schema
        $withDifferentSchema = new TeamObject([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Injection',
            'name'                 => 'Test Injection',
            'date'                 => '2024-12-04',
            'schema_definition_id' => $schema2->id,
        ]);

        // Then - should pass validation (different schema = different scope)
        $result = $withDifferentSchema->validate();
        $this->assertSame($withDifferentSchema, $result);
    }

    #[Test]
    public function validate_same_name_same_date_null_schema_vs_non_null_schema_passes(): void
    {
        // Given - Create object with null schema
        TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Injection',
            'name'                 => 'Test Injection',
            'date'                 => '2024-12-04',
            'schema_definition_id' => null,
        ]);

        $schema = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // When - Create object with same name+date but with a schema
        $withSchema = new TeamObject([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Injection',
            'name'                 => 'Test Injection',
            'date'                 => '2024-12-04',
            'schema_definition_id' => $schema->id,
        ]);

        // Then - should pass validation
        $result = $withSchema->validate();
        $this->assertSame($withSchema, $result);
    }

    #[Test]
    public function validate_same_name_same_date_different_root_object_passes(): void
    {
        // Given - Create two different root objects
        $rootObject1 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Document',
            'name'    => 'Root Document 1',
        ]);
        $rootObject2 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Document',
            'name'    => 'Root Document 2',
        ]);

        TeamObject::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Injection',
            'name'           => 'Test Injection',
            'date'           => '2024-12-04',
            'root_object_id' => $rootObject1->id,
        ]);

        // When - Create object with same name+date but different root object
        $withDifferentRoot = new TeamObject([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Injection',
            'name'           => 'Test Injection',
            'date'           => '2024-12-04',
            'root_object_id' => $rootObject2->id,
        ]);

        // Then - should pass validation (different root_object = different scope)
        $result = $withDifferentRoot->validate();
        $this->assertSame($withDifferentRoot, $result);
    }

    #[Test]
    public function validate_same_name_same_date_null_root_vs_non_null_root_passes(): void
    {
        // Given - Create object with null root_object_id
        TeamObject::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Injection',
            'name'           => 'Test Injection',
            'date'           => '2024-12-04',
            'root_object_id' => null,
        ]);

        $rootObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Document',
            'name'    => 'Root Document',
        ]);

        // When - Create object with same name+date but with a root object
        $withRoot = new TeamObject([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Injection',
            'name'           => 'Test Injection',
            'date'           => '2024-12-04',
            'root_object_id' => $rootObject->id,
        ]);

        // Then - should pass validation
        $result = $withRoot->validate();
        $this->assertSame($withRoot, $result);
    }

    #[Test]
    public function validate_allows_updating_existing_record(): void
    {
        // Given - Create an existing object
        $existing = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Injection',
            'name'    => 'Test Injection',
            'date'    => '2024-12-04',
        ]);

        // When - Modify and validate the same record (e.g., change description)
        $existing->description = 'Updated description';

        // Then - should pass validation (not triggering duplicate detection against itself)
        $result = $existing->validate();
        $this->assertSame($existing, $result);
    }

    #[Test]
    public function validate_same_name_same_date_same_schema_throws_validation_error(): void
    {
        // Given - Create schema and first object
        $schema = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Injection',
            'name'                 => 'Test Injection',
            'date'                 => '2024-12-04',
            'schema_definition_id' => $schema->id,
        ]);

        // When - Try to create duplicate with same name, date, and schema
        $duplicate = new TeamObject([
            'team_id'              => $this->user->currentTeam->id,
            'type'                 => 'Injection',
            'name'                 => 'Test Injection',
            'date'                 => '2024-12-04',
            'schema_definition_id' => $schema->id,
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $duplicate->validate();
    }

    #[Test]
    public function validate_same_name_same_date_same_root_throws_validation_error(): void
    {
        // Given - Create root object and first object
        $rootObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Document',
            'name'    => 'Root Document',
        ]);

        TeamObject::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Injection',
            'name'           => 'Test Injection',
            'date'           => '2024-12-04',
            'root_object_id' => $rootObject->id,
        ]);

        // When - Try to create duplicate with same name, date, and root object
        $duplicate = new TeamObject([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Injection',
            'name'           => 'Test Injection',
            'date'           => '2024-12-04',
            'root_object_id' => $rootObject->id,
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $duplicate->validate();
    }

    #[Test]
    public function validate_same_name_different_type_passes(): void
    {
        // Given - Create object with type 'Injection'
        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Injection',
            'name'    => 'Test Object',
            'date'    => '2024-12-04',
        ]);

        // When - Create object with same name but different type
        $differentType = new TeamObject([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Medication',
            'name'    => 'Test Object',
            'date'    => '2024-12-04',
        ]);

        // Then - should pass validation (different type = allowed)
        $result = $differentType->validate();
        $this->assertSame($differentType, $result);
    }
}
