<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use Illuminate\Support\Facades\Storage;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;

class LoadCsvTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Load Csv';

    /**
     * Run the CSV loading task
     */
    public function run(): void
    {
        $this->activity('Starting CSV loading process', 10);

        $inputArtifacts = $this->taskProcess->inputArtifacts;

        if ($inputArtifacts->isEmpty()) {
            $this->activity('No input artifacts provided', 100);
            $this->complete([]);

            return;
        }

        // Get config values
        $batchSize       = $this->config('batch_size', 1);
        $selectedColumns = $this->config('selected_columns', []);

        $this->activity('Processing CSV files with batch size: ' . $batchSize, 20);

        $outputArtifacts = [];
        $totalFiles      = $inputArtifacts->count();
        $percentPerFile  = 80 / max(1, $totalFiles);
        $processedFiles  = 0;

        foreach($inputArtifacts as $inputArtifact) {
            $storedFiles = $inputArtifact->storedFiles;

            if ($storedFiles->isEmpty()) {
                $this->activity('Input artifact has no stored files, skipping', 20 + ($processedFiles * $percentPerFile));
                $processedFiles++;
                continue;
            }

            foreach($storedFiles as $storedFile) {
                $this->activity('Processing file: ' . $storedFile->original_filename, 20 + ($processedFiles * $percentPerFile));

                // Process only CSV files
                if (!str_ends_with(strtolower($storedFile->original_filename), '.csv')) {
                    $this->activity('File is not a CSV, skipping: ' . $storedFile->original_filename, 20 + ($processedFiles * $percentPerFile));
                    continue;
                }

                try {
                    $filePath = Storage::path($storedFile->storage_path);

                    if (!file_exists($filePath)) {
                        $this->activity('File does not exist: ' . $filePath, 20 + ($processedFiles * $percentPerFile));
                        continue;
                    }

                    $csvData          = $this->processCsvFile($filePath, $selectedColumns, $batchSize);
                    $artifactsFromCsv = $this->createArtifactsFromCsvData($csvData, $storedFile, $batchSize);

                    $outputArtifacts = array_merge($outputArtifacts, $artifactsFromCsv);
                } catch(\Exception $e) {
                    $this->activity('Error processing CSV file: ' . $e->getMessage(), 20 + ($processedFiles * $percentPerFile));
                }
            }

            $processedFiles++;
        }

        $this->activity('Completed processing ' . count($outputArtifacts) . ' artifacts from CSV files', 100);
        $this->complete($outputArtifacts);
    }

    /**
     * Process the CSV file and extract data based on configuration
     *
     * @param string $filePath        Path to the CSV file
     * @param array  $selectedColumns Columns to include (empty array means all columns)
     * @param int    $batchSize       Batch size for grouping rows (0 means all rows in one batch)
     * @return array Processed CSV data
     * @throws \Exception If the file cannot be read
     */
    protected function processCsvFile(string $filePath, array $selectedColumns, int $batchSize): array
    {
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new ValidationError('Could not open CSV file');
        }

        $headers = fgetcsv($handle);

        if (!$headers) {
            fclose($handle);
            throw new ValidationError('Could not read headers from CSV file');
        }

        $headerIndexes = [];

        // If specific columns are selected, find their indexes
        if (!empty($selectedColumns)) {
            foreach($selectedColumns as $column) {
                $index = array_search($column, $headers);
                if ($index !== false) {
                    $headerIndexes[$index] = $column;
                }
            }

            // If none of the selected columns were found, use all columns
            if (empty($headerIndexes)) {
                throw new ValidationError('None of the selected columns were found in the CSV file');
            }
        } else {
            // Use all columns
            foreach($headers as $index => $header) {
                $headerIndexes[$index] = $header;
            }
        }

        $data     = [];
        $batch    = [];
        $rowCount = 0;

        while(($row = fgetcsv($handle)) !== false) {
            $rowData = [];

            // Extract only the selected columns (or all if none specified)
            foreach($headerIndexes as $index => $header) {
                if (isset($row[$index])) {
                    $rowData[$header] = $row[$index];
                }
            }

            if ($batchSize === 0) {
                // Collect all rows for one single batch
                $batch[] = $rowData;
            } else {
                if ($batchSize === 1) {
                    // Each row becomes its own 'batch' (flat object)
                    $data[] = $rowData;
                } else {
                    // Group into batches of the specified size
                    $batch[] = $rowData;
                    $rowCount++;

                    if ($rowCount >= $batchSize) {
                        $data[]   = $batch;
                        $batch    = [];
                        $rowCount = 0;
                    }
                }
            }
        }

        fclose($handle);

        // Handle any remaining rows in the last batch
        if ($batchSize !== 1 && !empty($batch)) {
            if ($batchSize === 0) {
                // All rows in one batch
                $data[] = $batch;
            } else {
                // Add the last incomplete batch
                $data[] = $batch;
            }
        }

        return $data;
    }

    /**
     * Create artifacts from the processed CSV data
     *
     * @param array      $csvData    The processed CSV data
     * @param StoredFile $sourceFile The source file that was processed
     * @param int        $batchSize  The batch size used for processing
     * @return array The created artifacts
     */
    protected function createArtifactsFromCsvData(array $csvData, StoredFile $sourceFile, int $batchSize): array
    {
        $artifacts    = [];
        $baseFileName = pathinfo($sourceFile->original_filename, PATHINFO_FILENAME);

        foreach($csvData as $index => $data) {
            $artifactName = $baseFileName;

            if (count($csvData) > 1) {
                $artifactName .= ' - Batch ' . ($index + 1);
            }

            $artifact = Artifact::create([
                'name'         => $artifactName,
                'json_content' => $data,
                'meta'         => [
                    'source_file' => $sourceFile->original_filename,
                    'batch_size'  => $batchSize,
                    'batch_index' => $index,
                ],
            ]);

            $artifact->storedFiles()->attach($sourceFile->id);
            $artifacts[] = $artifact;
        }

        return $artifacts;
    }
}
