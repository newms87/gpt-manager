<?php

namespace Tests\Unit\Services\ContentSearch;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Services\ContentSearch\ContentSearchRequest;
use App\Services\ContentSearch\Exceptions\InvalidSearchParametersException;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ContentSearchRequestTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected TaskDefinition $taskDefinition;

    protected Agent $agent;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        $this->agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Test Agent',
        ]);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $this->agent->id,
            'name'     => 'Test Task Definition',
        ]);
    }

    public function test_create_returnsNewInstance(): void
    {
        // When
        $request = ContentSearchRequest::create();

        // Then
        $this->assertInstanceOf(ContentSearchRequest::class, $request);
    }

    public function test_withNaturalLanguageQuery_setsQuery(): void
    {
        // Given
        $query   = 'Find the Google Docs file ID';
        $request = ContentSearchRequest::create();

        // When
        $result = $request->withNaturalLanguageQuery($query);

        // Then
        $this->assertSame($request, $result); // Should return same instance for chaining
        $this->assertEquals($query, $request->getNaturalLanguageQuery());
        $this->assertTrue($request->usesLlmExtraction());
    }

    public function test_withFieldPath_setsFieldPath(): void
    {
        // Given
        $fieldPath = 'template_stored_file_id';
        $request   = ContentSearchRequest::create();

        // When
        $result = $request->withFieldPath($fieldPath);

        // Then
        $this->assertSame($request, $result);
        $this->assertEquals($fieldPath, $request->getFieldPath());
        $this->assertTrue($request->usesFieldPath());
    }

    public function test_withRegexPattern_setsPattern(): void
    {
        // Given
        $pattern = '/[a-zA-Z0-9_-]{25,60}/';
        $request = ContentSearchRequest::create();

        // When
        $result = $request->withRegexPattern($pattern);

        // Then
        $this->assertSame($request, $result);
        $this->assertEquals($pattern, $request->getRegexPattern());
        $this->assertTrue($request->usesRegexPattern());
    }

    public function test_withValidation_setsValidationCallback(): void
    {
        // Given
        $validationCallback = function ($value) {
            return strlen($value) > 10;
        };
        $request            = ContentSearchRequest::create();

        // When
        $result = $request->withValidation($validationCallback, true);

        // Then
        $this->assertSame($request, $result);
        $this->assertEquals($validationCallback, $request->getValidationCallback());
        $this->assertTrue($request->isValidationRequired());
    }

    public function test_withValidation_setsValidationNotRequired(): void
    {
        // Given
        $validationCallback = function ($value) {
            return strlen($value) > 10;
        };
        $request            = ContentSearchRequest::create();

        // When
        $result = $request->withValidation($validationCallback, false);

        // Then
        $this->assertSame($request, $result);
        $this->assertFalse($request->isValidationRequired());
    }

    public function test_withLlmModel_setsModel(): void
    {
        // Given
        $model   = 'gpt-4o-mini';
        $request = ContentSearchRequest::create();

        // When
        $result = $request->withLlmModel($model);

        // Then
        $this->assertSame($request, $result);
        $this->assertEquals($model, $request->getLlmModel());
    }

    public function test_withTaskDefinition_setsTaskDefinition(): void
    {
        // Given
        $request = ContentSearchRequest::create();

        // When
        $result = $request->withTaskDefinition($this->taskDefinition);

        // Then
        $this->assertSame($request, $result);
        $this->assertEquals($this->taskDefinition, $request->getTaskDefinition());
        $this->assertEquals($this->taskDefinition->team_id, $request->getTeamId());
    }

    public function test_searchArtifacts_setsArtifacts(): void
    {
        // Given
        $artifacts = collect([
            Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]),
            Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]),
        ]);
        $request   = ContentSearchRequest::create();

        // When
        $result = $request->searchArtifacts($artifacts);

        // Then
        $this->assertSame($request, $result);
        $this->assertEquals($artifacts, $request->getArtifacts());
    }

    public function test_searchDirectives_setsDirectives(): void
    {
        // Given
        $directives = collect([
            new \stdClass(),  // Mock directive object
        ]);
        $request    = ContentSearchRequest::create();

        // When
        $result = $request->searchDirectives($directives);

        // Then
        $this->assertSame($request, $result);
        $this->assertEquals($directives, $request->getDirectives());
    }

    public function test_withMaxAttempts_setsMaxAttempts(): void
    {
        // Given
        $maxAttempts = 5;
        $request     = ContentSearchRequest::create();

        // When
        $result = $request->withMaxAttempts($maxAttempts);

        // Then
        $this->assertSame($request, $result);
        $this->assertEquals($maxAttempts, $request->getMaxAttempts());
    }

    public function test_withMaxAttempts_withInvalidValue_throwsException(): void
    {
        // Given
        $request = ContentSearchRequest::create();

        // Then
        $this->expectException(InvalidSearchParametersException::class);
        $this->expectExceptionMessage('Must be at least 1');

        // When
        $request->withMaxAttempts(0);
    }

    public function test_withOptions_setsOptions(): void
    {
        // Given
        $options = ['option1' => 'value1', 'option2' => 'value2'];
        $request = ContentSearchRequest::create();

        // When
        $result = $request->withOptions($options);

        // Then
        $this->assertSame($request, $result);
        $this->assertEquals($options, $request->getSearchOptions());
        $this->assertEquals('value1', $request->getOption('option1'));
        $this->assertEquals('value2', $request->getOption('option2'));
    }

    public function test_withOptions_mergesWithExistingOptions(): void
    {
        // Given
        $initialOptions    = ['option1' => 'value1'];
        $additionalOptions = ['option2' => 'value2', 'option1' => 'overridden'];
        $request           = ContentSearchRequest::create()->withOptions($initialOptions);

        // When
        $result = $request->withOptions($additionalOptions);

        // Then
        $this->assertEquals('overridden', $request->getOption('option1')); // Should be overridden
        $this->assertEquals('value2', $request->getOption('option2')); // Should be added
    }

    public function test_withOption_setSingleOption(): void
    {
        // Given
        $request = ContentSearchRequest::create();

        // When
        $result = $request->withOption('test_key', 'test_value');

        // Then
        $this->assertSame($request, $result);
        $this->assertEquals('test_value', $request->getOption('test_key'));
    }

    public function test_getOption_withDefault_returnsDefaultWhenNotSet(): void
    {
        // Given
        $request = ContentSearchRequest::create();

        // When
        $value = $request->getOption('non_existent_key', 'default_value');

        // Then
        $this->assertEquals('default_value', $value);
    }

    public function test_validate_withValidFieldPathRequest_passes(): void
    {
        // Given
        $artifacts = collect([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
        $request   = ContentSearchRequest::create()
            ->withFieldPath('template_stored_file_id')
            ->withTaskDefinition($this->taskDefinition)
            ->searchArtifacts($artifacts);

        // When & Then - should not throw exception
        $request->validate();
        $this->assertTrue(true); // If we get here, validation passed
    }

    public function test_validate_withValidRegexRequest_passes(): void
    {
        // Given
        $artifacts = collect([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
        $request   = ContentSearchRequest::create()
            ->withRegexPattern('/[a-zA-Z0-9_-]+/')
            ->withTaskDefinition($this->taskDefinition)
            ->searchArtifacts($artifacts);

        // When & Then - should not throw exception
        $request->validate();
        $this->assertTrue(true);
    }

    public function test_validate_withValidNaturalLanguageRequest_passes(): void
    {
        // Given
        $artifacts = collect([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
        $request   = ContentSearchRequest::create()
            ->withNaturalLanguageQuery('Find the file ID')
            ->withTaskDefinition($this->taskDefinition)
            ->searchArtifacts($artifacts);

        // When & Then - should not throw exception
        $request->validate();
        $this->assertTrue(true);
    }

    public function test_validate_withNoSearchMethod_throwsException(): void
    {
        // Given
        $artifacts = collect([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
        $request   = ContentSearchRequest::create()
            ->withTaskDefinition($this->taskDefinition)
            ->searchArtifacts($artifacts);

        // Then
        $this->expectException(InvalidSearchParametersException::class);
        $this->expectExceptionMessage('Must specify at least one search method');

        // When
        $request->validate();
    }

    public function test_validate_withNaturalLanguageQueryButNoTaskDefinition_throwsException(): void
    {
        // Given
        $artifacts = collect([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
        $request   = ContentSearchRequest::create()
            ->withNaturalLanguageQuery('Find the file ID')
            ->searchArtifacts($artifacts);
        // Not setting task definition

        // Then
        $this->expectException(InvalidSearchParametersException::class);
        $this->expectExceptionMessage('TaskDefinition is required for natural language queries');

        // When
        $request->validate();
    }

    public function test_validate_withInvalidRegexPattern_throwsException(): void
    {
        // Given
        $artifacts = collect([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
        $request   = ContentSearchRequest::create()
            ->withRegexPattern('/[unclosed') // Invalid regex
            ->withTaskDefinition($this->taskDefinition)
            ->searchArtifacts($artifacts);

        // Then
        $this->expectException(InvalidSearchParametersException::class);
        $this->expectExceptionMessage('Invalid regex pattern');

        // When
        $request->validate();
    }

    public function test_builderPattern_canChainAllMethods(): void
    {
        // Given
        $artifacts          = collect([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
        $validationCallback = function ($value) {
            return !empty($value);
        };

        // When
        $request = ContentSearchRequest::create()
            ->withNaturalLanguageQuery('Find the Google Docs file ID')
            ->withFieldPath('template_stored_file_id')
            ->withRegexPattern('/[a-zA-Z0-9_-]{25,60}/')
            ->withValidation($validationCallback, true)
            ->withLlmModel('gpt-4o-mini')
            ->withTaskDefinition($this->taskDefinition)
            ->searchArtifacts($artifacts)
            ->withMaxAttempts(3)
            ->withOptions(['debug' => true])
            ->withOption('timeout', 30);

        // Then
        $this->assertEquals('Find the Google Docs file ID', $request->getNaturalLanguageQuery());
        $this->assertEquals('template_stored_file_id', $request->getFieldPath());
        $this->assertEquals('/[a-zA-Z0-9_-]{25,60}/', $request->getRegexPattern());
        $this->assertEquals($validationCallback, $request->getValidationCallback());
        $this->assertTrue($request->isValidationRequired());
        $this->assertEquals('gpt-4o-mini', $request->getLlmModel());
        $this->assertEquals($this->taskDefinition, $request->getTaskDefinition());
        $this->assertEquals($artifacts, $request->getArtifacts());
        $this->assertEquals(3, $request->getMaxAttempts());
        $this->assertTrue($request->getOption('debug'));
        $this->assertEquals(30, $request->getOption('timeout'));

        // Should use all search methods
        $this->assertTrue($request->usesLlmExtraction());
        $this->assertTrue($request->usesFieldPath());
        $this->assertTrue($request->usesRegexPattern());
    }

    public function test_getTeamId_withoutTaskDefinition_returnsNull(): void
    {
        // Given
        $request = ContentSearchRequest::create();

        // When
        $teamId = $request->getTeamId();

        // Then
        $this->assertNull($teamId);
    }

    public function test_usesFlags_returnCorrectBooleans(): void
    {
        // Given
        $request = ContentSearchRequest::create();

        // When - no search methods set
        // Then
        $this->assertFalse($request->usesLlmExtraction());
        $this->assertFalse($request->usesFieldPath());
        $this->assertFalse($request->usesRegexPattern());

        // When - set natural language query
        $request->withNaturalLanguageQuery('Find something');
        // Then
        $this->assertTrue($request->usesLlmExtraction());

        // When - set field path
        $request->withFieldPath('field_name');
        // Then
        $this->assertTrue($request->usesFieldPath());

        // When - set regex pattern
        $request->withRegexPattern('/pattern/');
        // Then
        $this->assertTrue($request->usesRegexPattern());
    }

    public function test_defaultValues_areSetCorrectly(): void
    {
        // Given & When
        $request = ContentSearchRequest::create();

        // Then
        $this->assertNull($request->getNaturalLanguageQuery());
        $this->assertNull($request->getFieldPath());
        $this->assertNull($request->getRegexPattern());
        $this->assertNull($request->getValidationCallback());
        $this->assertEquals('gpt-5-nano', $request->getLlmModel()); // Default from config
        $this->assertNull($request->getTaskDefinition());
        $this->assertNull($request->getArtifacts());
        $this->assertNull($request->getDirectives());
        $this->assertFalse($request->isValidationRequired());
        $this->assertEquals(3, $request->getMaxAttempts()); // Default max attempts
        $this->assertEquals([], $request->getSearchOptions());
        $this->assertNull($request->getTeamId());
    }
}
