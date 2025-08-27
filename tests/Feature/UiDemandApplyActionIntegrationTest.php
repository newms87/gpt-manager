<?php

namespace Tests\Feature;

use App\Models\UiDemand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandApplyActionIntegrationTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_applyAction_update_withInputFiles_updatesFilesCorrectly(): void
    {
        // Given - Create a demand with initial files
        $initialFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'filename' => 'initial-file.pdf',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title' => 'Test Demand for Update',
            'description' => 'Initial description',
        ]);
        $uiDemand->inputFiles()->attach($initialFile->id, ['category' => 'input']);

        // Create new files for update
        $newFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'filename' => 'new-file.pdf',
        ]);

        $requestData = [
            'action' => 'update',
            'data' => [
                'title' => 'Updated Demand Title',
                'description' => 'Updated description',
                'input_files' => [
                    [
                        'id' => $newFile->id,
                        '__type' => 'StoredFileResource',
                        '__timestamp' => time() * 1000,
                        '__deleted_at' => null,
                        'filename' => 'new-file.pdf',
                    ]
                ]
            ]
        ];

        // When - Call the apply-action endpoint
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/apply-action", $requestData);

        // Then - Verify the response
        $response->assertSuccessful();
        $responseData = $response->json();
        
        // Verify the demand was updated (data is in the 'item' key for danx responses)
        $this->assertEquals('Updated Demand Title', $responseData['item']['title']);
        $this->assertEquals('Updated description', $responseData['item']['description']);
        
        // Verify input files are in the response
        $this->assertCount(1, $responseData['item']['input_files'], 'Response should contain 1 input file');
        $this->assertEquals($newFile->id, $responseData['item']['input_files'][0]['id']);

        // Verify the input files were synced correctly
        $uiDemand->refresh();
        $inputFiles = $uiDemand->inputFiles;
        
        $this->assertCount(1, $inputFiles, 'Should have exactly 1 input file');
        $this->assertEquals($newFile->id, $inputFiles->first()->id, 'Should have the new file');
        $this->assertEquals('input', $inputFiles->first()->pivot->category, 'Pivot category should be input');
        
        // Verify old file was removed
        $this->assertFalse($inputFiles->pluck('id')->contains($initialFile->id), 'Initial file should be removed');
    }

    public function test_applyAction_create_withInputFiles_createsWithFilesCorrectly(): void
    {
        // Given
        $file1 = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'filename' => 'file1.pdf',
        ]);
        $file2 = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'filename' => 'file2.pdf',
        ]);

        $requestData = [
            'action' => 'create',
            'data' => [
                'title' => 'New Demand with Files',
                'description' => 'Test description',
                'input_files' => [
                    [
                        'id' => $file1->id,
                        '__type' => 'StoredFileResource',
                        '__timestamp' => time() * 1000,
                        '__deleted_at' => null,
                        'filename' => 'file1.pdf',
                    ],
                    [
                        'id' => $file2->id,
                        '__type' => 'StoredFileResource', 
                        '__timestamp' => time() * 1000,
                        '__deleted_at' => null,
                        'filename' => 'file2.pdf',
                    ]
                ]
            ]
        ];

        // When
        $response = $this->postJson('/api/ui-demands/apply-action', $requestData);

        // Then
        $response->assertSuccessful();
        $responseData = $response->json();
        
        // For create actions, data is in 'result' key
        $this->assertEquals('New Demand with Files', $responseData['result']['title']);
        $this->assertEquals('Test description', $responseData['result']['description']);
        $this->assertNotNull($responseData['result']['team_object_id'], 'Team object should be created');

        // Verify demand was created in database
        $uiDemand = UiDemand::where('title', 'New Demand with Files')->first();
        $this->assertNotNull($uiDemand);
        
        // Verify team scoping
        $this->assertEquals($this->user->currentTeam->id, $uiDemand->team_id);
        $this->assertEquals($this->user->id, $uiDemand->user_id);
        
        // Verify input files were attached
        $inputFiles = $uiDemand->inputFiles;
        $this->assertCount(2, $inputFiles);
        $this->assertTrue($inputFiles->pluck('id')->contains($file1->id));
        $this->assertTrue($inputFiles->pluck('id')->contains($file2->id));
        
        // Verify pivot data
        foreach ($inputFiles as $file) {
            $this->assertEquals('input', $file->pivot->category);
        }
        
        // Verify team object was created
        $this->assertNotNull($uiDemand->team_object_id);
    }

    public function test_applyAction_update_withEmptyInputFiles_clearsAllFiles(): void
    {
        // Given - Create demand with files
        $existingFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'filename' => 'existing-file.pdf',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title' => 'Test Demand',
        ]);
        $uiDemand->inputFiles()->attach($existingFile->id, ['category' => 'input']);

        $requestData = [
            'action' => 'update',
            'data' => [
                'title' => 'Updated Title',
                'input_files' => [] // Empty array should clear files
            ]
        ];

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/apply-action", $requestData);

        // Then
        $response->assertSuccessful();
        
        $uiDemand->refresh();
        $this->assertEquals('Updated Title', $uiDemand->title);
        $this->assertCount(0, $uiDemand->inputFiles, 'All input files should be cleared');
    }
}