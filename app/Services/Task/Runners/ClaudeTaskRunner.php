<?php

namespace App\Services\Task\Runners;

use App\Jobs\ClaudeCodeGenerationJob;
use App\Models\Task\Artifact;
use Exception;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ClaudeTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Claude Code';

    public function prepareProcess(): void
    {
        $this->activity('Preparing Claude code execution', 1);

        $taskDescription = $this->config('task_description');
        if (!$taskDescription) {
            throw new Exception('Task description is required for Claude Code runner');
        }

        $this->taskProcess->name = 'Claude Code: ' . Str::limit($taskDescription, 50);
    }

    public function run(): void
    {
        $generatedCode   = $this->config('generated_code');
        $taskDescription = $this->config('task_description');

        if (!$generatedCode) {
            $this->activity('Generating code with Claude for: ' . Str::limit($taskDescription, 100), 10);

            // Dispatch job to generate code and create a pending task to execute it
            ClaudeCodeGenerationJob::dispatch($this->taskRun);

            // This process is complete - the job will create a new task process
            $this->complete();
        } else {
            $this->activity('Executing generated Claude code', 20);
            $this->executeGeneratedCode($generatedCode);
        }
    }

    /**
     * Execute the generated PHP code in a controlled environment
     */
    protected function executeGeneratedCode(string $code): void
    {
        try {
            $this->activity('Validating generated code', 30);

            // Basic validation of the generated code
            if (!$this->isValidPhpCode($code)) {
                throw new Exception('Generated code is not valid PHP');
            }

            $this->activity('Running generated code', 50);

            // Create a temporary file to execute the code
            $tempFile = $this->createTempCodeFile($code);

            try {
                // Execute the code with timeout
                $result = Process::timeout($this->taskDefinition->timeout_after_seconds ?? 300)
                    ->run("php $tempFile");

                if (!$result->successful()) {
                    throw new Exception('Code execution failed: ' . $result->errorOutput());
                }

                $this->activity('Processing code execution results', 80);

                // Parse the output and create artifacts
                $artifacts = $this->processCodeOutput($result->output());

                $this->activity('Code execution completed successfully', 100);
                $this->complete($artifacts);

            } finally {
                // Clean up temp file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }

        } catch (Exception $e) {
            $this->taskProcess->failed_at = now();
            $this->taskProcess->save();
            $this->activity('Code execution failed: ' . $e->getMessage(), 100);
            throw $e;
        }
    }

    /**
     * Validate that the generated code is valid PHP
     */
    protected function isValidPhpCode(string $code): bool
    {
        // Check for PHP opening tag
        if (!str_starts_with(trim($code), '<?php')) {
            return false;
        }

        // Basic syntax check
        $tempFile = tempnam(sys_get_temp_dir(), 'claude_code_check_');
        file_put_contents($tempFile, $code);

        try {
            $result = Process::run("php -l $tempFile");

            return $result->successful();
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Create a temporary file with the generated code
     */
    protected function createTempCodeFile(string $code): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'claude_code_exec_');

        // Wrap the code to provide context and capture output
        $wrappedCode = $this->wrapCodeForExecution($code);

        file_put_contents($tempFile, $wrappedCode);

        return $tempFile;
    }

    /**
     * Wrap the generated code to provide context and capture output
     */
    protected function wrapCodeForExecution(string $code): string
    {
        // Remove opening PHP tag if present to avoid duplication
        $code = preg_replace('/^<\?php\s*/', '', trim($code));

        return '<?php

// Auto-generated code execution wrapper
try {
    // Make input artifacts available
    $inputArtifacts = ' . var_export($this->getInputArtifactsData(), true) . ";
    
    // Store output for processing
    \$output = [];
    \$artifacts = [];
    
    // Helper function to create artifacts
    function createArtifact(\$name, \$content, \$type = 'text') {
        global \$artifacts;
        \$artifacts[] = [
            'name' => \$name,
            'content' => \$content,
            'type' => \$type
        ];
    }
    
    // Generated code starts here
    $code
    
    // Output results as JSON
    echo json_encode([
        'success' => true,
        'artifacts' => \$artifacts,
        'output' => \$output
    ]);
    
} catch (Exception \$e) {
    echo json_encode([
        'success' => false,
        'error' => \$e->getMessage(),
        'trace' => \$e->getTraceAsString()
    ]);
}";
    }

    /**
     * Get input artifacts data for use in generated code
     */
    protected function getInputArtifactsData(): array
    {
        $data = [];

        foreach ($this->taskProcess->inputArtifacts as $artifact) {
            $data[] = [
                'id'           => $artifact->id,
                'name'         => $artifact->name,
                'text_content' => $artifact->text_content,
                'json_content' => $artifact->json_content,
                'files'        => $artifact->storedFiles->map(function ($file) {
                    return [
                        'filename'  => $file->filename,
                        'path'      => $file->getLocalPath(),
                        'size'      => $file->size,
                        'mime_type' => $file->mime_type,
                    ];
                })->toArray(),
            ];
        }

        return $data;
    }

    /**
     * Process the output from code execution and create artifacts
     */
    protected function processCodeOutput(string $output): array
    {
        $artifacts = [];

        try {
            $result = json_decode($output, true);

            if (!$result['success']) {
                throw new Exception('Code execution error: ' . $result['error']);
            }

            // Create artifacts from the generated code output
            foreach ($result['artifacts'] ?? [] as $artifactData) {
                $artifact = new Artifact([
                    'name'               => $artifactData['name'],
                    'task_definition_id' => $this->taskDefinition->id,
                    'task_process_id'    => $this->taskProcess->id,
                ]);

                if ($artifactData['type'] === 'json') {
                    $artifact->json_content = is_string($artifactData['content'])
                        ? json_decode($artifactData['content'], true)
                        : $artifactData['content'];
                } else {
                    $artifact->text_content = $artifactData['content'];
                }

                $artifact->save();
                $artifacts[] = $artifact;
            }

        } catch (Exception $e) {
            // If JSON parsing fails, treat the entire output as text content
            $artifact = new Artifact([
                'name'               => 'Claude Code Output',
                'text_content'       => $output,
                'task_definition_id' => $this->taskDefinition->id,
                'task_process_id'    => $this->taskProcess->id,
            ]);
            $artifact->save();
            $artifacts[] = $artifact;
        }

        return $artifacts;
    }
}
