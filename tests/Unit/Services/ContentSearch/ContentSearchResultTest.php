<?php

namespace Tests\Unit\Services\ContentSearch;

use App\Models\Task\Artifact;
use App\Services\ContentSearch\ContentSearchResult;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ContentSearchResultTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_create_returnsNewInstance(): void
    {
        // When
        $result = ContentSearchResult::create();

        // Then
        $this->assertInstanceOf(ContentSearchResult::class, $result);
        $this->assertFalse($result->isFound());
        $this->assertNull($result->getValue());
    }

    public function test_setFound_marksAsFoundWithValue(): void
    {
        // Given
        $result     = ContentSearchResult::create();
        $value      = 'test-file-id-123';
        $method     = 'field_path';
        $confidence = 0.95;

        // When
        $returnedResult = $result->setFound($value, $method, $confidence);

        // Then
        $this->assertSame($result, $returnedResult); // Should return same instance for chaining
        $this->assertTrue($result->isFound());
        $this->assertEquals($value, $result->getValue());
        $this->assertEquals($method, $result->getExtractionMethod());
        $this->assertEquals($confidence, $result->getConfidenceScore());
    }

    public function test_setNotFound_marksAsNotFound(): void
    {
        // Given
        $result = ContentSearchResult::create()->setFound('value', 'method'); // First set as found
        $reason = 'No matching content';

        // When
        $returnedResult = $result->setNotFound($reason);

        // Then
        $this->assertSame($result, $returnedResult);
        $this->assertFalse($result->isFound());
        $this->assertNull($result->getValue());
        $this->assertEquals($reason, $result->getDebugItem('not_found_reason'));
    }

    public function test_setSourceArtifact_setsArtifactAsSource(): void
    {
        // Given
        $artifact = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $result   = ContentSearchResult::create();
        $location = 'json_content.template_stored_file_id';

        // When
        $returnedResult = $result->setSourceArtifact($artifact, $location);

        // Then
        $this->assertSame($result, $returnedResult);
        $this->assertEquals($artifact, $result->getSourceArtifact());
        $this->assertEquals('artifact', $result->getSourceType());
        $this->assertEquals($location, $result->getSourceLocation());
        $this->assertEquals("artifact:{$artifact->id}", $result->getSourceIdentifier());
    }

    public function test_setSourceDirective_setsDirectiveAsSource(): void
    {
        // Given
        $directive                 = new \stdClass();
        $directive->id             = 1;
        $directive->directive_text = 'Test directive text';
        $result                    = ContentSearchResult::create();
        $location                  = 'directive_text';

        // When
        $returnedResult = $result->setSourceDirective($directive, $location);

        // Then
        $this->assertSame($result, $returnedResult);
        $this->assertEquals($directive, $result->getSourceDirective());
        $this->assertEquals('directive', $result->getSourceType());
        $this->assertEquals($location, $result->getSourceLocation());
        $this->assertEquals("directive:{$directive->id}", $result->getSourceIdentifier());
    }

    public function test_setValidated_setsValidationStatus(): void
    {
        // Given
        $result = ContentSearchResult::create();

        // When - set as valid
        $returnedResult = $result->setValidated(true);

        // Then
        $this->assertSame($result, $returnedResult);
        $this->assertTrue($result->isValidated());
        $this->assertNull($result->getValidationError());
        $this->assertFalse($result->hasValidationError());

        // When - set as invalid with error
        $error = 'Value too short';
        $result->setValidated(false, $error);

        // Then
        $this->assertFalse($result->isValidated());
        $this->assertEquals($error, $result->getValidationError());
        $this->assertTrue($result->hasValidationError());
    }

    public function test_incrementAttempts_incrementsCounter(): void
    {
        // Given
        $result = ContentSearchResult::create();
        $this->assertEquals(0, $result->getAttempts());

        // When
        $returnedResult = $result->incrementAttempts();

        // Then
        $this->assertSame($result, $returnedResult);
        $this->assertEquals(1, $result->getAttempts());

        // When - increment again
        $result->incrementAttempts();

        // Then
        $this->assertEquals(2, $result->getAttempts());
    }

    public function test_setMetadata_setsMetadata(): void
    {
        // Given
        $result   = ContentSearchResult::create();
        $metadata = ['key1' => 'value1', 'key2' => 'value2'];

        // When
        $returnedResult = $result->setMetadata($metadata);

        // Then
        $this->assertSame($result, $returnedResult);
        $this->assertEquals($metadata, $result->getMetadata());
        $this->assertEquals('value1', $result->getMetadataItem('key1'));
        $this->assertEquals('value2', $result->getMetadataItem('key2'));
    }

    public function test_addMetadata_addsSingleMetadataItem(): void
    {
        // Given
        $result = ContentSearchResult::create()->setMetadata(['existing' => 'data']);

        // When
        $returnedResult = $result->addMetadata('new_key', 'new_value');

        // Then
        $this->assertSame($result, $returnedResult);
        $this->assertEquals('data', $result->getMetadataItem('existing'));
        $this->assertEquals('new_value', $result->getMetadataItem('new_key'));
    }

    public function test_getMetadataItem_withDefault_returnsDefaultWhenNotSet(): void
    {
        // Given
        $result = ContentSearchResult::create();

        // When
        $value = $result->getMetadataItem('non_existent', 'default_value');

        // Then
        $this->assertEquals('default_value', $value);
    }

    public function test_setAllMatches_setsMatches(): void
    {
        // Given
        $result  = ContentSearchResult::create();
        $matches = ['match1', 'match2', 'match3'];

        // When
        $returnedResult = $result->setAllMatches($matches);

        // Then
        $this->assertSame($result, $returnedResult);
        $this->assertEquals($matches, $result->getAllMatches());
    }

    public function test_addDebugInfo_addsDebugInformation(): void
    {
        // Given
        $result = ContentSearchResult::create();

        // When
        $returnedResult = $result->addDebugInfo('step1', 'processed');

        // Then
        $this->assertSame($result, $returnedResult);
        $this->assertEquals('processed', $result->getDebugItem('step1'));
    }

    public function test_setDebugInfo_mergesDebugInformation(): void
    {
        // Given
        $result       = ContentSearchResult::create()
            ->addDebugInfo('existing', 'value');
        $newDebugInfo = ['new_key' => 'new_value', 'existing' => 'overridden'];

        // When
        $returnedResult = $result->setDebugInfo($newDebugInfo);

        // Then
        $this->assertSame($result, $returnedResult);
        $this->assertEquals('overridden', $result->getDebugItem('existing'));
        $this->assertEquals('new_value', $result->getDebugItem('new_key'));
    }

    public function test_getDebugItem_withDefault_returnsDefaultWhenNotSet(): void
    {
        // Given
        $result = ContentSearchResult::create();

        // When
        $value = $result->getDebugItem('non_existent', 'default_debug');

        // Then
        $this->assertEquals('default_debug', $value);
    }

    public function test_isSuccessful_requiresBothFoundAndValidated(): void
    {
        // Given
        $result = ContentSearchResult::create();

        // When - not found, not validated
        // Then
        $this->assertFalse($result->isSuccessful());

        // When - found but not validated
        $result->setFound('value', 'method');
        // Then
        $this->assertFalse($result->isSuccessful());

        // When - found and validated
        $result->setValidated(true);
        // Then
        $this->assertTrue($result->isSuccessful());

        // When - found but validation failed
        $result->setValidated(false);
        // Then
        $this->assertFalse($result->isSuccessful());
    }

    public function test_getSourceIdentifier_withoutSource_returnsUnknown(): void
    {
        // Given
        $result = ContentSearchResult::create();

        // When
        $identifier = $result->getSourceIdentifier();

        // Then
        $this->assertEquals('unknown', $identifier);
    }

    public function test_getSourceDescription_withArtifact_returnsDescriptiveText(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Test Artifact',
        ]);
        $result   = ContentSearchResult::create()
            ->setSourceArtifact($artifact, 'json_content.field');

        // When
        $description = $result->getSourceDescription();

        // Then
        $this->assertStringContainsString('artifact', $description);
        $this->assertStringContainsString((string)$artifact->id, $description);
        $this->assertStringContainsString('Test Artifact', $description);
        $this->assertStringContainsString('json_content.field', $description);
    }

    public function test_getSourceDescription_withDirective_returnsDescriptiveText(): void
    {
        // Given
        $directive                 = new \stdClass();
        $directive->id             = 1;
        $directive->directive_text = 'Test directive text';
        $result                    = ContentSearchResult::create()
            ->setSourceDirective($directive, 'directive_text');

        // When
        $description = $result->getSourceDescription();

        // Then
        $this->assertStringContainsString('directive', $description);
        $this->assertStringContainsString((string)$directive->id, $description);
        $this->assertStringContainsString('directive_text', $description);
    }

    public function test_toArray_returnsCompleteRepresentation(): void
    {
        // Given
        $artifact = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $result   = ContentSearchResult::create()
            ->setFound('test-value', 'field_path', 0.95)
            ->setSourceArtifact($artifact, 'json_content.field')
            ->setValidated(true)
            ->incrementAttempts()
            ->setMetadata(['test_key' => 'test_value'])
            ->setAllMatches(['match1', 'match2'])
            ->addDebugInfo('debug_key', 'debug_value');

        // When
        $array = $result->toArray();

        // Then
        $this->assertTrue($array['found']);
        $this->assertEquals('test-value', $array['value']);
        $this->assertEquals('field_path', $array['extraction_method']);
        $this->assertEquals('artifact', $array['source_type']);
        $this->assertEquals("artifact:{$artifact->id}", $array['source_identifier']);
        $this->assertEquals('json_content.field', $array['source_location']);
        $this->assertTrue($array['validated']);
        $this->assertNull($array['validation_error']);
        $this->assertEquals(1, $array['attempts']);
        $this->assertEquals(0.95, $array['confidence_score']);
        $this->assertEquals(['test_key' => 'test_value'], $array['metadata']);
        $this->assertEquals(2, $array['all_matches_count']);
        $this->assertEquals(['debug_key' => 'debug_value'], $array['debug_info']);
    }

    public function test_fieldPathFound_createsCorrectResult(): void
    {
        // Given
        $artifact  = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $value     = 'test-file-id';
        $fieldPath = 'json_content.template_stored_file_id';

        // When
        $result = ContentSearchResult::fieldPathFound($value, $artifact, $fieldPath);

        // Then
        $this->assertTrue($result->isFound());
        $this->assertEquals($value, $result->getValue());
        $this->assertEquals('field_path', $result->getExtractionMethod());
        $this->assertEquals(1.0, $result->getConfidenceScore());
        $this->assertEquals($artifact, $result->getSourceArtifact());
        $this->assertEquals($fieldPath, $result->getSourceLocation());
        $this->assertTrue($result->isValidated());
        $this->assertTrue($result->isSuccessful());
    }

    public function test_regexFound_withArtifact_createsCorrectResult(): void
    {
        // Given
        $artifact   = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $value      = 'extracted-value';
        $pattern    = '/pattern/';
        $allMatches = ['extracted-value', 'another-match'];

        // When
        $result = ContentSearchResult::regexFound($value, $artifact, $pattern, $allMatches);

        // Then
        $this->assertTrue($result->isFound());
        $this->assertEquals($value, $result->getValue());
        $this->assertEquals('regex', $result->getExtractionMethod());
        $this->assertEquals(1.0, $result->getConfidenceScore());
        $this->assertEquals($artifact, $result->getSourceArtifact());
        $this->assertEquals('text_content', $result->getSourceLocation());
        $this->assertEquals($allMatches, $result->getAllMatches());
        $this->assertEquals($pattern, $result->getMetadataItem('regex_pattern'));
        $this->assertTrue($result->isValidated());
    }

    public function test_regexFound_withDirective_createsCorrectResult(): void
    {
        // Given
        $directive                 = new \stdClass();
        $directive->id             = 1;
        $directive->directive_text = 'Test directive text';
        $value                     = 'extracted-value';
        $pattern                   = '/pattern/';

        // When
        $result = ContentSearchResult::regexFound($value, $directive, $pattern);

        // Then
        $this->assertTrue($result->isFound());
        $this->assertEquals($directive, $result->getSourceDirective());
        $this->assertEquals('directive_text', $result->getSourceLocation());
        $this->assertTrue($result->isValidated());
    }

    public function test_llmFound_withArtifact_createsCorrectResult(): void
    {
        // Given
        $artifact   = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $value      = 'llm-extracted-value';
        $model      = self::TEST_MODEL;
        $confidence = 0.85;

        // When
        $result = ContentSearchResult::llmFound($value, $artifact, $model, $confidence);

        // Then
        $this->assertTrue($result->isFound());
        $this->assertEquals($value, $result->getValue());
        $this->assertEquals('llm', $result->getExtractionMethod());
        $this->assertEquals($confidence, $result->getConfidenceScore());
        $this->assertEquals($artifact, $result->getSourceArtifact());
        $this->assertEquals('text_content', $result->getSourceLocation());
        $this->assertEquals($model, $result->getMetadataItem('llm_model'));
        $this->assertTrue($result->isValidated());
    }

    public function test_llmFound_withDirective_createsCorrectResult(): void
    {
        // Given
        $directive                 = new \stdClass();
        $directive->id             = 1;
        $directive->directive_text = 'Test directive text';
        $value                     = 'llm-extracted-value';
        $model                     = self::TEST_MODEL;

        // When
        $result = ContentSearchResult::llmFound($value, $directive, $model);

        // Then
        $this->assertTrue($result->isFound());
        $this->assertEquals($directive, $result->getSourceDirective());
        $this->assertEquals('directive_text', $result->getSourceLocation());
        $this->assertEquals(0.8, $result->getConfidenceScore()); // Default confidence
        $this->assertTrue($result->isValidated());
    }

    public function test_notFound_createsCorrectResult(): void
    {
        // Given
        $reason = 'No matching content found';

        // When
        $result = ContentSearchResult::notFound($reason);

        // Then
        $this->assertFalse($result->isFound());
        $this->assertFalse($result->isSuccessful());
        $this->assertNull($result->getValue());
        $this->assertEquals($reason, $result->getDebugItem('not_found_reason'));
    }

    public function test_notFound_withoutReason_createsCorrectResult(): void
    {
        // When
        $result = ContentSearchResult::notFound();

        // Then
        $this->assertFalse($result->isFound());
        $this->assertFalse($result->isSuccessful());
        $this->assertNull($result->getValue());
        $this->assertNull($result->getDebugItem('not_found_reason'));
    }

    public function test_chainedMethodCalls_workCorrectly(): void
    {
        // Given
        $artifact = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // When - chain multiple method calls
        $result = ContentSearchResult::create()
            ->setFound('chained-value', 'chained-method', 0.9)
            ->setSourceArtifact($artifact, 'chained-location')
            ->setValidated(true)
            ->incrementAttempts()
            ->setMetadata(['chained' => 'metadata'])
            ->setAllMatches(['match1', 'match2'])
            ->addDebugInfo('chained', 'debug');

        // Then
        $this->assertTrue($result->isFound());
        $this->assertEquals('chained-value', $result->getValue());
        $this->assertEquals('chained-method', $result->getExtractionMethod());
        $this->assertEquals(0.9, $result->getConfidenceScore());
        $this->assertEquals($artifact, $result->getSourceArtifact());
        $this->assertEquals('chained-location', $result->getSourceLocation());
        $this->assertTrue($result->isValidated());
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(1, $result->getAttempts());
        $this->assertEquals('metadata', $result->getMetadataItem('chained'));
        $this->assertEquals(['match1', 'match2'], $result->getAllMatches());
        $this->assertEquals('debug', $result->getDebugItem('chained'));
    }
}
