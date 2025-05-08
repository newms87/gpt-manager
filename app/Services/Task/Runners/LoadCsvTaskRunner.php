<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use Exception;
use Newms87\Danx\Helpers\FileHelper;
use Newms87\Danx\Models\Utilities\StoredFile;

class LoadCsvTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Load Csv';

    /**
     * Run the CSV loading task
     */
    public function run(): void
    {
        // Get config values
        $batchSize       = $this->config('batch_size', 1);
        $selectedColumns = $this->config('selected_columns', []);

        $this->activity("Starting CSV loading process w/ batch size $batchSize: " . json_encode($selectedColumns), 10);

        $allCsvFiles = $this->getAllFiles(['csv']);

        if (empty($allCsvFiles)) {
            $this->activity('No CSV file provided', 100);
            $this->complete([]);

            return;
        }

        $this->activity('Processing CSV files with batch size: ' . $batchSize);

        $outputArtifacts = [];

        $processedFiles = 0;
        $totalFiles     = count($allCsvFiles);
        foreach($allCsvFiles as $csvFile) {
            $this->activity('Processing file ' . $csvFile->filename);

            try {
                $csvData        = FileHelper::parseCsvFile($csvFile->url, 0, 1, $selectedColumns);
                $batchedCsvData = $this->splitIntoBatches($csvData, $batchSize);

                foreach($batchedCsvData as $batchIndex => $batchedCsvDatum) {
                    $outputArtifacts[] = $this->createArtifactWithCsvData($batchIndex, $batchSize, $batchedCsvDatum, $csvFile);
                }
            } catch(Exception $e) {
                $this->activity('Error processing CSV file: ' . $e->getMessage());
            } finally {
                $processedFiles++;
                $this->activity("Finished processing file " . $csvFile->filename, 10 + ($processedFiles / $totalFiles) * 90);
            }
        }

        $this->complete($outputArtifacts);
    }

    /**
     * Split the CSV data into batches
     */
    protected function splitIntoBatches(array $csvData, int $batchSize): array
    {
        $batchedCsvData = [];
        $currentBatch   = [];

        foreach($csvData as $data) {
            if ($batchSize > 0 && count($currentBatch) >= $batchSize) {
                $batchedCsvData[] = $currentBatch;
                $currentBatch     = [];
            }

            $currentBatch[] = $data;
        }

        if (!empty($currentBatch)) {
            $batchedCsvData[] = $currentBatch;
        }

        return $batchedCsvData;
    }

    /**
     * Create artifacts from the processed CSV data
     *
     * @param array      $csvData    The processed CSV data
     * @param StoredFile $sourceFile The source file that was processed
     * @param int        $batchSize  The batch size used for processing
     * @return array The created artifacts
     */
    protected function createArtifactWithCsvData(int $batchIndex, int $batchSize, array $csvData, StoredFile $sourceFile): Artifact
    {
        $baseFileName = pathinfo($sourceFile->filename, PATHINFO_FILENAME);

        $artifact = Artifact::create([
            'name'         => $baseFileName . ($batchSize > 0 ? ' - Batch ' . $batchIndex : ''),
            'json_content' => $csvData,
            'meta'         => [
                'batch_size'  => $batchSize,
                'batch_index' => $batchIndex,
            ],
        ]);

        $artifact->storedFiles()->attach($sourceFile);

        return $artifact;
    }
}
