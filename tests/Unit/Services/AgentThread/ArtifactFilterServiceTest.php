<?php

namespace Tests\Unit\Services\AgentThread;

use App\Models\Task\Artifact;
use App\Services\AgentThread\ArtifactFilterService;
use Illuminate\Support\Facades\Storage;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ArtifactFilterServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        Storage::fake('local');
    }

    #[Test]
    public function filter_withIncludeFilesFalse_stillIncludesTextTranscodes(): void
    {
        // Given - artifact with stored file that has text transcode AND json content
        // (need json content included to force array return instead of string)
        $artifact               = $this->createArtifactWithTextTranscode('This is the transcoded text content');
        $artifact->json_content = ['key' => 'value'];
        $artifact->save();
        $artifact = $artifact->fresh(['storedFiles']);

        // When - includeFiles=false but includeTextTranscodes=true (default), includeJson=true
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeFiles(false)
            ->includeJson(true)
            ->includeMeta(false)
            ->includeText(false)
            ->filter();

        // Then - text_transcodes should be included, files should NOT be included
        $this->assertIsArray($result);
        $this->assertArrayHasKey('text_transcodes', $result);
        $this->assertArrayNotHasKey('files', $result);
        $this->assertStringContainsString('transcoded text content', $result['text_transcodes']);
    }

    #[Test]
    public function filter_withIncludeTextTranscodesFalse_excludesTextTranscodes(): void
    {
        // Given - artifact with stored file that has text transcode
        $artifact = $this->createArtifactWithTextTranscode('This is the transcoded text content');

        // When - includeTextTranscodes=false
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeFiles(true)
            ->includeJson(false)
            ->includeMeta(false)
            ->includeText(false)
            ->includeTextTranscodes(false)
            ->filter();

        // Then - text_transcodes should NOT be in output
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('text_transcodes', $result);
        $this->assertArrayHasKey('files', $result);
    }

    #[Test]
    public function filter_withIncludeFilesTrue_includesBothFilesAndTextTranscodes(): void
    {
        // Given - artifact with stored file that has text transcode
        $artifact = $this->createArtifactWithTextTranscode('Transcoded content here');

        // When - includeFiles=true and includeTextTranscodes=true
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeFiles(true)
            ->includeJson(false)
            ->includeMeta(false)
            ->includeText(false)
            ->includeTextTranscodes(true)
            ->filter();

        // Then - both files and text_transcodes should be included
        $this->assertIsArray($result);
        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('text_transcodes', $result);
    }

    #[Test]
    public function filter_withIncludeMetaFalse_excludesMeta(): void
    {
        // Given - artifact with meta and json content (need json to make it return array)
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Some text',
            'json_content' => ['key' => 'value'],
            'meta'         => ['classification' => ['provider_identification' => true]],
        ]);

        // When - includeMeta=false but includeJson=true (so we get array not string)
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeText(true)
            ->includeFiles(false)
            ->includeJson(true)
            ->includeMeta(false)
            ->filter();

        // Then - meta should NOT be in output
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('meta', $result);
        $this->assertArrayHasKey('json_content', $result);
    }

    #[Test]
    public function filter_withIncludeMetaTrue_includesMeta(): void
    {
        // Given - artifact with meta content
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Some text',
            'meta'         => ['classification' => ['provider_identification' => true]],
        ]);

        // When - includeMeta=true
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeText(true)
            ->includeFiles(false)
            ->includeJson(false)
            ->includeMeta(true)
            ->filter();

        // Then - meta should be included
        $this->assertIsArray($result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertEquals(['classification' => ['provider_identification' => true]], $result['meta']);
    }

    #[Test]
    public function filter_withIncludeJsonFalse_excludesJsonContent(): void
    {
        // Given - artifact with json and meta content (need meta to get array not string)
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Some text',
            'json_content' => ['key' => 'value', 'nested' => ['data' => 'test']],
            'meta'         => ['category' => 'test'],
        ]);

        // When - includeJson=false but includeMeta=true (so we get array not string)
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeText(true)
            ->includeFiles(false)
            ->includeJson(false)
            ->includeMeta(true)
            ->filter();

        // Then - json_content should NOT be in output
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('json_content', $result);
        $this->assertArrayHasKey('meta', $result);
    }

    #[Test]
    public function filter_withIncludeJsonTrue_includesJsonContent(): void
    {
        // Given - artifact with json content
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Some text',
            'json_content' => ['key' => 'value', 'nested' => ['data' => 'test']],
        ]);

        // When - includeJson=true
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeText(true)
            ->includeFiles(false)
            ->includeJson(true)
            ->includeMeta(false)
            ->filter();

        // Then - json_content should be included
        $this->assertIsArray($result);
        $this->assertArrayHasKey('json_content', $result);
        $this->assertEquals(['key' => 'value', 'nested' => ['data' => 'test']], $result['json_content']);
    }

    #[Test]
    public function filter_withIncludeTextTrue_includesTextContent(): void
    {
        // Given - artifact with only text content
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'This is the text content',
            'json_content' => ['key' => 'value'],
        ]);

        // When - includeText=true with json also
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeText(true)
            ->includeFiles(false)
            ->includeJson(true)
            ->includeMeta(false)
            ->filter();

        // Then - text_content should be included
        $this->assertIsArray($result);
        $this->assertArrayHasKey('text_content', $result);
        $this->assertEquals('This is the text content', $result['text_content']);
    }

    #[Test]
    public function filter_withIncludeTextFalse_excludesTextContent(): void
    {
        // Given - artifact with text and json content
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'This is the text content',
            'json_content' => ['key' => 'value'],
        ]);

        // When - includeText=false
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeText(false)
            ->includeFiles(false)
            ->includeJson(true)
            ->includeMeta(false)
            ->filter();

        // Then - text_content should NOT be in output
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('text_content', $result);
    }

    #[Test]
    public function filter_withOnlyTextContent_returnsStringDirectly(): void
    {
        // Given - artifact with only text content (no files, no json, no meta)
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'This is just plain text',
            'json_content' => null,
            'meta'         => null,
        ]);

        // When - filter with only text included
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeText(true)
            ->includeFiles(false)
            ->includeJson(false)
            ->includeMeta(false)
            ->filter();

        // Then - should return string directly, not array
        $this->assertIsString($result);
        $this->assertEquals('This is just plain text', $result);
    }

    #[Test]
    public function filter_withEmptyArtifact_returnsNull(): void
    {
        // Given - artifact with no content
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => null,
            'json_content' => null,
            'meta'         => null,
        ]);

        // When - filter with all options enabled
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeText(true)
            ->includeFiles(true)
            ->includeJson(true)
            ->includeMeta(true)
            ->filter();

        // Then - should return null
        $this->assertNull($result);
    }

    #[Test]
    public function willBeEmpty_withNoContent_returnsTrue(): void
    {
        // Given - artifact with no content
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => null,
            'json_content' => null,
            'meta'         => null,
        ]);

        // When
        $isEmpty = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->willBeEmpty();

        // Then
        $this->assertTrue($isEmpty);
    }

    #[Test]
    public function willBeEmpty_withTextContent_returnsFalse(): void
    {
        // Given - artifact with text content
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Has content',
        ]);

        // When
        $isEmpty = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeText(true)
            ->willBeEmpty();

        // Then
        $this->assertFalse($isEmpty);
    }

    #[Test]
    public function willBeEmpty_withNoArtifact_returnsTrue(): void
    {
        // Given - no artifact set
        // When
        $isEmpty = app(ArtifactFilterService::class)->willBeEmpty();

        // Then
        $this->assertTrue($isEmpty);
    }

    #[Test]
    public function filter_forExtractionUseCase_includesTextTranscodesWithJsonContent(): void
    {
        // Given - artifact with stored file that has text transcode AND json content
        // This simulates the extraction use case where we want text transcodes
        // Note: Currently, willBeEmpty() doesn't consider text transcodes,
        // and isTextOnly() returns true if only text is included (returning string),
        // so we need JSON content to get array with text_transcodes
        $artifact               = $this->createArtifactWithTextTranscode('Provider: ABC Insurance\nPolicy: 12345');
        $artifact->json_content = ['extraction_placeholder' => true];
        $artifact->save();
        $artifact = $artifact->fresh(['storedFiles']);

        // When - extraction filter: include json (for array return) and text_transcodes
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeFiles(false)
            ->includeJson(true)
            ->includeMeta(false)
            ->includeText(false)
            ->includeTextTranscodes(true)
            ->filter();

        // Then - should return array with json_content and text_transcodes
        $this->assertIsArray($result);
        $this->assertArrayHasKey('text_transcodes', $result);
        $this->assertArrayHasKey('json_content', $result);
        $this->assertArrayNotHasKey('files', $result);
        $this->assertArrayNotHasKey('meta', $result);
        $this->assertArrayNotHasKey('text_content', $result);

        // Verify the content is properly formatted
        $this->assertStringContainsString('Provider: ABC Insurance', $result['text_transcodes']);
    }

    #[Test]
    public function filter_withStoredFilesButNoTranscodes_excludesTextTranscodesKey(): void
    {
        // Given - artifact with stored file but NO text transcode
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => null,
        ]);

        $storedFile = StoredFile::factory()->create([
            'disk'     => 'local',
            'filepath' => 'test/file.jpg',
            'filename' => 'test-file.jpg',
            'mime'     => 'image/jpeg',
        ]);
        $artifact->storedFiles()->attach($storedFile->id);

        // When - filter with text transcodes enabled
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact->fresh(['storedFiles']))
            ->includeFiles(true)
            ->includeJson(false)
            ->includeMeta(false)
            ->includeText(false)
            ->includeTextTranscodes(true)
            ->filter();

        // Then - text_transcodes key should NOT be present since there are no transcodes
        $this->assertIsArray($result);
        $this->assertArrayHasKey('files', $result);
        $this->assertArrayNotHasKey('text_transcodes', $result);
    }

    #[Test]
    public function filter_formatsTextTranscodesWithFilename(): void
    {
        // Given - artifact with stored file that has text transcode
        $artifact = $this->createArtifactWithTextTranscode('Document content here', 'my-document.pdf');
        // Add json content to avoid isTextOnly() returning true (which returns string)
        $artifact->json_content = ['placeholder' => true];
        $artifact->save();
        $artifact = $artifact->fresh(['storedFiles']);

        // When
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeFiles(false)
            ->includeJson(true)
            ->includeTextTranscodes(true)
            ->filter();

        // Then - text_transcodes should include the filename
        $this->assertIsArray($result);
        $this->assertArrayHasKey('text_transcodes', $result);
        $this->assertStringContainsString('=== File: my-document.pdf ===', $result['text_transcodes']);
    }

    #[Test]
    public function isTextOnly_withOnlyText_returnsTrue(): void
    {
        // Given - artifact with only text
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Only text here',
            'json_content' => null,
            'meta'         => null,
        ]);

        // When
        $service = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeText(true)
            ->includeFiles(false)
            ->includeJson(false)
            ->includeMeta(false);

        // Then
        $this->assertTrue($service->isTextOnly());
    }

    #[Test]
    public function isTextOnly_withJsonContent_returnsFalse(): void
    {
        // Given - artifact with text and json
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Some text',
            'json_content' => ['key' => 'value'],
        ]);

        // When
        $service = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeText(true)
            ->includeFiles(false)
            ->includeJson(true)
            ->includeMeta(false);

        // Then
        $this->assertFalse($service->isTextOnly());
    }

    #[Test]
    public function hasText_withTextContent_returnsTrue(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Some text content',
        ]);

        // When
        $hasText = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeText(true)
            ->hasText();

        // Then
        $this->assertTrue($hasText);
    }

    #[Test]
    public function hasText_withTextContentButIncludeTextFalse_returnsFalse(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Some text content',
        ]);

        // When
        $hasText = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeText(false)
            ->hasText();

        // Then
        $this->assertFalse($hasText);
    }

    #[Test]
    public function hasFiles_withStoredFiles_returnsTrue(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $storedFile = StoredFile::factory()->create([
            'disk'     => 'local',
            'filepath' => 'test/file.jpg',
            'filename' => 'test.jpg',
            'mime'     => 'image/jpeg',
        ]);
        $artifact->storedFiles()->attach($storedFile->id);

        // When
        $hasFiles = app(ArtifactFilterService::class)
            ->setArtifact($artifact->fresh(['storedFiles']))
            ->includeFiles(true)
            ->hasFiles();

        // Then
        $this->assertTrue($hasFiles);
    }

    #[Test]
    public function hasFiles_withStoredFilesButIncludeFilesFalse_returnsFalse(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $storedFile = StoredFile::factory()->create([
            'disk'     => 'local',
            'filepath' => 'test/file.jpg',
            'filename' => 'test.jpg',
            'mime'     => 'image/jpeg',
        ]);
        $artifact->storedFiles()->attach($storedFile->id);

        // When
        $hasFiles = app(ArtifactFilterService::class)
            ->setArtifact($artifact->fresh(['storedFiles']))
            ->includeFiles(false)
            ->hasFiles();

        // Then
        $this->assertFalse($hasFiles);
    }

    #[Test]
    public function filter_withOnlyTextTranscodes_returnsStringWithFormattedTranscodes(): void
    {
        // Given - artifact with text transcode but NO text content, json, meta, or files included
        $artifact = $this->createArtifactWithTextTranscode('Document content from image');

        // When - only text transcodes enabled (no files, json, meta, text)
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeFiles(false)
            ->includeJson(false)
            ->includeMeta(false)
            ->includeText(false)
            ->includeTextTranscodes(true)
            ->filter();

        // Then - should return string with formatted transcodes
        $this->assertIsString($result);
        $this->assertStringContainsString('=== File: test-file.jpg ===', $result);
        $this->assertStringContainsString('Document content from image', $result);
    }

    #[Test]
    public function filter_withTextContentAndTextTranscodes_returnsStringCombined(): void
    {
        // Given - artifact with both text content AND text transcode (no json, meta, files)
        $artifact               = $this->createArtifactWithTextTranscode('Transcoded image text');
        $artifact->text_content = 'Some original text content';
        $artifact->save();
        $artifact = $artifact->fresh(['storedFiles']);

        // When - text and text transcodes enabled but no files, json, or meta
        $result = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeFiles(false)
            ->includeJson(false)
            ->includeMeta(false)
            ->includeText(true)
            ->includeTextTranscodes(true)
            ->filter();

        // Then - should return combined string with text content followed by transcodes
        $this->assertIsString($result);
        $this->assertStringContainsString('Some original text content', $result);
        $this->assertStringContainsString('=== File: test-file.jpg ===', $result);
        $this->assertStringContainsString('Transcoded image text', $result);

        // Verify the text content comes before transcodes (separated by double newline)
        $textPos      = strpos($result, 'Some original text content');
        $transcodePos = strpos($result, '=== File:');
        $this->assertLessThan($transcodePos, $textPos, 'Text content should come before transcodes');
    }

    #[Test]
    public function isTextOnly_withTextTranscodesButNoFilesJsonMeta_returnsTrue(): void
    {
        // Given - artifact with text transcode (no json, meta)
        $artifact = $this->createArtifactWithTextTranscode('Transcoded content');

        // When - files not included but text transcodes enabled
        $service = app(ArtifactFilterService::class)
            ->setArtifact($artifact)
            ->includeFiles(false)
            ->includeJson(false)
            ->includeMeta(false)
            ->includeText(false)
            ->includeTextTranscodes(true);

        // Then - should be text-only since no files, json, or meta
        $this->assertTrue($service->isTextOnly());
    }

    /**
     * Helper method to create an artifact with a stored file that has a text transcode
     */
    private function createArtifactWithTextTranscode(string $transcodeContent, string $filename = 'test-file.jpg'): Artifact
    {
        // Create artifact
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => null,
            'json_content' => null,
            'meta'         => null,
        ]);

        // Create the original stored file (image)
        $storedFile = StoredFile::factory()->create([
            'disk'     => 'local',
            'filepath' => 'test/' . $filename,
            'filename' => $filename,
            'mime'     => 'image/jpeg',
        ]);

        // Attach file to artifact
        $artifact->storedFiles()->attach($storedFile->id);

        // Create the transcode file path and put content there
        $transcodePath = 'test/transcode-' . uniqid() . '.txt';
        Storage::disk('local')->put($transcodePath, $transcodeContent);

        // Create the text transcode for the file
        StoredFile::factory()->create([
            'disk'                    => 'local',
            'filepath'                => $transcodePath,
            'filename'                => 'transcode.txt',
            'mime'                    => 'text/plain',
            'original_stored_file_id' => $storedFile->id,
            'transcode_name'          => 'Image To Text LLM',
        ]);

        return $artifact->fresh(['storedFiles']);
    }
}
