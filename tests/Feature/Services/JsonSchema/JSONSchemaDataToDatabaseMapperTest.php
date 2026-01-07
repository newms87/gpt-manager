<?php

namespace Tests\Feature\Services\JsonSchema;

use App\Models\TeamObject\TeamObject;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;
use Tests\AuthenticatedTestCase;

class JSONSchemaDataToDatabaseMapperTest extends AuthenticatedTestCase
{
    public function test_updateTeamObject_with_empty_string_date_does_not_throw_exception(): void
    {
        // Given: A TeamObject with no date set
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'date'    => null,
        ]);

        // When: We call updateTeamObject with an empty string date (as might come from an LLM)
        $mapper = app(JSONSchemaDataToDatabaseMapper::class);
        $result = $mapper->updateTeamObject($teamObject, ['date' => '']);

        // Then: No exception is thrown and the date remains null
        $this->assertInstanceOf(TeamObject::class, $result);
        $this->assertNull($result->date, 'Date should remain null when empty string is passed');
    }

    public function test_updateTeamObject_with_empty_string_date_preserves_existing_date(): void
    {
        // Given: A TeamObject with an existing date
        $existingDate = now()->subDays(10);
        $teamObject   = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'date'    => $existingDate,
        ]);

        // When: We call updateTeamObject with an empty string date
        $mapper = app(JSONSchemaDataToDatabaseMapper::class);
        $result = $mapper->updateTeamObject($teamObject, ['date' => '']);

        // Then: The existing date is preserved (empty string is unset, so fill() doesn't overwrite)
        $this->assertInstanceOf(TeamObject::class, $result);
        $result->refresh();
        $this->assertNotNull($result->date, 'Existing date should be preserved when empty string is passed');
        $this->assertEquals(
            $existingDate->format('Y-m-d'),
            $result->date->format('Y-m-d'),
            'Date should match the original value'
        );
    }

    public function test_updateTeamObject_with_null_date_does_not_throw_exception(): void
    {
        // Given: A TeamObject with an existing date
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'date'    => now(),
        ]);

        // When: We call updateTeamObject with a null date
        $mapper = app(JSONSchemaDataToDatabaseMapper::class);
        $result = $mapper->updateTeamObject($teamObject, ['date' => null]);

        // Then: No exception is thrown and the existing date is preserved
        $this->assertInstanceOf(TeamObject::class, $result);
    }

    public function test_updateTeamObject_with_valid_date_string_sets_date(): void
    {
        // Given: A TeamObject with no date
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'date'    => null,
        ]);

        // When: We call updateTeamObject with a valid date string
        $mapper = app(JSONSchemaDataToDatabaseMapper::class);
        $result = $mapper->updateTeamObject($teamObject, ['date' => '2024-06-15']);

        // Then: The date is set correctly
        $this->assertInstanceOf(TeamObject::class, $result);
        $result->refresh();
        $this->assertNotNull($result->date, 'Date should be set when valid date string is passed');
        $this->assertEquals('2024-06-15', $result->date->format('Y-m-d'));
    }

    public function test_updateTeamObject_with_empty_string_in_array_format_does_not_throw_exception(): void
    {
        // Given: A TeamObject with no date
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'date'    => null,
        ]);

        // When: We call updateTeamObject with date in array format containing empty string value
        // This simulates the attribute format {'value': ''} that might come from LLM responses
        $mapper = app(JSONSchemaDataToDatabaseMapper::class);
        $result = $mapper->updateTeamObject($teamObject, ['date' => ['value' => '']]);

        // Then: No exception is thrown and the date remains null
        $this->assertInstanceOf(TeamObject::class, $result);
        $result->refresh();
        $this->assertNull($result->date, 'Date should remain null when empty string in array format is passed');
    }
}
