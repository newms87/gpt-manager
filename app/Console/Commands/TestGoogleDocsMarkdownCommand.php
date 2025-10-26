<?php

namespace App\Console\Commands;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Team\Team;
use Illuminate\Console\Command;

class TestGoogleDocsMarkdownCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:google-docs-markdown
                           {team-id : Team ID for OAuth authentication}
                           {--auto-accept : Auto-accept all confirmations for testing}';

    /**
     * The console command description.
     */
    protected $description = 'Test Google Docs markdown formatting conversion - verifies bold, italic, headings work correctly';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $teamId = $this->argument('team-id');

        $this->info('=== Google Docs Markdown Formatting Test ===');
        $this->info("Team ID: $teamId");
        $this->newLine();

        // Set the team context using the provided team ID
        $team = Team::find($teamId);
        if (!$team) {
            $this->error("Team with ID $teamId not found");

            return 1;
        }

        // Set authentication context for OAuth to work
        $user = $team->users()->first();
        if (!$user) {
            $this->error("No user found for team $teamId");

            return 1;
        }

        auth()->login($user);
        app()->instance('team', $team);

        $this->info("Authenticated as: {$user->name} (Team: {$team->name})");
        $this->newLine();

        // Check OAuth status
        $this->info('=== Authentication Status ===');
        $oauthService   = app(\App\Services\Auth\OAuthService::class);
        $requiredScopes = [
            'https://www.googleapis.com/auth/documents',
            'https://www.googleapis.com/auth/drive',
        ];

        if ($oauthService->hasValidTokenWithScopes('google', $requiredScopes, $team)) {
            $this->info('✅ OAuth token available with required scopes');
        } else {
            $this->error('❌ No valid OAuth token with required scopes');
            $this->info("Run: php artisan test:google-docs-template {$teamId} <doc-id> <object-id> to set up OAuth");

            return 1;
        }
        $this->newLine();

        // Define test markdown content
        $testMarkdown = "**Initial Medical Evaluation – Synergy Chiropractic Clinics**\n\n**Dates of Treatment: 10/31/2017 No. of Visits: 12 (planned; 3x/week for 4 weeks)**\n\nOn October 31, 2017, Abdinasir Abdi presented to Synergy Chiropractic Clinics following...";

        $this->info('=== Test Markdown Content ===');
        $this->line($testMarkdown);
        $this->newLine();

        // Parse markdown to show what will be formatted
        $api         = app(GoogleDocsApi::class);
        $reflection  = new \ReflectionClass($api);
        $parseMethod = $reflection->getMethod('parseMarkdown');
        $parseMethod->setAccessible(true);
        $parsed = $parseMethod->invoke($api, $testMarkdown);

        $this->info('=== Parsed Plain Text ===');
        $this->line($parsed['plainText']);
        $this->newLine();

        $this->info('=== Format Instructions ===');
        foreach ($parsed['formats'] as $format) {
            $text = substr($parsed['plainText'], $format['start'], $format['end'] - $format['start']);
            $this->info("{$format['type']}: [{$format['start']}-{$format['end']}] = \"{$text}\"");
        }
        $this->newLine();

        // Confirm before creating documents
        if (!$this->option('auto-accept') && !$this->confirm('Create test documents in Google Docs?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $this->info('=== Creating Template Document ===');

        try {
            // Create template with variable placeholder
            $templateContent = 'This is a test template.\n\n{{medical_summary}}\n\nEnd of template.';

            $this->info('=== Debug Template Content ===');
            $this->info('Length: ' . strlen($templateContent));
            $this->info('Has actual newlines: ' . (strpos($templateContent, "\n") !== false ? 'YES' : 'NO'));
            $this->info('Has literal \\n: ' . (strpos($templateContent, '\\' . 'n') !== false ? 'YES' : 'NO'));
            $this->newLine();

            $templateResult = $api->createDocument(
                'Markdown Test Template - ' . now()->format('Y-m-d H:i:s'),
                $templateContent
            );

            $this->info('✅ Template created:');
            $this->info("   URL: {$templateResult['url']}");
            $this->info("   ID: {$templateResult['document_id']}");
            $this->newLine();

            // Create document from template with markdown variable
            $this->info('=== Creating Document from Template with Markdown ===');

            $variableMappings = [
                'medical_summary' => $testMarkdown,
            ];

            // Create from template with debugging
            $this->info('=== Debug: Calling createDocumentFromTemplate ===');
            $result = $api->createDocumentFromTemplate(
                $templateResult['document_id'],
                $variableMappings,
                'Markdown Formatting Test - ' . now()->format('Y-m-d H:i:s')
            );

            // Read back and show the raw document structure
            $this->info('=== Debug: Fetching document structure ===');
            $docResponse = $api->get("documents/{$result['document_id']}")->json();

            if (isset($docResponse['error'])) {
                $this->error('Error fetching document: ' . json_encode($docResponse['error']));
            }

            $this->info('Has body: ' . (isset($docResponse['body']) ? 'YES' : 'NO'));
            $this->info('Body keys: ' . implode(', ', array_keys($docResponse['body'] ?? [])));
            $this->info('Document body content count: ' . count($docResponse['body']['content'] ?? []));
            $this->info('ALL text runs (showing indices 0-200):');
            foreach ($docResponse['body']['content'] ?? [] as $element) {
                if (isset($element['paragraph']['elements'])) {
                    foreach ($element['paragraph']['elements'] as $pe) {
                        if (isset($pe['textRun']['content'])) {
                            $start = $pe['startIndex'] ?? 0;
                            $end   = $pe['endIndex']   ?? 0;
                            // Show text runs
                            if ($start >= 0 && $start <= 200) {
                                $text        = $pe['textRun']['content'];
                                $textPreview = str_replace("\n", '\\n', substr($text, 0, 50));
                                $isBold      = isset($pe['textRun']['textStyle']['bold']) && $pe['textRun']['textStyle']['bold'];
                                $this->info(sprintf(
                                    '  [%d-%d] %s: %s',
                                    $start,
                                    $end,
                                    $isBold ? 'BOLD' : 'plain',
                                    json_encode($textPreview)
                                ));
                            }
                        }
                    }
                }
            }

            $this->info('✅ Document created from template:');
            $this->info("   URL: {$result['url']}");
            $this->info("   ID: {$result['document_id']}");
            $this->newLine();

            // Read back the document to see what Google Docs actually stored
            $this->info('=== Reading Back Document Content ===');
            $documentData = $api->readDocument($result['document_id']);
            $this->info('Document content:');
            $this->line($documentData['content']);
            $this->newLine();

            $this->info('=== Verification Instructions ===');
            $this->info('Please check the document at the URL above and verify:');
            $this->info('✓ No literal \\n characters (should be actual line breaks)');
            $this->info("✓ 'Initial Medical Evaluation – Synergy Chiropractic Clinics' is FULLY bolded");
            $this->info("✓ 'Dates of Treatment: 10/31/2017...' is FULLY bolded (including 'Da')");
            $this->info("✓ The 'ne' in 'Synergy' is bolded (not missing)");
            $this->newLine();

            $this->info("🔗 Document URL: {$result['url']}");

        } catch (\Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");
            $this->error("Stack trace: {$e->getTraceAsString()}");

            return 1;
        }

        $this->newLine();
        $this->info('✅ Test completed successfully!');

        return 0;
    }
}
