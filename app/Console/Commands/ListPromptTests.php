<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ListPromptTests extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'prompt:list';

    /**
     * The console command description.
     */
    protected $description = 'List all available prompt tests';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $testPath = app_path('Services/PromptTesting/Tests');

        if (!File::exists($testPath)) {
            $this->error('No test directory found.');

            return 1;
        }

        $tests = $this->findTests($testPath);

        if (empty($tests)) {
            $this->info('No prompt tests found.');

            return 0;
        }

        $this->info('Available Prompt Tests:');
        $this->newLine();

        foreach ($tests as $category => $testList) {
            $this->line("<comment>{$category}</comment>");
            foreach ($testList as $test) {
                $this->line("  • {$test['name']} - {$test['description']}");
            }
            $this->newLine();
        }

        $this->info('Run a test with: sail artisan prompt:test <test-name>');
        $this->line('Example: sail artisan prompt:test McpServer/BasicMcp');

        return 0;
    }

    private function findTests(string $path): array
    {
        $tests = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($path . '/', '', $file->getPathname());
            $className    = str_replace(['/', '.php'], ['\\', ''], $relativePath);

            if ($className === 'BasePromptTest') {
                continue;
            }

            // Extract category from path
            $pathParts = explode('/', $relativePath);
            $category  = count($pathParts) > 1 ? $pathParts[0] : 'General';

            // Remove 'Test' suffix from class name for display
            $testName = str_replace('Test', '', basename($className));

            $tests[$category][] = [
                'name'        => str_replace('Test', '', $className),
                'description' => $this->getTestDescription($file->getPathname()),
                'file'        => $relativePath,
            ];
        }

        return $tests;
    }

    private function getTestDescription(string $filePath): string
    {
        $content = File::get($filePath);

        // Look for a description comment or docblock
        if (preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)\s*\n/', $content, $matches)) {
            return trim($matches[1]);
        }

        // Look for a single-line comment
        if (preg_match('/\/\/\s*(.+)/', $content, $matches)) {
            return trim($matches[1]);
        }

        return 'No description available';
    }
}
