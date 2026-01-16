<?php

namespace Tests\Feature\Database;

use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Newms87\Danx\Exceptions\ValidationError;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

/**
 * Tests that verify CITEXT extension is working correctly for case-insensitive matching.
 *
 * These tests verify that PostgreSQL CITEXT columns allow case-insensitive queries
 * while preserving the original case when stored and retrieved.
 */
class CaseInsensitiveMatchingTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    #[Test]
    public function team_object_name_is_case_insensitive_lowercase_query(): void
    {
        // Given - Create a TeamObject with mixed case name
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Test Object',
            'type'    => 'TestType',
        ]);

        // When - Query with lowercase
        $found = TeamObject::where('name', 'test object')->first();

        // Then - Should find the record
        $this->assertNotNull($found, 'Should find TeamObject with lowercase query');
        $this->assertEquals($teamObject->id, $found->id);
        $this->assertEquals('Test Object', $found->name, 'Original case should be preserved');
    }

    #[Test]
    public function team_object_name_is_case_insensitive_uppercase_query(): void
    {
        // Given - Create a TeamObject with mixed case name
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Test Object',
            'type'    => 'TestType',
        ]);

        // When - Query with uppercase
        $found = TeamObject::where('name', 'TEST OBJECT')->first();

        // Then - Should find the record
        $this->assertNotNull($found, 'Should find TeamObject with uppercase query');
        $this->assertEquals($teamObject->id, $found->id);
        $this->assertEquals('Test Object', $found->name, 'Original case should be preserved');
    }

    #[Test]
    public function team_object_type_is_case_insensitive(): void
    {
        // Given - Create a TeamObject with mixed case type
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Type Test',
            'type'    => 'Client',
        ]);

        // When - Query with lowercase type
        $found = TeamObject::where('type', 'client')->first();

        // Then - Should find the record
        $this->assertNotNull($found, 'Should find TeamObject with lowercase type query');
        $this->assertEquals($teamObject->id, $found->id);
        $this->assertEquals('Client', $found->type, 'Original case should be preserved');
    }

    #[Test]
    public function team_object_attribute_name_is_case_insensitive(): void
    {
        // Given - Create a TeamObjectAttribute with mixed case name
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $attribute = TeamObjectAttribute::factory()->create([
            'team_object_id' => $teamObject->id,
            'name'           => 'ClientName',
            'text_value'     => 'John Doe',
        ]);

        // When - Query with lowercase name
        $found = TeamObjectAttribute::where('name', 'clientname')->first();

        // Then - Should find the record
        $this->assertNotNull($found, 'Should find TeamObjectAttribute with lowercase name query');
        $this->assertEquals($attribute->id, $found->id);
        $this->assertEquals('ClientName', $found->name, 'Original case should be preserved');
    }

    #[Test]
    public function team_object_attribute_text_value_is_case_insensitive(): void
    {
        // Given - Create a TeamObjectAttribute with mixed case text_value
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $attribute = TeamObjectAttribute::factory()->create([
            'team_object_id' => $teamObject->id,
            'name'           => 'full_name',
            'text_value'     => 'John Smith',
        ]);

        // When - Query with lowercase text_value
        $found = TeamObjectAttribute::where('text_value', 'john smith')->first();

        // Then - Should find the record
        $this->assertNotNull($found, 'Should find TeamObjectAttribute with lowercase text_value query');
        $this->assertEquals($attribute->id, $found->id);
        $this->assertEquals('John Smith', $found->text_value, 'Original case should be preserved');
    }

    #[Test]
    public function user_email_is_case_insensitive(): void
    {
        // Given - Create a User with mixed case email
        $user = User::factory()->create([
            'email' => 'Test@Example.COM',
        ]);

        // When - Query with lowercase email
        $found = User::where('email', 'test@example.com')->first();

        // Then - Should find the record
        $this->assertNotNull($found, 'Should find User with lowercase email query');
        $this->assertEquals($user->id, $found->id);
        $this->assertEquals('Test@Example.COM', $found->email, 'Original case should be preserved');
    }

    #[Test]
    public function unique_constraint_is_case_insensitive_for_team_objects(): void
    {
        // Given - Create a TeamObject with a specific name
        $teamObject = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'name'                 => 'Unique Test',
            'type'                 => 'UniqueType',
            'schema_definition_id' => null,
            'root_object_id'       => null,
            'date'                 => null,
        ]);

        // When - Try to create another with lowercase name (same uniqueness keys)
        // The TeamObject validate() method should throw a ValidationError
        $this->expectException(ValidationError::class);

        $duplicate = TeamObject::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'name'                 => 'unique test', // lowercase version
            'type'                 => 'UniqueType',
            'schema_definition_id' => null,
            'root_object_id'       => null,
            'date'                 => null,
        ]);

        // Trigger validation manually since factory may not call validate()
        $duplicate->validate();
    }

    #[Test]
    public function citext_works_with_ilike_queries(): void
    {
        // Given - Create TeamObjects with different cases
        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'ACME Corporation',
            'type'    => 'Company',
        ]);

        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Acme Industries',
            'type'    => 'Company',
        ]);

        // When - Query with ILIKE (PostgreSQL case-insensitive LIKE)
        $found = TeamObject::where('name', 'ilike', '%acme%')->get();

        // Then - Should find both records regardless of case
        $this->assertEquals(2, $found->count(), 'ILIKE query should find records regardless of case');
    }

    #[Test]
    public function citext_preserves_original_case_on_retrieval(): void
    {
        // Given - Create records with specific casing
        $mixedCase = 'JoHn SmItH';
        $attribute = TeamObjectAttribute::factory()->create([
            'text_value' => $mixedCase,
        ]);

        // When - Retrieve the record
        $found = TeamObjectAttribute::find($attribute->id);

        // Then - Original case should be preserved exactly
        $this->assertEquals($mixedCase, $found->text_value, 'Original mixed case should be preserved');
    }
}
