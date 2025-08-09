<?php

namespace Tests\Unit\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\Runners\LoadCsvTaskRunner;
use Mockery;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\FileHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;

class LoadCsvTaskRunnerTest extends AuthenticatedTestCase
{
    protected TaskDefinition     $taskDefinition;
    protected TaskRun           $taskRun;
    protected TaskProcess       $taskProcess;
    protected LoadCsvTaskRunner $taskRunner;
    protected array             $testCsvData;

    public function setUp(): void
    {
        parent::setUp();

        // Sample CSV data after being parsed by FileHelper
        $this->testCsvData = [
            ['name' => 'John Doe', 'age' => '30', 'email' => 'john.doe@example.com', 'department' => 'Engineering'],
            ['name' => 'Jane Smith', 'age' => '28', 'email' => 'jane.smith@example.com', 'department' => 'Marketing'],
            ['name' => 'Bob Johnson', 'age' => '35', 'email' => 'bob.johnson@example.com', 'department' => 'Finance'],
            ['name' => 'Alice Brown', 'age' => '42', 'email' => 'alice.brown@example.com', 'department' => 'HR'],
        ];

        // Create test task definition
        $this->taskDefinition = TaskDefinition::factory()->create([
            'name'             => 'Test Load CSV Task',
            'task_runner_name' => LoadCsvTaskRunner::RUNNER_NAME,
            'task_runner_config' => [
                'batch_size' => 1,
                'selected_columns' => []
            ]
        ]);

        // Create task run
        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'name'               => 'Test Load CSV Run',
        ]);

        // Create task process
        $this->taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'activity'    => 'Testing CSV loading',
        ]);

        // Get the actual task runner instance - it will be properly initialized via the chain:
        // TaskProcess::getRunner() -> TaskRun::getRunner()->setTaskProcess() -> TaskDefinition::getRunner()->setTaskRun()
        $this->taskRunner = $this->taskProcess->getRunner();
    }


    /**
     * Helper to create a real StoredFile for testing
     *
     * @param string $filename The filename for the test file
     * @return StoredFile
     */
    protected function createTestStoredFile(string $filename = 'test.csv'): StoredFile
    {
        return StoredFile::factory()->create([
            'filename' => $filename,
            'url' => 'path/to/' . $filename,
            'mime' => 'text/csv'
        ]);
    }

    /**
     * Helper method to create test CSV data with selected columns only
     *
     * @param array $selectedColumns The columns to include
     * @return array The filtered CSV data
     */
    protected function createFilteredCsvData(array $selectedColumns): array
    {
        if (empty($selectedColumns)) {
            return $this->testCsvData;
        }

        $filteredData = [];
        foreach($this->testCsvData as $row) {
            $filteredRow = [];
            foreach($selectedColumns as $column) {
                if (isset($row[$column])) {
                    $filteredRow[$column] = $row[$column];
                }
            }
            $filteredData[] = $filteredRow;
        }

        return $filteredData;
    }


    /**
     * Test the run method with empty CSV files
     */
    public function test_run_withNoFiles_completesWithEmptyResult()
    {
        // Given no CSV files are attached to the task process
        // (task process already created with no input artifacts)

        // When we run the CSV loading task
        $this->taskRunner->run();

        // Then the task should complete with no output artifacts
        $this->taskProcess->refresh();
        $outputArtifacts = $this->taskProcess->outputArtifacts;
        $this->assertCount(0, $outputArtifacts);
        // Check that the process finished (status will be updated during completion)
        $this->assertNotNull($this->taskProcess->completed_at);
    }

    /**
     * Test the run method with one CSV file and batch size 1
     */
    public function test_run_withOneCsvFileBatchSizeOne_createsSeparateArtifacts()
    {
        // Given we have a CSV file attached to the task process
        $csvFile = $this->createTestStoredFile('employees.csv');
        $artifact = Artifact::factory()->create();
        $artifact->storedFiles()->attach($csvFile->id);
        $this->taskProcess->inputArtifacts()->attach($artifact->id);

        // Mock FileHelper (3rd party dependency) to return our test data
        $fileHelperMock = Mockery::mock('alias:' . FileHelper::class);
        $fileHelperMock->shouldReceive('parseCsvFile')
            ->once()
            ->with($csvFile->url, 0, 1, [])
            ->andReturn($this->testCsvData);

        // When we run the CSV loading task
        $this->taskRunner->run();

        // Then we should have 4 output artifacts (one per CSV row)
        $this->taskProcess->refresh();
        $outputArtifacts = $this->taskProcess->outputArtifacts;
        $this->assertCount(4, $outputArtifacts);

        // Verify artifact content matches CSV data
        $this->assertEquals([$this->testCsvData[0]], $outputArtifacts[0]->json_content);
        $this->assertEquals([$this->testCsvData[1]], $outputArtifacts[1]->json_content);
        $this->assertEquals([$this->testCsvData[2]], $outputArtifacts[2]->json_content);
        $this->assertEquals([$this->testCsvData[3]], $outputArtifacts[3]->json_content);

        // Verify meta data
        $this->assertEquals(1, $outputArtifacts[0]->meta['batch_size']);
        $this->assertEquals(0, $outputArtifacts[0]->meta['batch_index']);
        $this->assertEquals(1, $outputArtifacts[1]->meta['batch_index']);
        $this->assertEquals(2, $outputArtifacts[2]->meta['batch_index']);
        $this->assertEquals(3, $outputArtifacts[3]->meta['batch_index']);

        // Verify artifact names
        $this->assertEquals('employees - Batch 0', $outputArtifacts[0]->name);
        $this->assertEquals('employees - Batch 1', $outputArtifacts[1]->name);
        $this->assertEquals('employees - Batch 2', $outputArtifacts[2]->name);
        $this->assertEquals('employees - Batch 3', $outputArtifacts[3]->name);
    }

    /**
     * Test the run method with one CSV file and batch size 0 (all in one batch)
     */
    public function test_run_withOneCsvFileBatchSizeZero_createsOneArtifact()
    {
        // Given we have a new task definition with batch size 0 configuration
        $taskDef = TaskDefinition::factory()->create([
            'name'             => 'Test Load CSV Task - Batch 0',
            'task_runner_name' => LoadCsvTaskRunner::RUNNER_NAME,
            'task_runner_config' => [
                'batch_size' => 0,
                'selected_columns' => []
            ]
        ]);
        
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDef->id,
            'name'               => 'Test Load CSV Run - Batch 0',
        ]);
        
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'activity'    => 'Testing CSV loading - Batch 0',
        ]);
        
        $taskRunner = $taskProcess->getRunner();
        
        $csvFile = $this->createTestStoredFile('all_employees.csv');
        $artifact = Artifact::factory()->create();
        $artifact->storedFiles()->attach($csvFile->id);
        $taskProcess->inputArtifacts()->attach($artifact->id);

        // Mock FileHelper (3rd party dependency)
        $fileHelperMock = Mockery::mock('alias:' . FileHelper::class);
        $fileHelperMock->shouldReceive('parseCsvFile')
            ->once()
            ->with($csvFile->url, 0, 1, [])
            ->andReturn($this->testCsvData);

        // When we run the CSV loading task
        $taskRunner->run();

        // Then we should have 1 output artifact containing all CSV data
        $taskProcess->refresh();
        $outputArtifacts = $taskProcess->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);

        $artifact = $outputArtifacts->first();
        $this->assertEquals($this->testCsvData, $artifact->json_content);
        $this->assertEquals(0, $artifact->meta['batch_size']);
        $this->assertEquals(0, $artifact->meta['batch_index']);
        $this->assertEquals('all_employees', $artifact->name);
    }

    /**
     * Test the run method with selected columns
     */
    public function test_run_withSelectedColumns_filtersColumns()
    {
        // Given we have a new task definition with selected columns configuration
        $selectedColumns = ['name', 'email'];
        $taskDef = TaskDefinition::factory()->create([
            'name'             => 'Test Load CSV Task - Selected Columns',
            'task_runner_name' => LoadCsvTaskRunner::RUNNER_NAME,
            'task_runner_config' => [
                'batch_size' => 1,
                'selected_columns' => $selectedColumns
            ]
        ]);
        
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDef->id,
            'name'               => 'Test Load CSV Run - Selected Columns',
        ]);
        
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'activity'    => 'Testing CSV loading - Selected Columns',
        ]);
        
        $taskRunner = $taskProcess->getRunner();
        
        $csvFile = $this->createTestStoredFile('filtered_employees.csv');
        $artifact = Artifact::factory()->create();
        $artifact->storedFiles()->attach($csvFile->id);
        $taskProcess->inputArtifacts()->attach($artifact->id);

        // Create expected filtered data
        $filteredData = $this->createFilteredCsvData($selectedColumns);

        // Mock FileHelper (3rd party dependency) to return filtered data
        $fileHelperMock = Mockery::mock('alias:' . FileHelper::class);
        $fileHelperMock->shouldReceive('parseCsvFile')
            ->once()
            ->with($csvFile->url, 0, 1, $selectedColumns)
            ->andReturn($filteredData);

        // When we run the CSV loading task
        $taskRunner->run();

        // Then we should have artifacts with only selected columns
        $taskProcess->refresh();
        $outputArtifacts = $taskProcess->outputArtifacts;
        $this->assertCount(4, $outputArtifacts);

        // Verify that artifacts contain only the selected columns
        foreach($outputArtifacts as $index => $artifact) {
            $this->assertEquals([$filteredData[$index]], $artifact->json_content);
            $rowData = $artifact->json_content[0];
            $this->assertArrayHasKey('name', $rowData);
            $this->assertArrayHasKey('email', $rowData);
            $this->assertArrayNotHasKey('age', $rowData);
            $this->assertArrayNotHasKey('department', $rowData);
        }
    }

    /**
     * Test the run method with nonexistent columns
     */
    public function test_run_withNonexistentColumns_handlesError()
    {
        // Given we have a new task definition with nonexistent columns configuration
        $selectedColumns = ['name', 'nonexistent_column'];
        $taskDef = TaskDefinition::factory()->create([
            'name'             => 'Test Load CSV Task - Bad Columns',
            'task_runner_name' => LoadCsvTaskRunner::RUNNER_NAME,
            'task_runner_config' => [
                'batch_size' => 1,
                'selected_columns' => $selectedColumns
            ]
        ]);
        
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDef->id,
            'name'               => 'Test Load CSV Run - Bad Columns',
        ]);
        
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'activity'    => 'Testing CSV loading - Bad Columns',
        ]);
        
        $taskRunner = $taskProcess->getRunner();
        
        $csvFile = $this->createTestStoredFile('bad_columns.csv');
        $artifact = Artifact::factory()->create();
        $artifact->storedFiles()->attach($csvFile->id);
        $taskProcess->inputArtifacts()->attach($artifact->id);

        // Mock FileHelper (3rd party dependency) to throw validation error
        $fileHelperMock = Mockery::mock('alias:' . FileHelper::class);
        $fileHelperMock->shouldReceive('parseCsvFile')
            ->once()
            ->with($csvFile->url, 0, 1, $selectedColumns)
            ->andThrow(new ValidationError('Column nonexistent_column not found in CSV file'));

        // When we run the CSV loading task
        $taskRunner->run();

        // Then the task should complete with no artifacts due to the error
        $taskProcess->refresh();
        $outputArtifacts = $taskProcess->outputArtifacts;
        $this->assertCount(0, $outputArtifacts);
        // Check that the process finished (status will be updated during completion)
        $this->assertNotNull($taskProcess->completed_at);
    }

    /**
     * Test the run method with batch size 2
     */
    public function test_run_withBatchSizeTwo_createsBatchedArtifacts()
    {
        // Given we have a new task definition with batch size 2 configuration
        $taskDef = TaskDefinition::factory()->create([
            'name'             => 'Test Load CSV Task - Batch 2',
            'task_runner_name' => LoadCsvTaskRunner::RUNNER_NAME,
            'task_runner_config' => [
                'batch_size' => 2,
                'selected_columns' => []
            ]
        ]);
        
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDef->id,
            'name'               => 'Test Load CSV Run - Batch 2',
        ]);
        
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'activity'    => 'Testing CSV loading - Batch 2',
        ]);
        
        $taskRunner = $taskProcess->getRunner();
        
        $csvFile = $this->createTestStoredFile('batched_employees.csv');
        $artifact = Artifact::factory()->create();
        $artifact->storedFiles()->attach($csvFile->id);
        $taskProcess->inputArtifacts()->attach($artifact->id);

        // Mock FileHelper (3rd party dependency)
        $fileHelperMock = Mockery::mock('alias:' . FileHelper::class);
        $fileHelperMock->shouldReceive('parseCsvFile')
            ->once()
            ->with($csvFile->url, 0, 1, [])
            ->andReturn($this->testCsvData);

        // When we run the CSV loading task
        $taskRunner->run();

        // Then we should have 2 output artifacts (4 rows / 2 per batch)
        $taskProcess->refresh();
        $outputArtifacts = $taskProcess->outputArtifacts;
        $this->assertCount(2, $outputArtifacts);

        // First batch should contain first 2 rows
        $expectedFirstBatch = [$this->testCsvData[0], $this->testCsvData[1]];
        $this->assertEquals($expectedFirstBatch, $outputArtifacts[0]->json_content);
        $this->assertEquals(2, $outputArtifacts[0]->meta['batch_size']);
        $this->assertEquals(0, $outputArtifacts[0]->meta['batch_index']);

        // Second batch should contain last 2 rows
        $expectedSecondBatch = [$this->testCsvData[2], $this->testCsvData[3]];
        $this->assertEquals($expectedSecondBatch, $outputArtifacts[1]->json_content);
        $this->assertEquals(2, $outputArtifacts[1]->meta['batch_size']);
        $this->assertEquals(1, $outputArtifacts[1]->meta['batch_index']);
    }

}
