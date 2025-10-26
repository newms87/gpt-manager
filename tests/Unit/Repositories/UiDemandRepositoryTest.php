<?php

namespace Tests\Unit\Repositories;

use App\Models\Demand\UiDemand;
use App\Models\Schema\SchemaDefinition;
use App\Models\TeamObject\TeamObject;
use App\Repositories\TeamObjectRepository;
use App\Repositories\UiDemandRepository;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandRepositoryTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private UiDemandRepository $repository;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->repository = new UiDemandRepository();

        // Create required SchemaDefinition
        SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Demand Schema',
        ]);
    }

    public function test_createDemand_withValidData_createsUiDemandWithTeamObjectAndFiles(): void
    {
        // Given
        $storedFile1 = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'filename' => 'test-file-1.pdf',
        ]);
        $storedFile2 = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'filename' => 'test-file-2.pdf',
        ]);

        $data = [
            'title'       => 'Test UI Demand',
            'description' => 'Test description',
            'input_files' => [
                ['id' => $storedFile1->id],
                ['id' => $storedFile2->id],
            ],
        ];

        // When
        $result = $this->repository->applyAction('create', null, $data);

        // Then
        $this->assertIsArray($result);

        $uiDemand = UiDemand::where('title', 'Test UI Demand')->first();
        $this->assertNotNull($uiDemand);
        $this->assertEquals($this->user->currentTeam->id, $uiDemand->team_id);
        $this->assertEquals($this->user->id, $uiDemand->user_id);
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->status);
        $this->assertEquals('Test UI Demand', $uiDemand->title);
        $this->assertEquals('Test description', $uiDemand->description);

        // Verify team object was created
        $this->assertNotNull($uiDemand->team_object_id);
        $teamObject = TeamObject::find($uiDemand->team_object_id);
        $this->assertNotNull($teamObject);
        $this->assertEquals('Demand', $teamObject->type);
        $this->assertEquals('Test UI Demand', $teamObject->name);

        // Verify input files were synced correctly
        $inputFiles = $uiDemand->inputFiles;
        $this->assertCount(2, $inputFiles);
        $this->assertTrue($inputFiles->pluck('id')->contains($storedFile1->id));
        $this->assertTrue($inputFiles->pluck('id')->contains($storedFile2->id));

        // Verify pivot table has correct category
        foreach ($inputFiles as $file) {
            $this->assertEquals('input', $file->pivot->category);
        }
    }

    public function test_createDemand_withoutInputFiles_createsUiDemandSuccessfully(): void
    {
        // Given
        $data = [
            'title'       => 'Test UI Demand Without Files',
            'description' => 'Test description',
        ];

        // When
        $result = $this->repository->applyAction('create', null, $data);

        // Then
        $this->assertIsArray($result);

        $uiDemand = UiDemand::where('title', 'Test UI Demand Without Files')->first();
        $this->assertNotNull($uiDemand);
        $this->assertEquals($this->user->currentTeam->id, $uiDemand->team_id);
        $this->assertEquals($this->user->id, $uiDemand->user_id);
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->status);

        // Verify team object was created
        $this->assertNotNull($uiDemand->team_object_id);

        // Verify no input files
        $this->assertCount(0, $uiDemand->inputFiles);
    }

    public function test_updateDemand_withInputFiles_updatesAndSyncsFilesCorrectly(): void
    {
        // Given - Create initial demand with one file
        $initialFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'filename' => 'initial-file.pdf',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'     => $this->user->currentTeam->id,
            'user_id'     => $this->user->id,
            'title'       => 'Original Title',
            'description' => 'Original description',
        ]);
        $uiDemand->inputFiles()->attach($initialFile->id, ['category' => 'input']);

        // Create new files for update
        $newFile1 = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'filename' => 'new-file-1.pdf',
        ]);
        $newFile2 = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'filename' => 'new-file-2.pdf',
        ]);

        $updateData = [
            'title'       => 'Updated Title',
            'description' => 'Updated description',
            'input_files' => [
                ['id' => $newFile1->id],
                ['id' => $newFile2->id],
            ],
        ];

        // When
        $result = $this->repository->applyAction('update', $uiDemand, $updateData);

        // Then
        $this->assertIsArray($result);

        $uiDemand->refresh();
        $this->assertEquals('Updated Title', $uiDemand->title);
        $this->assertEquals('Updated description', $uiDemand->description);

        // Verify input files were replaced correctly
        $inputFiles = $uiDemand->inputFiles;
        $this->assertCount(2, $inputFiles);
        $this->assertTrue($inputFiles->pluck('id')->contains($newFile1->id));
        $this->assertTrue($inputFiles->pluck('id')->contains($newFile2->id));
        $this->assertFalse($inputFiles->pluck('id')->contains($initialFile->id));

        // Verify pivot table has correct category
        foreach ($inputFiles as $file) {
            $this->assertEquals('input', $file->pivot->category);
        }
    }

    public function test_updateDemand_withEmptyInputFiles_removesAllFiles(): void
    {
        // Given - Create demand with files
        $existingFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'filename' => 'existing-file.pdf',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);
        $uiDemand->inputFiles()->attach($existingFile->id, ['category' => 'input']);

        $updateData = [
            'title'       => 'Updated Title',
            'input_files' => [], // Empty array
        ];

        // When
        $result = $this->repository->applyAction('update', $uiDemand, $updateData);

        // Then
        $this->assertIsArray($result);

        $uiDemand->refresh();
        $this->assertEquals('Updated Title', $uiDemand->title);
        $this->assertCount(0, $uiDemand->inputFiles);
    }

    public function test_syncInputFiles_withValidFileIds_syncsCorrectly(): void
    {
        // Given
        $file1 = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'filename' => 'file-1.pdf',
        ]);
        $file2 = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'filename' => 'file-2.pdf',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $data = [
            'input_files' => [
                ['id' => $file1->id],
                ['id' => $file2->id],
            ],
        ];

        // When
        $this->repository->syncInputFiles($uiDemand, $data);

        // Then
        $uiDemand->refresh();
        $inputFiles = $uiDemand->inputFiles;
        $this->assertCount(2, $inputFiles);
        $this->assertTrue($inputFiles->pluck('id')->contains($file1->id));
        $this->assertTrue($inputFiles->pluck('id')->contains($file2->id));

        // Verify pivot table has correct category
        foreach ($inputFiles as $file) {
            $this->assertEquals('input', $file->pivot->category);
        }
    }

    public function test_syncInputFiles_withEmptyInputFiles_doesNothing(): void
    {
        // Given
        $existingFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($existingFile->id, ['category' => 'input']);

        $data = []; // No input_files key

        // When
        $this->repository->syncInputFiles($uiDemand, $data);

        // Then
        $uiDemand->refresh();
        $this->assertCount(1, $uiDemand->inputFiles); // Files remain unchanged
    }

    public function test_syncInputFiles_withNonExistentFileIds_ignoresInvalidIds(): void
    {
        // Given
        $validFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'filename' => 'valid-file.pdf',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $data = [
            'input_files' => [
                ['id' => $validFile->id],
                ['id' => 'non-existent-id'],
                ['id' => '99999'],
            ],
        ];

        // When
        $this->repository->syncInputFiles($uiDemand, $data);

        // Then
        $uiDemand->refresh();
        $inputFiles = $uiDemand->inputFiles;
        $this->assertCount(1, $inputFiles); // Only valid file is synced
        $this->assertEquals($validFile->id, $inputFiles->first()->id);
    }

    public function test_query_scopesToCurrentTeam(): void
    {
        // Given
        $otherTeam = $this->createTeam();

        $currentTeamDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Current Team Demand',
        ]);

        $otherTeamDemand = UiDemand::factory()->create([
            'team_id' => $otherTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Other Team Demand',
        ]);

        // When
        $results = $this->repository->query()->get();

        // Then
        $this->assertCount(1, $results);
        $this->assertEquals($currentTeamDemand->id, $results->first()->id);
        $this->assertEquals('Current Team Demand', $results->first()->title);
    }

    public function test_createDemand_createsTeamObjectWithCorrectMetadata(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Test Demand Title',
        ]);

        $this->mock(TeamObjectRepository::class, function ($mock) use ($teamObject) {
            $mock->shouldReceive('createTeamObject')
                ->once()
                ->with(
                    'Demand',
                    'Test Demand Title',
                    \Mockery::on(function ($data) {
                        return isset($data['demand_id'])                 &&
                            isset($data['title'])                        &&
                            isset($data['description'])                  &&
                            $data['title']       === 'Test Demand Title' &&
                            $data['description'] === 'Test Demand Description';
                    })
                )
                ->andReturn($teamObject);
        });

        $data = [
            'title'       => 'Test Demand Title',
            'description' => 'Test Demand Description',
        ];

        // When
        $result = $this->repository->applyAction('create', null, $data);

        // Then
        $this->assertIsArray($result);

        $uiDemand = UiDemand::where('title', 'Test Demand Title')->first();
        $this->assertNotNull($uiDemand);
        $this->assertEquals($teamObject->id, $uiDemand->team_object_id);
    }

    private function createTeam()
    {
        return \App\Models\Team\Team::factory()->create();
    }
}
