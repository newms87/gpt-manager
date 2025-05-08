<?php

namespace Tests\Unit\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Services\Task\Runners\LoadCsvTaskRunner;
use Mockery;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\FileHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\TestCase;

class LoadCsvTaskRunnerTest extends TestCase
{
    protected LoadCsvTaskRunner $taskRunner;
    protected array             $testCsvData;
    protected array             $mockFiles;

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

        // Create mock task runner with mocked methods
        $this->taskRunner = Mockery::mock(LoadCsvTaskRunner::class)->makePartial();
    }

    /**
     * Helper to create a mock StoredFile for testing
     *
     * @param string $filename The filename for the mock file
     * @return StoredFile
     */
    protected function createMockStoredFile(string $filename = 'test_data.csv'): StoredFile
    {
        $storedFile = Mockery::mock(StoredFile::class);
        $storedFile->shouldReceive('getAttribute')->with('filename')->andReturn($filename);
        $storedFile->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $storedFile->shouldReceive('setAttribute')->withAnyArgs()->andReturnSelf();

        return $storedFile;
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
     * Test splitIntoBatches with batch size 1 (flat arrays)
     */
    public function testSplitIntoBatchesWithBatchSizeOne()
    {
        // Call the splitIntoBatches method with batch size 1
        $result = $this->taskRunner->splitIntoBatches($this->testCsvData, 1);

        // Verify the structure and content
        $this->assertIsArray($result);
        $this->assertCount(4, $result);

        // Each batch should contain exactly one row
        foreach($result as $batch) {
            $this->assertCount(1, $batch);
        }

        // Verify specific data elements in each batch
        $this->assertEquals('John Doe', $result[0][0]['name']);
        $this->assertEquals('jane.smith@example.com', $result[1][0]['email']);
        $this->assertEquals('Finance', $result[2][0]['department']);
        $this->assertEquals('42', $result[3][0]['age']);
    }

    /**
     * Test splitIntoBatches with batch size 2
     */
    public function testSplitIntoBatchesWithBatchSizeTwo()
    {
        // Call the splitIntoBatches method with batch size 2
        $result = $this->taskRunner->splitIntoBatches($this->testCsvData, 2);

        // Verify the structure and content
        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Should have 2 batches

        // Each batch should contain the right number of rows
        $this->assertCount(2, $result[0]); // First batch has 2 rows
        $this->assertCount(2, $result[1]); // Second batch has 2 rows

        // Verify specific data elements in each batch
        $this->assertEquals('John Doe', $result[0][0]['name']);
        $this->assertEquals('Jane Smith', $result[0][1]['name']);
        $this->assertEquals('Bob Johnson', $result[1][0]['name']);
        $this->assertEquals('Alice Brown', $result[1][1]['name']);
    }

    /**
     * Test splitIntoBatches with batch size 0 (all rows in one batch)
     */
    public function testSplitIntoBatchesWithBatchSizeZero()
    {
        // Call the splitIntoBatches method with batch size 0
        $result = $this->taskRunner->splitIntoBatches($this->testCsvData, 0);

        // Verify the structure and content
        $this->assertIsArray($result);
        $this->assertCount(1, $result); // Should have 1 batch with all rows
        $this->assertCount(4, $result[0]); // The batch should have 4 rows

        // Verify all rows are in the single batch with correct order
        $this->assertEquals('John Doe', $result[0][0]['name']);
        $this->assertEquals('Jane Smith', $result[0][1]['name']);
        $this->assertEquals('Bob Johnson', $result[0][2]['name']);
        $this->assertEquals('Alice Brown', $result[0][3]['name']);
    }

    /**
     * Test splitIntoBatches with batch size 3 (uneven distribution)
     */
    public function testSplitIntoBatchesWithBatchSizeThree()
    {
        // Call the splitIntoBatches method with batch size 3
        $result = $this->taskRunner->splitIntoBatches($this->testCsvData, 3);

        // Verify the structure and content
        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Should have 2 batches

        // First batch should have 3 rows, second batch should have 1 row
        $this->assertCount(3, $result[0]);
        $this->assertCount(1, $result[1]);

        // Verify specific data elements
        $this->assertEquals('John Doe', $result[0][0]['name']);
        $this->assertEquals('Jane Smith', $result[0][1]['name']);
        $this->assertEquals('Bob Johnson', $result[0][2]['name']);
        $this->assertEquals('Alice Brown', $result[1][0]['name']);
    }

    /**
     * Test the run method with empty CSV files
     */
    public function testRunWithNoFiles()
    {
        // Setup mockTaskRunner for the run method test
        $this->taskRunner->shouldReceive('config')->with('batch_size', 1)->andReturn(1);
        $this->taskRunner->shouldReceive('config')->with('selected_columns', [])->andReturn([]);
        $this->taskRunner->shouldReceive('activity')->with('Starting CSV loading process w/ batch size 1: []', 10)->once();
        $this->taskRunner->shouldReceive('activity')->with('No CSV file provided', 100)->once();
        $this->taskRunner->shouldReceive('getAllFiles')->with(['csv'])->andReturn([]);
        $this->taskRunner->shouldReceive('complete')->once()->with([]);

        // Run the process
        $this->taskRunner->run();

        // Assert that the mock received the expected method calls
        $this->assertTrue(true, 'Run method completed successfully with no files');
    }

    /**
     * Test the run method with one CSV file and batch size 1
     */
    public function testRunWithOneCsvFileBatchSizeOne()
    {
        // Create a mock StoredFile for testing
        $mockFile = $this->createMockStoredFile();

        // We need to set properties directly rather than using dynamic properties
        $mockFile->shouldReceive('getAttribute')->with('url')->andReturn('path/to/test.csv');
        $mockFile->shouldReceive('getAttribute')->with('filename')->andReturn('test.csv');

        // Setup mockTaskRunner for the run method test
        $this->taskRunner->shouldReceive('config')->with('batch_size', 1)->andReturn(1);
        $this->taskRunner->shouldReceive('config')->with('selected_columns', [])->andReturn([]);
        $this->taskRunner->shouldReceive('activity')->with('Starting CSV loading process w/ batch size 1: []', 10)->once();
        $this->taskRunner->shouldReceive('activity')->with('Processing CSV files with batch size: 1')->once();
        $this->taskRunner->shouldReceive('activity')->with('Processing file test.csv')->once();
        $this->taskRunner->shouldReceive('activity')->with('Finished processing file test.csv', Mockery::any())->once();
        $this->taskRunner->shouldReceive('getAllFiles')->with(['csv'])->andReturn([$mockFile]);

        // Mock FileHelper
        $fileHelperMock = Mockery::mock('alias:' . FileHelper::class);
        $fileHelperMock->shouldReceive('parseCsvFile')
            ->once()
            ->with('path/to/test.csv', 0, 1, [])
            ->andReturn($this->testCsvData);

        // Mock the splitIntoBatches method to return individual rows as batches
        $this->taskRunner->shouldReceive('splitIntoBatches')
            ->once()
            ->with($this->testCsvData, 1)
            ->andReturn([[$this->testCsvData[0]], [$this->testCsvData[1]], [$this->testCsvData[2]], [$this->testCsvData[3]]]);

        // Mock artifact creation - should be called for each row
        $mockArtifact1 = Mockery::mock(Artifact::class);
        $mockArtifact2 = Mockery::mock(Artifact::class);
        $mockArtifact3 = Mockery::mock(Artifact::class);
        $mockArtifact4 = Mockery::mock(Artifact::class);

        $this->taskRunner->shouldReceive('createArtifactWithCsvData')
            ->once()
            ->with(0, 1, [$this->testCsvData[0]], $mockFile)
            ->andReturn($mockArtifact1);

        $this->taskRunner->shouldReceive('createArtifactWithCsvData')
            ->once()
            ->with(1, 1, [$this->testCsvData[1]], $mockFile)
            ->andReturn($mockArtifact2);

        $this->taskRunner->shouldReceive('createArtifactWithCsvData')
            ->once()
            ->with(2, 1, [$this->testCsvData[2]], $mockFile)
            ->andReturn($mockArtifact3);

        $this->taskRunner->shouldReceive('createArtifactWithCsvData')
            ->once()
            ->with(3, 1, [$this->testCsvData[3]], $mockFile)
            ->andReturn($mockArtifact4);

        $this->taskRunner->shouldReceive('complete')
            ->once()
            ->with([$mockArtifact1, $mockArtifact2, $mockArtifact3, $mockArtifact4]);

        // Run the process
        $this->taskRunner->run();

        // Add an assertion to make PHPUnit happy
        $this->assertTrue(true, 'Run method completed successfully');
    }

    /**
     * Test the run method with one CSV file and batch size 0 (all in one batch)
     */
    public function testRunWithOneCsvFileBatchSizeZero()
    {
        // Create a mock StoredFile for testing
        $mockFile = $this->createMockStoredFile();
        $mockFile->shouldReceive('getAttribute')->with('url')->andReturn('path/to/test.csv');
        $mockFile->shouldReceive('getAttribute')->with('filename')->andReturn('test.csv');

        // Setup mockTaskRunner for the run method test
        $this->taskRunner->shouldReceive('config')->with('batch_size', 1)->andReturn(0);
        $this->taskRunner->shouldReceive('config')->with('selected_columns', [])->andReturn([]);
        $this->taskRunner->shouldReceive('activity')->with('Starting CSV loading process w/ batch size 0: []', 10)->once();
        $this->taskRunner->shouldReceive('activity')->with('Processing CSV files with batch size: 0')->once();
        $this->taskRunner->shouldReceive('activity')->with('Processing file test.csv')->once();
        $this->taskRunner->shouldReceive('activity')->with('Finished processing file test.csv', Mockery::any())->once();
        $this->taskRunner->shouldReceive('getAllFiles')->with(['csv'])->andReturn([$mockFile]);

        // Mock FileHelper
        $fileHelperMock = Mockery::mock('alias:' . FileHelper::class);
        $fileHelperMock->shouldReceive('parseCsvFile')
            ->once()
            ->with('path/to/test.csv', 0, 1, [])
            ->andReturn($this->testCsvData);

        // Mock the splitIntoBatches method to return a single batch with all rows
        $this->taskRunner->shouldReceive('splitIntoBatches')
            ->once()
            ->with($this->testCsvData, 0)
            ->andReturn([$this->testCsvData]);

        // Mock artifact creation - should be called once for all rows
        $mockArtifact = Mockery::mock(Artifact::class);

        $this->taskRunner->shouldReceive('createArtifactWithCsvData')
            ->once()
            ->with(0, 0, $this->testCsvData, $mockFile)
            ->andReturn($mockArtifact);

        $this->taskRunner->shouldReceive('complete')
            ->once()
            ->with([$mockArtifact]);

        // Run the process
        $this->taskRunner->run();

        // Add an assertion to make PHPUnit happy
        $this->assertTrue(true, 'Run method completed successfully with batch size 0');
    }

    /**
     * Test the run method with selected columns
     */
    public function testRunWithSelectedColumns()
    {
        // Create a mock StoredFile for testing
        $mockFile = $this->createMockStoredFile();
        $mockFile->shouldReceive('getAttribute')->with('url')->andReturn('path/to/test.csv');
        $mockFile->shouldReceive('getAttribute')->with('filename')->andReturn('test.csv');

        // Define selected columns
        $selectedColumns = ['name', 'email'];

        // Create filtered data with only selected columns
        $filteredData = $this->createFilteredCsvData($selectedColumns);

        // Setup mockTaskRunner for the run method test
        $this->taskRunner->shouldReceive('config')->with('batch_size', 1)->andReturn(1);
        $this->taskRunner->shouldReceive('config')->with('selected_columns', [])->andReturn($selectedColumns);
        $this->taskRunner->shouldReceive('activity')->with('Starting CSV loading process w/ batch size 1: ["name","email"]', 10)->once();
        $this->taskRunner->shouldReceive('activity')->with('Processing CSV files with batch size: 1')->once();
        $this->taskRunner->shouldReceive('activity')->with('Processing file test.csv')->once();
        $this->taskRunner->shouldReceive('activity')->with('Finished processing file test.csv', Mockery::any())->once();
        $this->taskRunner->shouldReceive('getAllFiles')->with(['csv'])->andReturn([$mockFile]);

        // Mock FileHelper
        $fileHelperMock = Mockery::mock('alias:' . FileHelper::class);
        $fileHelperMock->shouldReceive('parseCsvFile')
            ->once()
            ->with('path/to/test.csv', 0, 1, $selectedColumns)
            ->andReturn($filteredData);

        // Mock the splitIntoBatches method to return individual rows as batches
        $batchedData = [];
        foreach($filteredData as $row) {
            $batchedData[] = [$row];
        }
        $this->taskRunner->shouldReceive('splitIntoBatches')
            ->once()
            ->with($filteredData, 1)
            ->andReturn($batchedData);

        // Mock artifact creation - should be called for each row
        $mockArtifacts = [];

        for($i = 0; $i < count($filteredData); $i++) {
            $mockArtifact    = Mockery::mock(Artifact::class);
            $mockArtifacts[] = $mockArtifact;

            $this->taskRunner->shouldReceive('createArtifactWithCsvData')
                ->once()
                ->with($i, 1, [$filteredData[$i]], $mockFile)
                ->andReturn($mockArtifact);
        }

        $this->taskRunner->shouldReceive('complete')
            ->once()
            ->with($mockArtifacts);

        // Run the process
        $this->taskRunner->run();

        // Add an assertion to make PHPUnit happy
        $this->assertTrue(true, 'Run method completed successfully with selected columns');
    }

    /**
     * Test the run method with nonexistent columns
     */
    public function testRunWithNonexistentColumns()
    {
        // Create a mock StoredFile for testing
        $mockFile = $this->createMockStoredFile();
        $mockFile->shouldReceive('getAttribute')->with('url')->andReturn('path/to/test.csv');
        $mockFile->shouldReceive('getAttribute')->with('filename')->andReturn('test.csv');

        // Define selected columns with a nonexistent one
        $selectedColumns = ['name', 'nonexistent_column'];

        // Setup mockTaskRunner for the run method test
        $this->taskRunner->shouldReceive('config')->with('batch_size', 1)->andReturn(1);
        $this->taskRunner->shouldReceive('config')->with('selected_columns', [])->andReturn($selectedColumns);
        $this->taskRunner->shouldReceive('activity')->with('Starting CSV loading process w/ batch size 1: [\"name\",\"nonexistent_column\"]', 10)->once();
        $this->taskRunner->shouldReceive('activity')->with('Processing CSV files with batch size: 1')->once();
        $this->taskRunner->shouldReceive('activity')->with('Processing file test.csv')->once();
        $this->taskRunner->shouldReceive('getAllFiles')->with(['csv'])->andReturn([$mockFile]);

        // Mock FileHelper to throw a ValidationError
        $fileHelperMock = Mockery::mock('alias:' . FileHelper::class);
        $fileHelperMock->shouldReceive('parseCsvFile')
            ->once()
            ->with('path/to/test.csv', 0, 1, $selectedColumns)
            ->andThrow(new ValidationError('Column nonexistent_column not found in CSV file'));

        $this->taskRunner->shouldReceive('error')
            ->once()
            ->with('Column nonexistent_column not found in CSV file', Mockery::any());

        $this->taskRunner->shouldReceive('complete')
            ->once()
            ->with([]);

        // Run the process
        $this->taskRunner->run();

        // Add an assertion to make PHPUnit happy
        $this->assertTrue(true, 'Run method handles nonexistent columns correctly');
    }

    /**
     * Clean up after tests
     */
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
